<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Loan;
use App\Models\LoanGuarantor;
use App\Models\LoanProduct;
use App\Models\SavingsAccount;
use App\Services\LoanService;
use App\Services\SavingsService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class LoanController extends Controller
{
    public function __construct(
        protected LoanService $loanService,
        protected SavingsService $savingsService,
    ) {}

    public function index(Request $request)
    {
        $loans = Loan::with('client', 'product')
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->when($request->search, fn($q) => $q->where('loan_number', 'like', "%{$request->search}%")
                ->orWhereHas('client', fn($q2) => $q2->where('name', 'like', "%{$request->search}%")))
            ->latest()
            ->paginate(20);

        return view('loans.index', compact('loans'));
    }

    public function create(Request $request)
    {
        $clients  = Client::where('status', 'active')->orderBy('name')->get();
        $products = LoanProduct::where('is_active', true)->get();
        $selectedClient = $request->client_id ? Client::find($request->client_id) : null;

        return view('loans.create', compact('clients', 'products', 'selectedClient'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'client_id'       => 'required|exists:clients,id',
            'loan_product_id' => 'required|exists:loan_products,id',
            'principal'       => 'required|numeric|min:1',
            'interest_rate'   => 'nullable|numeric|min:0',
            'interest_method' => 'nullable|in:flat,reducing',
            'term_months'     => 'nullable|integer|min:1',
            'notes'           => 'nullable|string',
            // Guarantors
            'guarantors'                    => 'nullable|array',
            'guarantors.*.name'             => 'required_with:guarantors|string|max:100',
            'guarantors.*.phone'            => 'nullable|string|max:20',
            'guarantors.*.id_number'        => 'nullable|string|max:30',
            'guarantors.*.relationship'     => 'nullable|string|max:50',
            'guarantors.*.address'          => 'nullable|string|max:200',
            'guarantors.*.employer'         => 'nullable|string|max:100',
            'guarantors.*.monthly_income'   => 'nullable|numeric|min:0',
            'guarantors.*.photo'            => 'nullable|image|max:2048',
        ]);

        $loan = $this->loanService->createLoan($data);

        // Save guarantors
        if (!empty($data['guarantors'])) {
            foreach ($data['guarantors'] as $idx => $g) {
                if (empty(trim($g['name'] ?? ''))) continue;
                $photo = $request->file("guarantors.$idx.photo");
                LoanGuarantor::create([
                    'loan_id'       => $loan->id,
                    'name'          => $g['name'],
                    'phone'         => $g['phone'] ?? null,
                    'id_number'     => $g['id_number'] ?? null,
                    'relationship'  => $g['relationship'] ?? null,
                    'address'       => $g['address'] ?? null,
                    'employer'      => $g['employer'] ?? null,
                    'monthly_income'=> $g['monthly_income'] ?? null,
                    'photo'         => $photo ? $this->storeGuarantorPhoto($photo) : null,
                ]);
            }
        }

        return redirect()->route('loans.show', $loan)->with('success', 'Loan application created. Pending disbursement.');
    }

    public function show(Loan $loan)
    {
        $loan->load('client', 'product', 'schedules', 'repayments.receivedBy', 'createdBy', 'approvedBy', 'guarantors');
        $notYetDisbursed = in_array($loan->status, ['pending', 'approved']);
        $schedulePreview = $notYetDisbursed
            ? $this->loanService->previewSchedule($loan)
            : [];

        // Savings accounts for this client (for fee deduction)
        $clientSavingsAccounts = $notYetDisbursed
            ? SavingsAccount::where('client_id', $loan->client_id)->where('status', 'active')->with('product')->get()
            : collect();

        $currentPenalty   = $this->loanService->calculatePenaltyPublic($loan);
        $penaltyBreakdown = $this->loanService->penaltyBreakdown($loan);

        return view('loans.show', compact('loan', 'schedulePreview', 'clientSavingsAccounts', 'currentPenalty', 'penaltyBreakdown'));
    }

    public function approve(Loan $loan)
    {
        if ($loan->status !== 'pending') {
            return back()->with('error', 'Only pending loans can be approved.');
        }

        $loan->update([
            'status'      => 'approved',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        return back()->with('success', 'Loan approved. It can now be disbursed.');
    }

    public function disburse(Request $request, Loan $loan)
    {
        $request->validate([
            'disbursement_date'        => ['required', 'date', 'before_or_equal:today', new \App\Rules\DateInOpenPeriod()],
            'application_fee_amount'   => 'required|numeric|min:0',
            'application_fee_method'   => 'required|in:loan,savings',
            'management_fee_rate'      => 'required|numeric|min:0|max:100',
            'management_fee_method'    => 'required|in:loan,savings',
            'insurance_fee_rate'       => 'required|numeric|min:0|max:100',
            'insurance_fee_method'     => 'required|in:loan,savings',
            'fee_savings_account_id'   => 'nullable|exists:savings_accounts,id',
        ]);

        // Savings account required if any fee method is savings
        $needsSavings = in_array('savings', [
            $request->application_fee_method,
            $request->management_fee_method,
            $request->insurance_fee_method,
        ]);
        if ($needsSavings && empty($request->fee_savings_account_id)) {
            return back()->withErrors(['fee_savings_account_id' => 'A savings account is required when any fee is set to deduct from savings.'])->withInput();
        }

        if ($loan->status !== 'approved') {
            return back()->with('error', 'Only approved loans can be disbursed. Get this loan approved first.');
        }

        try {
            $this->loanService->disburseLoan($loan, $request->disbursement_date, [
                'savings_account_id'      => $request->fee_savings_account_id,
                'application_fee_amount'  => $request->application_fee_amount,
                'application_fee_method'  => $request->application_fee_method,
                'management_fee_rate'     => $request->management_fee_rate,
                'management_fee_method'   => $request->management_fee_method,
                'insurance_fee_rate'      => $request->insurance_fee_rate,
                'insurance_fee_method'    => $request->insurance_fee_method,
            ]);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('loans.show', $loan)->with('success', 'Loan disbursed successfully. Schedule generated.');
    }

    public function repayForm(Loan $loan)
    {
        if ($loan->status !== 'active') {
            return back()->with('error', 'Only active loans can accept repayments.');
        }

        $loan->load('client', 'product', 'schedules');

        // Next unpaid/partial installment
        $nextInstallment = $loan->schedules
            ->whereIn('status', ['pending', 'partial', 'overdue'])
            ->sortBy('installment_no')
            ->first();

        // Overdue installments count
        $overdueInstallments = $loan->schedules
            ->filter(fn($s) => $s->isOverdue() && $s->status !== 'paid');

        // Amount owed on overdue installments (excluding penalty)
        $overdueAmount = $overdueInstallments->sum(
            fn($s) => ($s->principal_due - $s->principal_paid) + ($s->interest_due - $s->interest_paid)
        );

        // Suggested = next installment remaining (principal + interest)
        $suggestedAmount = $nextInstallment
            ? round(($nextInstallment->principal_due - $nextInstallment->principal_paid) + ($nextInstallment->interest_due - $nextInstallment->interest_paid), 2)
            : round($loan->total_outstanding, 2);

        // Client savings accounts
        $savingsAccounts = \App\Models\SavingsAccount::where('client_id', $loan->client_id)
            ->where('status', 'active')
            ->with('product')
            ->get();

        // Penalty
        $penaltyDue = $this->loanService->calculatePenaltyPublic($loan);

        // Early settlement amount (principal + interest due to today only)
        $earlySettlement = $this->loanService->calculateEarlySettlement($loan, today()->toDateString());

        // Schedules as JSON for JS allocation preview
        $schedulesJson = $loan->schedules
            ->whereIn('status', ['pending', 'partial', 'overdue'])
            ->sortBy('installment_no')
            ->values()
            ->map(fn($s) => [
                'installment_no' => $s->installment_no,
                'interest_rem'   => round(max(0, $s->interest_due - $s->interest_paid), 2),
                'principal_rem'  => round(max(0, $s->principal_due - $s->principal_paid), 2),
            ]);

        $paymentSourceAccounts = \App\Models\Account::where('is_payment_source', true)->where('is_active', true)->orderBy('account_code')->get();

        return view('loans.repay', compact(
            'loan', 'nextInstallment', 'overdueInstallments', 'overdueAmount',
            'suggestedAmount', 'savingsAccounts', 'penaltyDue', 'earlySettlement', 'schedulesJson', 'paymentSourceAccounts'
        ));
    }

    public function repay(Request $request, Loan $loan)
    {
        $request->validate([
            'payment_date'              => ['required', 'date', 'before_or_equal:today', new \App\Rules\DateInOpenPeriod()],
            'amount'                    => 'required|numeric|min:0.01',
            'payment_source_account_id' => 'required',
            'savings_account_id'        => 'required_if:payment_source_account_id,savings|nullable|exists:savings_accounts,id',
            'reference'                 => 'required|string|max:100|unique:transactions,reference',
            'notes'                     => 'nullable|string',
        ]);

        $isSavings = $request->payment_source_account_id === 'savings';
        $request->merge([
            'payment_method'            => $isSavings ? 'savings' : 'direct',
            'payment_source_account_id' => $isSavings ? null : (int) $request->payment_source_account_id,
        ]);

        if ($loan->status !== 'active') {
            return back()->with('error', 'Only active loans can accept repayments.');
        }

        $total = $loan->outstanding_principal + $loan->outstanding_interest + $loan->outstanding_penalty;
        if ($request->amount > $total + 0.01) {
            return back()->withErrors(['amount' => 'Repayment cannot exceed total outstanding balance.'])->withInput();
        }

        // Deduct from savings account if payment method is savings
        if ($request->payment_method === 'savings' && $request->savings_account_id) {
            $savingsAccount = SavingsAccount::findOrFail($request->savings_account_id);
            try {
                $this->savingsService->withdraw(
                    $savingsAccount,
                    $request->amount,
                    $request->payment_date,
                    "Loan repayment - {$loan->loan_number}",
                    null,  // let accounting auto-generate; user's reference is reserved for the loan repayment journal
                    0.0   // no withdrawal fee on loan recoveries
                );
            } catch (\InvalidArgumentException $e) {
                return back()->with('error', 'Savings withdrawal failed: ' . $e->getMessage())->withInput();
            }
        }

        $this->loanService->processRepayment($loan, $request->all());

        return redirect()->route('loans.show', $loan)->with('success', 'Repayment processed successfully.');
    }

    public function storeGuarantor(Request $request, Loan $loan)
    {
        $data = $request->validate([
            'name'          => 'required|string|max:100',
            'phone'         => 'nullable|string|max:20',
            'id_number'     => 'nullable|string|max:30',
            'relationship'  => 'nullable|string|max:50',
            'address'       => 'nullable|string|max:200',
            'employer'      => 'nullable|string|max:100',
            'monthly_income'=> 'nullable|numeric|min:0',
            'photo'         => 'nullable|image|max:2048',
        ]);

        if ($request->hasFile('photo')) {
            $data['photo'] = $this->storeGuarantorPhoto($request->file('photo'));
        }

        LoanGuarantor::create(array_merge($data, ['loan_id' => $loan->id]));

        return back()->with('success', 'Guarantor added successfully.');
    }

    public function destroyGuarantor(Loan $loan, LoanGuarantor $guarantor)
    {
        abort_if($guarantor->loan_id !== $loan->id, 403);
        if ($guarantor->photo && file_exists(public_path($guarantor->photo))) {
            @unlink(public_path($guarantor->photo));
        }
        $guarantor->delete();
        return back()->with('success', 'Guarantor removed.');
    }

    /**
     * Stores directly under public/uploads/guarantors rather than the
     * storage/app/public disk - same reasoning as ClientController's
     * storeClientPhoto: this host blocks the storage symlink from resolving.
     */
    private function storeGuarantorPhoto($file): string
    {
        $dir = public_path('uploads/guarantors');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filename = 'uploads/guarantors/' . uniqid('guarantor_') . '.' . $file->getClientOriginalExtension();
        $file->move($dir, basename($filename));

        return $filename;
    }

    public function schedule(Loan $loan)
    {
        $loan->load('schedules', 'client', 'product');
        $schedulePreview  = in_array($loan->status, ['pending', 'approved'])
            ? $this->loanService->previewSchedule($loan)
            : [];
        $currentPenalty   = $this->loanService->calculatePenaltyPublic($loan);
        $penaltyBreakdown = $this->loanService->penaltyBreakdown($loan);
        return view('loans.schedule', compact('loan', 'schedulePreview', 'currentPenalty', 'penaltyBreakdown'));
    }

    public function statement(Loan $loan)
    {
        $loan->load(['client', 'product', 'schedules', 'repayments' => fn($q) => $q->with('receivedBy')->orderBy('payment_date')->orderBy('id')]);
        $currentPenalty   = $this->loanService->calculatePenaltyPublic($loan);
        $penaltyBreakdown = $this->loanService->penaltyBreakdown($loan);
        return view('loans.statement', compact('loan', 'currentPenalty', 'penaltyBreakdown'));
    }

    public function statementPdf(Loan $loan)
    {
        $loan->load(['client', 'product', 'schedules', 'repayments' => fn($q) => $q->with('receivedBy')->orderBy('payment_date')->orderBy('id')]);
        $currentPenalty   = $this->loanService->calculatePenaltyPublic($loan);
        $penaltyBreakdown = $this->loanService->penaltyBreakdown($loan);
        $pdf = Pdf::loadView('pdf.loan-statement', compact('loan', 'currentPenalty', 'penaltyBreakdown'))
            ->setPaper('a4', 'portrait');
        return $pdf->download("loan-statement-{$loan->loan_number}.pdf");
    }

    public function schedulePdf(Loan $loan)
    {
        $loan->load('client', 'product', 'schedules');
        $schedulePreview  = in_array($loan->status, ['pending', 'approved'])
            ? $this->loanService->previewSchedule($loan)
            : [];
        $currentPenalty   = $this->loanService->calculatePenaltyPublic($loan);
        $penaltyBreakdown = $this->loanService->penaltyBreakdown($loan);
        $pdf = Pdf::loadView('pdf.loan-schedule', compact('loan', 'schedulePreview', 'currentPenalty', 'penaltyBreakdown'))
            ->setPaper('a4', 'portrait');
        return $pdf->download("loan-schedule-{$loan->loan_number}.pdf");
    }

    public function destroy(Loan $loan)
    {
        if (!in_array($loan->status, ['pending', 'approved'])) {
            return back()->with('error', 'Only pending or approved (not yet disbursed) loans can be deleted.');
        }
        foreach ($loan->guarantors as $guarantor) {
            if ($guarantor->photo && file_exists(public_path($guarantor->photo))) {
                @unlink(public_path($guarantor->photo));
            }
        }
        $loan->guarantors()->delete();
        $loan->delete();
        return redirect()->route('loans.index')->with('success', 'Loan deleted.');
    }
}
