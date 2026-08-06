<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Loan;
use App\Models\LoanProduct;
use App\Services\LoanService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class LoanProductController extends Controller
{
    public function __construct(protected LoanService $loanService) {}

    public function index()
    {
        $products = LoanProduct::paginate(20);
        return view('loan-products.index', compact('products'));
    }

    public function create()
    {
        $accounts = Account::where('is_active', true)->orderBy('account_code')->get();
        return view('loan-products.create', compact('accounts'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'                       => 'required|string|max:255',
            'interest_rate'              => 'required|numeric|min:0|max:100',
            'interest_method'            => 'required|in:flat,reducing',
            'repayment_frequency'        => 'required|in:daily,weekly,monthly,quarterly,annually',
            'term_months'                => 'required|integer|min:1',
            'penalty_rate'               => 'nullable|numeric|min:0',
            'min_amount'                 => 'nullable|numeric|min:0',
            'max_amount'                 => 'nullable|numeric|min:0',
            'receivable_account_id'      => 'nullable|exists:accounts,id',
            'interest_income_account_id' => 'nullable|exists:accounts,id',
            'penalty_income_account_id'  => 'nullable|exists:accounts,id',
            'disbursement_account_id'    => 'nullable|exists:accounts,id',
            'is_active'                  => 'boolean',
        ]);

        LoanProduct::create($data);

        return redirect()->route('loan-products.index')->with('success', 'Loan product created.');
    }

    public function show(LoanProduct $loanProduct)
    {
        $loanProduct->load('receivableAccount', 'interestIncomeAccount', 'penaltyIncomeAccount', 'disbursementAccount');
        $stats = [
            'total_loans'       => $loanProduct->loans()->count(),
            'active_loans'      => $loanProduct->loans()->where('status', 'active')->count(),
            'total_disbursed'   => $loanProduct->loans()->where('status', '!=', 'pending')->sum('principal'),
            'total_outstanding' => $loanProduct->loans()->where('status', 'active')->sum('outstanding_principal'),
        ];
        return view('loan-products.show', compact('loanProduct', 'stats'));
    }

    public function edit(LoanProduct $loanProduct)
    {
        $accounts = Account::where('is_active', true)->orderBy('account_code')->get();
        return view('loan-products.edit', compact('loanProduct', 'accounts'));
    }

    public function update(Request $request, LoanProduct $loanProduct)
    {
        $data = $request->validate([
            'name'                       => 'required|string|max:255',
            'interest_rate'              => 'required|numeric|min:0|max:100',
            'interest_method'            => 'required|in:flat,reducing',
            'repayment_frequency'        => 'required|in:daily,weekly,monthly,quarterly,annually',
            'term_months'                => 'required|integer|min:1',
            'penalty_rate'               => 'nullable|numeric|min:0',
            'min_amount'                 => 'nullable|numeric|min:0',
            'max_amount'                 => 'nullable|numeric|min:0',
            'receivable_account_id'      => 'nullable|exists:accounts,id',
            'interest_income_account_id' => 'nullable|exists:accounts,id',
            'penalty_income_account_id'  => 'nullable|exists:accounts,id',
            'disbursement_account_id'    => 'nullable|exists:accounts,id',
            'is_active'                  => 'boolean',
        ]);

        $loanProduct->update($data);

        return redirect()->route('loan-products.index')->with('success', 'Loan product updated.');
    }

    /**
     * AJAX: return a projected schedule for the product setup preview panel.
     */
    public function schedulePreview(Request $request)
    {
        $data = $request->validate([
            'principal'            => 'required|numeric|min:1',
            'interest_rate'        => 'required|numeric|min:0|max:100',
            'interest_method'      => 'required|in:flat,reducing',
            'repayment_frequency'  => 'required|in:daily,weekly,monthly,quarterly,annually',
            'term_months'          => 'required|integer|min:1|max:360',
        ]);

        // For quarterly/annually, term must divide evenly into that many months
        if ($data['repayment_frequency'] === 'quarterly' && $data['term_months'] % 3 !== 0) {
            return response()->json(['error' => 'For quarterly repayments, the term in months must be a multiple of 3.'], 422);
        }
        if ($data['repayment_frequency'] === 'annually' && $data['term_months'] % 12 !== 0) {
            return response()->json(['error' => 'For annual repayments, the term in months must be a multiple of 12.'], 422);
        }

        // Build a temporary unsaved Loan object to reuse buildScheduleRows()
        $loan = new Loan([
            'principal'           => $data['principal'],
            'interest_rate'       => $data['interest_rate'],
            'interest_method'     => $data['interest_method'],
            'repayment_frequency' => $data['repayment_frequency'],
            'term_months'         => $data['term_months'],
        ]);

        $rows = $this->loanService->buildScheduleRows($loan, Carbon::today(), true);

        return response()->json(['rows' => $rows, 'frequency' => $data['repayment_frequency']]);
    }
}
