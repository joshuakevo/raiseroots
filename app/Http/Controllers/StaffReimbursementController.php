<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Employee;
use App\Models\StaffReimbursement;
use App\Services\StaffReimbursementService;
use Illuminate\Http\Request;

class StaffReimbursementController extends Controller
{
    public function __construct(protected StaffReimbursementService $service) {}

    public function index(Request $request)
    {
        $query = StaffReimbursement::with('employee', 'expenseAccount')
            ->when($request->employee_id, fn ($q) => $q->where('employee_id', $request->employee_id))
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->from_date, fn ($q) => $q->whereDate('date', '>=', $request->from_date))
            ->when($request->to_date, fn ($q) => $q->whereDate('date', '<=', $request->to_date));

        // Totals cover everything matching the filters (paid only), not just this page.
        $totals = (clone $query)->where('status', 'paid')
            ->selectRaw('COALESCE(SUM(amount),0) as amount, COALESCE(SUM(deduction),0) as deduction, COALESCE(SUM(net_amount),0) as net, COUNT(*) as count')
            ->first();

        $reimbursements = $query->orderByDesc('date')->orderByDesc('id')->paginate(25)->withQueryString();
        $employees      = Employee::orderBy('name')->get(['id', 'name', 'employee_number']);

        return view('staff-reimbursements.index', compact('reimbursements', 'employees', 'totals'));
    }

    public function create()
    {
        $employees = Employee::with('savingsAccount')->where('status', 'active')->orderBy('name')->get();
        $expenseAccounts = Account::where('account_type', 'expense')->where('is_active', true)->orderBy('account_code')->get();
        $allAccounts     = Account::where('is_active', true)->orderBy('account_code')->get();
        $paymentAccounts = Account::where('is_payment_source', true)->where('is_active', true)->orderBy('account_code')->get();

        return view('staff-reimbursements.create', compact('employees', 'expenseAccounts', 'allAccounts', 'paymentAccounts'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'employee_id'          => 'required|exists:employees,id',
            'date'                 => ['required', 'date', 'before_or_equal:today', new \App\Rules\DateInOpenPeriod()],
            'description'          => 'required|string|max:255',
            'expense_account_id'   => 'required|exists:accounts,id',
            'amount'               => 'required|numeric|min:1',
            'deduction'            => 'nullable|numeric|min:0',
            'deduction_reason'     => 'nullable|string|max:255',
            'deduction_account_id' => 'nullable|exists:accounts,id',
            'payment_method'       => 'required|in:cash,savings',
            'payment_account_id'   => 'required_if:payment_method,cash|nullable|exists:accounts,id',
        ]);

        if ((float) ($data['deduction'] ?? 0) > 0 && (empty($data['deduction_account_id']) || empty($data['deduction_reason']))) {
            return back()->withInput()->with('error', 'For a deduction, enter the reason and choose which account it goes to.');
        }

        try {
            $reimbursement = $this->service->pay($data);
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('staff-reimbursements.show', $reimbursement)
            ->with('success', "Reimbursement {$reimbursement->reference} paid: " . number_format($reimbursement->net_amount, 2) . " to {$reimbursement->employee->name}.");
    }

    public function show(StaffReimbursement $staffReimbursement)
    {
        $staffReimbursement->load('employee', 'expenseAccount', 'deductionAccount', 'paymentAccount', 'savingsAccount', 'transaction', 'createdBy');

        return view('staff-reimbursements.show', ['r' => $staffReimbursement]);
    }
}
