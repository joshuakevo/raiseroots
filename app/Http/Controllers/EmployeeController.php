<?php
namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Client;
use App\Models\Employee;
use App\Models\SavingsAccount;
use Illuminate\Http\Request;

class EmployeeController extends Controller {
    public function index() {
        $employees = Employee::with('savingsAccount', 'paymentSourceAccount')->latest()->paginate(25);
        return view('employees.index', compact('employees'));
    }

    public function create() {
        [$clients, $savingsAccounts, $paymentSourceAccounts] = $this->formOptions();
        return view('employees.create', compact('clients', 'savingsAccounts', 'paymentSourceAccounts'));
    }

    public function store(Request $request) {
        $data = $this->validateEmployee($request);

        if (!empty($data['client_id']) && Employee::where('client_id', $data['client_id'])->exists()) {
            return back()->withErrors(['client_id' => 'This client is already registered as an employee.'])->withInput();
        }

        $data['employee_number'] = $this->generateEmployeeNumber();
        $data['created_by']      = auth()->id();
        Employee::create($data);
        return redirect()->route('employees.index')->with('success', 'Employee created successfully.');
    }

    public function show(Employee $employee) {
        $employee->load('client', 'savingsAccount.product', 'paymentSourceAccount', 'payrollItems.payrollRun');
        return view('employees.show', compact('employee'));
    }

    public function edit(Employee $employee) {
        [$clients, $savingsAccounts, $paymentSourceAccounts] = $this->formOptions();

        // Make sure the employee's own current client/savings account stay selectable
        // even if they no longer match the base "active client with active savings" list
        // (e.g. the client went inactive since the employee was linked).
        if ($employee->client_id && !$clients->contains('id', $employee->client_id)) {
            $currentClient = Client::find($employee->client_id);
            if ($currentClient) {
                $clients = $clients->push($currentClient)->sortBy('name')->values();
            }
        }
        if ($employee->client_id) {
            $savingsAccounts[$employee->client_id] = SavingsAccount::with('product')
                ->where('client_id', $employee->client_id)
                ->where('status', 'active')
                ->get();
        }

        return view('employees.edit', compact('employee', 'clients', 'savingsAccounts', 'paymentSourceAccounts'));
    }

    public function update(Request $request, Employee $employee) {
        $data = $this->validateEmployee($request, $employee);
        $employee->update($data);
        return redirect()->route('employees.show', $employee)->with('success', 'Employee updated.');
    }

    /** Shared create/edit validation — payout requirements depend on payment_method. */
    private function validateEmployee(Request $request, ?Employee $employee = null): array
    {
        $data = $request->validate([
            'name'                      => 'required|string|max:150',
            'phone'                     => 'nullable|string|max:20',
            'email'                     => 'nullable|email|max:150',
            'id_number'                 => 'nullable|string|max:30',
            'position'                  => 'nullable|string|max:100',
            'department'                => 'nullable|string|max:100',
            'basic_salary'              => 'required|numeric|min:0',
            'payment_method'            => 'required|in:savings,cash',
            'client_id'                 => 'nullable|exists:clients,id',
            'savings_account_id'        => 'required_if:payment_method,savings|nullable|exists:savings_accounts,id',
            'payment_source_account_id' => 'required_if:payment_method,cash|nullable|exists:accounts,id',
            'status'                    => 'required|in:active,inactive',
            'notes'                     => 'nullable|string',
        ]);

        if ($data['payment_method'] === 'savings') {
            $sa = SavingsAccount::where('id', $data['savings_account_id'])
                ->where('client_id', $data['client_id'] ?? null)->first();
            if (!$sa) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'savings_account_id' => 'The savings account does not belong to the selected client.',
                ]);
            }
            $data['payment_source_account_id'] = null;
        } else {
            $data['client_id']          = null;
            $data['savings_account_id'] = null;
        }

        return $data;
    }

    /** @return array{0: \Illuminate\Support\Collection, 1: \Illuminate\Support\Collection, 2: \Illuminate\Support\Collection} */
    private function formOptions(): array
    {
        $clients = Client::where('status', 'active')
            ->whereHas('savingsAccounts', fn($q) => $q->where('status', 'active'))
            ->orderBy('name')
            ->get();

        $savingsAccounts = SavingsAccount::with('product')
            ->where('status', 'active')
            ->get()
            ->groupBy('client_id');

        $paymentSourceAccounts = Account::where('is_payment_source', true)
            ->where('is_active', true)
            ->orderBy('account_code')
            ->get();

        return [$clients, $savingsAccounts, $paymentSourceAccounts];
    }

    private function generateEmployeeNumber(): string {
        $last = Employee::withTrashed()->count() + 1;
        return 'EMP-' . str_pad($last, 5, '0', STR_PAD_LEFT);
    }
}
