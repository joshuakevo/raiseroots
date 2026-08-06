<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\BranchController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\MemberShareController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FixedDepositController;
use App\Http\Controllers\FixedDepositProductController;
use App\Http\Controllers\LoanController;
use App\Http\Controllers\LoanProductController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SavingsAccountController;
use App\Http\Controllers\SavingsProductController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\TellerController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\FinancialPeriodController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\GroupPortalController;
use App\Http\Controllers\ClientPortalController;
use Illuminate\Support\Facades\Route;

// ── Authentication (public) ───────────────────────────────────────
Route::get('login', [LoginController::class, 'showLogin'])->name('login');
Route::post('login', [LoginController::class, 'login']);
Route::post('logout', [LoginController::class, 'logout'])->name('logout');

// ── Password reset ────────────────────────────────────────────────
Route::get('password/reset', [ForgotPasswordController::class, 'showLinkRequestForm'])->name('password.request');
Route::post('password/email', [ForgotPasswordController::class, 'sendResetLinkEmail'])->name('password.email');
Route::get('password/reset/{token}', [ResetPasswordController::class, 'showResetForm'])->name('password.reset');
Route::post('password/reset', [ResetPasswordController::class, 'reset'])->name('password.update');

// ── All other routes require auth ─────────────────────────────────
Route::middleware('auth')->group(function () {

    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/choose-portal', fn() => view('auth.choose-portal'))->name('choose-portal');

    // ── Client portal ─────────────────────────────────────────────────
    Route::prefix('client-portal')->name('client-portal.')->middleware('role:client|group_member|group_leader')->group(function () {
        Route::get('/', [ClientPortalController::class, 'overview'])->name('dashboard');
        Route::get('/accounts', [ClientPortalController::class, 'accounts'])->name('accounts');
        Route::get('/activity', [ClientPortalController::class, 'activity'])->name('activity');
        Route::post('/switch/{client}', [ClientPortalController::class, 'switchAccount'])->name('switch');
        Route::get('/savings/{savingsAccount}', [ClientPortalController::class, 'savingsStatement'])->name('savings');
        Route::get('/savings/{savingsAccount}/pdf', [ClientPortalController::class, 'savingsStatementPdf'])->name('savings.pdf');
        Route::get('/loans/{loan}', [ClientPortalController::class, 'loanStatement'])->name('loan');
        Route::get('/loans/{loan}/pdf', [ClientPortalController::class, 'loanStatementPdf'])->name('loan.pdf');
        Route::get('/fixed-deposits/{fixedDeposit}', [ClientPortalController::class, 'fdStatement'])->name('fd');
        Route::get('/fixed-deposits/{fixedDeposit}/pdf', [ClientPortalController::class, 'fdStatementPdf'])->name('fd.pdf');
    });

    // ── Group portal (leader / member) ────────────────────────────────
    Route::prefix('group-portal')->name('group-portal.')->group(function () {
        Route::get('/', [GroupPortalController::class, 'leaderDashboard'])
            ->middleware('role:group_leader')
            ->name('leader');
        Route::get('/members', [GroupPortalController::class, 'leaderMembers'])
            ->middleware('role:group_leader')
            ->name('leader.members');
        Route::get('/activity', [GroupPortalController::class, 'leaderActivity'])
            ->middleware('role:group_leader')
            ->name('leader.activity');
        Route::get('/members/{member}/statement', [GroupPortalController::class, 'memberStatement'])
            ->middleware('role:group_leader')
            ->name('leader.member-statement');
        Route::get('/members/{member}/statement/pdf', [GroupPortalController::class, 'memberStatementPdf'])
            ->middleware('role:group_leader')
            ->name('leader.member-statement.pdf');
        Route::get('/me', [GroupPortalController::class, 'memberOverview'])
            ->middleware('role:group_member')
            ->name('member');
        Route::get('/me/statement', [GroupPortalController::class, 'memberDashboard'])
            ->middleware('role:group_member')
            ->name('member.statement');
        Route::post('/me/transfer-to-savings', [GroupPortalController::class, 'transferToSavings'])
            ->middleware('role:group_member')
            ->name('member.transfer-to-savings');
    });

    // ── Clients ───────────────────────────────────────────────────────
    Route::resource('clients', ClientController::class)->middleware('permission:view clients');
    Route::post('clients/{client}/invite', [ClientController::class, 'invite'])
        ->name('clients.invite')->middleware('permission:edit clients');
    Route::post('clients/{client}/invite-members', [ClientController::class, 'inviteGroupMembers'])
        ->name('clients.invite-members')->middleware('permission:edit clients');
    Route::post('clients/{client}/membership-payment', [MemberShareController::class, 'payMembership'])
        ->name('clients.membership-payment')->middleware('permission:manage shares');
    Route::post('clients/{client}/shares', [MemberShareController::class, 'addShare'])
        ->name('clients.shares.add')->middleware('permission:manage shares');
    Route::post('clients/{client}/shares/{share}/payment', [MemberShareController::class, 'payShare'])
        ->name('clients.shares.payment')->middleware('permission:manage shares');
    Route::get('shares', [MemberShareController::class, 'index'])
        ->name('shares.index')->middleware('permission:manage shares');
    Route::get('clients/{client}/shares/statement', [MemberShareController::class, 'statement'])
        ->name('clients.shares.statement')->middleware('permission:manage shares');
    Route::get('clients/{client}/shares/statement/pdf', [MemberShareController::class, 'statementPdf'])
        ->name('clients.shares.statement-pdf')->middleware('permission:manage shares');
    Route::post('clients/{client}/shares/{share}/revalue', [MemberShareController::class, 'revalue'])
        ->name('clients.shares.revalue')->middleware('permission:manage shares');
    Route::post('shares/revalue-all', [MemberShareController::class, 'revalueAll'])
        ->name('shares.revalue-all')->middleware('permission:manage shares');
    Route::post('clients/{client}/shares/{share}/liquidate', [MemberShareController::class, 'liquidate'])
        ->name('clients.shares.liquidate')->middleware('permission:manage shares');

    // ── Groups (admin) ────────────────────────────────────────────────
    Route::get('groups', [GroupController::class, 'index'])->name('groups.index')->middleware('permission:view groups');
    Route::get('groups/create', [GroupController::class, 'create'])->name('groups.create')->middleware('permission:manage groups');
    Route::post('groups', [GroupController::class, 'store'])->name('groups.store')->middleware('permission:manage groups');
    Route::get('groups/{group}', [GroupController::class, 'show'])->name('groups.show')->middleware('permission:view groups');
    Route::get('groups/{group}/edit', [GroupController::class, 'edit'])->name('groups.edit')->middleware('permission:manage groups');
    Route::put('groups/{group}', [GroupController::class, 'update'])->name('groups.update')->middleware('permission:manage groups');
    Route::post('groups/{group}/members', [GroupController::class, 'storeMember'])->name('groups.members.store')->middleware('permission:manage groups');
    Route::put('groups/{group}/members/{member}', [GroupController::class, 'updateMember'])->name('groups.members.update')->middleware('permission:manage groups');
    Route::delete('groups/{group}/members/{member}', [GroupController::class, 'destroyMember'])->name('groups.members.destroy')->middleware('permission:manage groups');
    Route::post('groups/{group}/members/{member}/send-reset', [GroupController::class, 'sendMemberReset'])->name('groups.members.send-reset')->middleware('permission:manage groups');
    Route::post('groups/{group}/members/{member}/transfer-to-savings', [GroupController::class, 'transferToSavings'])->name('groups.members.transfer-to-savings')->middleware('permission:manage groups');
    Route::get('groups/{group}/members/{member}/statement', [GroupController::class, 'memberStatement'])->name('groups.members.statement')->middleware('permission:manage groups');
    Route::get('groups/{group}/deposit', [GroupController::class, 'depositForm'])->name('groups.deposit')->middleware('permission:manage groups');
    Route::post('groups/{group}/deposit', [GroupController::class, 'deposit'])->name('groups.deposit.store')->middleware('permission:manage groups');
    Route::get('groups/{group}/withdraw', [GroupController::class, 'withdrawalForm'])->name('groups.withdraw')->middleware('permission:manage groups');
    Route::post('groups/{group}/withdraw', [GroupController::class, 'withdrawal'])->name('groups.withdraw.store')->middleware('permission:manage groups');
    Route::get('groups/{group}/interest', [GroupController::class, 'interestForm'])->name('groups.interest')->middleware('permission:manage groups');
    Route::post('groups/{group}/interest', [GroupController::class, 'interest'])->name('groups.interest.store')->middleware('permission:manage groups');
    Route::get('groups/{group}/contribute', [GroupController::class, 'contributeForm'])->name('groups.contribute')->middleware('permission:manage groups');
    Route::post('groups/{group}/contribute', [GroupController::class, 'contribute'])->name('groups.contribute.store')->middleware('permission:manage groups');
    Route::get('groups/{group}/pool-withdraw', [GroupController::class, 'poolWithdrawForm'])->name('groups.pool-withdraw')->middleware('permission:manage groups');
    Route::post('groups/{group}/pool-withdraw', [GroupController::class, 'poolWithdraw'])->name('groups.pool-withdraw.store')->middleware('permission:manage groups');
    Route::delete('groups/{group}', [GroupController::class, 'destroy'])->name('groups.destroy')->middleware('permission:manage groups');

    // ── Chart of Accounts ─────────────────────────────────────────────
    Route::resource('accounts', AccountController::class)
        ->except(['destroy'])
        ->middleware('permission:view accounts');

    // ── Journal Entries ───────────────────────────────────────────────
    // NOTE: specific routes (create) must come before wildcard ({transaction})
    Route::get('transactions/create', [TransactionController::class, 'create'])
        ->name('transactions.create')->middleware('permission:create transactions');
    Route::post('transactions', [TransactionController::class, 'store'])
        ->name('transactions.store')->middleware('permission:create transactions');
    Route::get('transactions', [TransactionController::class, 'index'])
        ->name('transactions.index')->middleware('permission:view transactions');
    Route::get('transactions/{transaction}', [TransactionController::class, 'show'])
        ->name('transactions.show')->middleware('permission:view transactions');
    Route::get('transactions/{transaction}/edit', [TransactionController::class, 'edit'])
        ->name('transactions.edit')->middleware('permission:create transactions');
    Route::put('transactions/{transaction}', [TransactionController::class, 'update'])
        ->name('transactions.update')->middleware('permission:create transactions');
    Route::post('transactions/{transaction}/reverse', [TransactionController::class, 'reverse'])
        ->name('transactions.reverse')->middleware('permission:reverse transactions');
    Route::delete('transactions/{transaction}', [TransactionController::class, 'destroy'])
        ->name('transactions.destroy')->middleware('role:super_admin');

    // ── Loan Products ─────────────────────────────────────────────────
    Route::get('loan-products/schedule-preview', [LoanProductController::class, 'schedulePreview'])
        ->name('loan-products.schedule-preview')->middleware('permission:view loan-products');
    Route::resource('loan-products', LoanProductController::class)
        ->except(['destroy'])
        ->middleware('permission:view loan-products');

    // ── Loans ─────────────────────────────────────────────────────────
    // NOTE: loans/create must come before loans/{loan}
    Route::get('loans/create', [LoanController::class, 'create'])
        ->name('loans.create')->middleware('permission:create loans');
    Route::post('loans', [LoanController::class, 'store'])
        ->name('loans.store')->middleware('permission:create loans');
    Route::get('loans', [LoanController::class, 'index'])
        ->name('loans.index')->middleware('permission:view loans');
    Route::get('loans/{loan}', [LoanController::class, 'show'])
        ->name('loans.show')->middleware('permission:view loans');
    Route::get('loans/{loan}/schedule', [LoanController::class, 'schedule'])
        ->name('loans.schedule')->middleware('permission:view loans');
    Route::post('loans/{loan}/disburse', [LoanController::class, 'disburse'])
        ->name('loans.disburse')->middleware('permission:disburse loans');
    Route::get('loans/{loan}/repay', [LoanController::class, 'repayForm'])
        ->name('loans.repay-form')->middleware('permission:repay loans');
    Route::post('loans/{loan}/repay', [LoanController::class, 'repay'])
        ->name('loans.repay')->middleware('permission:repay loans');
    Route::post('loans/{loan}/guarantors', [LoanController::class, 'storeGuarantor'])
        ->name('loans.guarantors.store')->middleware('permission:create loans');
    Route::delete('loans/{loan}/guarantors/{guarantor}', [LoanController::class, 'destroyGuarantor'])
        ->name('loans.guarantors.destroy')->middleware('permission:create loans');
    Route::get('loans/{loan}/statement', [LoanController::class, 'statement'])
        ->name('loans.statement')->middleware('permission:view loans');
    Route::get('loans/{loan}/statement/pdf', [LoanController::class, 'statementPdf'])
        ->name('loans.statement-pdf')->middleware('permission:view loans');
    Route::get('loans/{loan}/schedule/pdf', [LoanController::class, 'schedulePdf'])
        ->name('loans.schedule-pdf')->middleware('permission:view loans');
    Route::delete('loans/{loan}', [LoanController::class, 'destroy'])
        ->name('loans.destroy')->middleware('permission:create loans');

    // ── Savings Products ──────────────────────────────────────────────
    Route::resource('savings-products', SavingsProductController::class)
        ->except(['destroy'])
        ->middleware('permission:view savings-products');

    // ── Savings Accounts ──────────────────────────────────────────────
    // NOTE: savings/create must come before savings/{saving}
    Route::get('savings/create', [SavingsAccountController::class, 'create'])
        ->name('savings.create')->middleware('permission:create savings');
    Route::post('savings', [SavingsAccountController::class, 'store'])
        ->name('savings.store')->middleware('permission:create savings');
    Route::get('savings', [SavingsAccountController::class, 'index'])
        ->name('savings.index')->middleware('permission:view savings');
    Route::get('savings/{saving}', [SavingsAccountController::class, 'show'])
        ->name('savings.show')->middleware('permission:view savings');
    Route::get('savings/{saving}/deposit', [SavingsAccountController::class, 'depositForm'])
        ->name('savings.deposit-form')->middleware('permission:deposit savings');
    Route::post('savings/{saving}/deposit', [SavingsAccountController::class, 'deposit'])
        ->name('savings.deposit')->middleware('permission:deposit savings');
    Route::get('savings/{saving}/withdraw', [SavingsAccountController::class, 'withdrawForm'])
        ->name('savings.withdraw-form')->middleware('permission:withdraw savings');
    Route::post('savings/{saving}/withdraw', [SavingsAccountController::class, 'withdraw'])
        ->name('savings.withdraw')->middleware('permission:withdraw savings');
    Route::get('savings/{saving}/transfer', [SavingsAccountController::class, 'transferForm'])
        ->name('savings.transfer-form')->middleware('permission:transfer savings');
    Route::post('savings/{saving}/transfer', [SavingsAccountController::class, 'transfer'])
        ->name('savings.transfer')->middleware('permission:transfer savings');
    Route::get('savings/{saving}/statement', [SavingsAccountController::class, 'statement'])
        ->name('savings.statement')->middleware('permission:view savings');
    Route::get('savings/{saving}/statement/pdf', [SavingsAccountController::class, 'statementPdf'])
        ->name('savings.statement-pdf')->middleware('permission:view savings');
    Route::post('savings/{saving}/post-interest', [SavingsAccountController::class, 'postInterest'])
        ->name('savings.post-interest')->middleware('permission:deposit savings');
    Route::post('savings/post-interest-bulk', [SavingsAccountController::class, 'postInterestBulk'])
        ->name('savings.post-interest-bulk')->middleware('permission:deposit savings');
    Route::post('savings/reverse-interest-bulk', [SavingsAccountController::class, 'reverseInterestBulk'])
        ->name('savings.reverse-interest-bulk')->middleware('permission:deposit savings');
    Route::post('savings/{saving}/dormant', [SavingsAccountController::class, 'markDormant'])
        ->name('savings.dormant')->middleware('permission:deposit savings');
    Route::post('savings/{saving}/close', [SavingsAccountController::class, 'close'])
        ->name('savings.close')->middleware('permission:deposit savings');
    Route::post('savings/{saving}/reactivate', [SavingsAccountController::class, 'reactivate'])
        ->name('savings.reactivate')->middleware('permission:deposit savings');
    Route::delete('savings/{saving}', [SavingsAccountController::class, 'destroy'])
        ->name('savings.destroy')->middleware('permission:deposit savings');

    // ── Fixed Deposit Products ────────────────────────────────────────
    Route::resource('fd-products', FixedDepositProductController::class)
        ->except(['destroy'])
        ->middleware('permission:view fd-products');

    // ── Fixed Deposits ────────────────────────────────────────────────
    // NOTE: fixed-deposits/create must come before fixed-deposits/{fixedDeposit}
    Route::get('fixed-deposits/create', [FixedDepositController::class, 'create'])
        ->name('fixed-deposits.create')->middleware('permission:create fixed-deposits');
    Route::post('fixed-deposits', [FixedDepositController::class, 'store'])
        ->name('fixed-deposits.store')->middleware('permission:create fixed-deposits');
    Route::get('fixed-deposits', [FixedDepositController::class, 'index'])
        ->name('fixed-deposits.index')->middleware('permission:view fixed-deposits');
    Route::get('fixed-deposits/{fixedDeposit}', [FixedDepositController::class, 'show'])
        ->name('fixed-deposits.show')->middleware('permission:view fixed-deposits');
    Route::get('fixed-deposits/{fixedDeposit}/mature', [FixedDepositController::class, 'matureForm'])
        ->name('fixed-deposits.mature-form')->middleware('permission:mature fixed-deposits');
    Route::post('fixed-deposits/{fixedDeposit}/mature', [FixedDepositController::class, 'mature'])
        ->name('fixed-deposits.mature')->middleware('permission:mature fixed-deposits');
    Route::get('fixed-deposits/{fixedDeposit}/break', [FixedDepositController::class, 'breakForm'])
        ->name('fixed-deposits.break-form')->middleware('permission:mature fixed-deposits');
    Route::post('fixed-deposits/{fixedDeposit}/break', [FixedDepositController::class, 'breakDeposit'])
        ->name('fixed-deposits.break')->middleware('permission:mature fixed-deposits');
    Route::get('fixed-deposits/{fixedDeposit}/certificate', [FixedDepositController::class, 'certificate'])
        ->name('fixed-deposits.certificate')->middleware('permission:view fixed-deposits');

    // ── Reports ───────────────────────────────────────────────────────
    Route::prefix('reports')->name('reports.')->middleware('permission:view reports')->group(function () {
        Route::get('/', [ReportController::class, 'index'])->name('index');
        Route::get('trial-balance', [ReportController::class, 'trialBalance'])->name('trial-balance');
        Route::get('income-statement', [ReportController::class, 'incomeStatement'])->name('income-statement');
        Route::get('balance-sheet', [ReportController::class, 'balanceSheet'])->name('balance-sheet');
        Route::get('general-ledger', [ReportController::class, 'generalLedger'])->name('general-ledger');
        Route::get('loan-portfolio', [ReportController::class, 'loanPortfolio'])->name('loan-portfolio');
        Route::get('loan-aging', [ReportController::class, 'loanAging'])->name('loan-aging');
        Route::get('repayment-schedule', [ReportController::class, 'repaymentSchedule'])->name('repayment-schedule');
        Route::get('interest-income', [ReportController::class, 'interestIncome'])->name('interest-income');
        Route::get('savings-balances', [ReportController::class, 'savingsBalances'])->name('savings-balances');
        Route::get('fd-maturity', [ReportController::class, 'fixedDepositMaturity'])->name('fd-maturity');
        Route::get('member-summary', [ReportController::class, 'memberSummary'])->name('member-summary');
    });

    // ── Quick Teller ──────────────────────────────────────────────────
    Route::middleware('permission:use teller')->group(function () {
        Route::get('teller', [TellerController::class, 'index'])->name('teller.index');
        Route::get('teller/search-client', [TellerController::class, 'searchClient'])->name('teller.search-client');
        Route::post('teller/deposit', [TellerController::class, 'deposit'])->name('teller.deposit');
        Route::post('teller/withdraw', [TellerController::class, 'withdraw'])->name('teller.withdraw');
    });

    // ── Branches ──────────────────────────────────────────────────────
    Route::resource('branches', BranchController::class)
        ->except(['destroy'])
        ->middleware('permission:manage branches');

    // ── User Management ───────────────────────────────────────────────
    Route::middleware('permission:manage users')->group(function () {
        Route::resource('users', UserController::class)->except(['destroy']);
        Route::post('users/{user}/toggle-status', [UserController::class, 'toggleStatus'])->name('users.toggle-status');
        Route::post('users/{user}/assign-role', [UserController::class, 'assignRole'])->name('users.assign-role');
        Route::post('users/{user}/permissions', [App\Http\Controllers\RoleController::class, 'updateUserPermissions'])->name('users.permissions');
    });

    // ── Roles & Permissions ────────────────────────────────────────────
    Route::middleware('role:super_admin')->group(function () {
        Route::get('roles', [App\Http\Controllers\RoleController::class, 'index'])->name('roles.index');
        Route::post('roles', [App\Http\Controllers\RoleController::class, 'store'])->name('roles.store');
        Route::get('roles/{role}/edit', [App\Http\Controllers\RoleController::class, 'edit'])->name('roles.edit');
        Route::put('roles/{role}', [App\Http\Controllers\RoleController::class, 'update'])->name('roles.update');
        Route::delete('roles/{role}', [App\Http\Controllers\RoleController::class, 'destroy'])->name('roles.destroy');
    });

    // ── System Settings ───────────────────────────────────────────────
    Route::middleware('permission:manage settings')->group(function () {
        Route::get('settings', [SettingsController::class, 'index'])->name('settings.index');
        Route::post('settings', [SettingsController::class, 'update'])->name('settings.update');
        Route::post('settings/logo', [SettingsController::class, 'uploadLogo'])->name('settings.logo');
        Route::delete('settings/logo', [SettingsController::class, 'removeLogo'])->name('settings.logo.remove');
        Route::post('settings/reconcile', [SettingsController::class, 'reconcile'])->name('settings.reconcile');
        Route::post('settings/migrate', [SettingsController::class, 'runMigrations'])->name('settings.migrate');
        Route::post('settings/storage-diagnostics', [SettingsController::class, 'storageDiagnostics'])->name('settings.storage-diagnostics');
        Route::post('settings/sync-legacy-uploads', [SettingsController::class, 'syncLegacyUploads'])->name('settings.sync-legacy-uploads');
    });

    // ── Audit Log ─────────────────────────────────────────────────────
    Route::middleware('permission:manage settings')->group(function () {
        Route::get('audit', [AuditLogController::class, 'index'])->name('audit.index');

        // ── Financial Periods ─────────────────────────────────────────────
        Route::get('periods', [FinancialPeriodController::class, 'index'])->name('periods.index');
        Route::post('periods/{period}/close', [FinancialPeriodController::class, 'closeMonth'])->name('periods.close');
        Route::post('periods/{period}/reopen', [FinancialPeriodController::class, 'reopenMonth'])->name('periods.reopen');
        Route::post('periods/year/{year}/close', [FinancialPeriodController::class, 'closeYear'])->name('periods.year.close');
        Route::post('periods/year/{year}/reopen', [FinancialPeriodController::class, 'reopenYear'])->name('periods.year.reopen');
    });

    // ── User Manual PDF ──────────────────────────────────────────────
    Route::get('manual/pdf', function () {
        $orgName = \App\Models\SystemSetting::get('org_name', 'ElTech Finance');
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.user-manual', compact('orgName'))
            ->setPaper('a4', 'portrait');
        return $pdf->download('ElTech-Finance-User-Manual.pdf');
    })->name('manual.pdf')->middleware('role:super_admin|admin');

    // ── Database Backup ───────────────────────────────────────────────
    Route::middleware('permission:manage backup')->group(function () {
        Route::get('backup', [BackupController::class, 'index'])->name('backup.index');
        Route::post('backup/create', [BackupController::class, 'create'])->name('backup.create');
        Route::get('backup/download/{filename}', [BackupController::class, 'download'])->name('backup.download');
        Route::delete('backup/{filename}', [BackupController::class, 'destroy'])->name('backup.destroy');
    });

    // ── Employees (static paths before {employee}) ────────────────────
    Route::middleware('permission:view employees')->group(function () {
        Route::get('employees', [EmployeeController::class, 'index'])->name('employees.index');
    });
    Route::middleware('permission:create employees')->group(function () {
        Route::get('employees/create', [EmployeeController::class, 'create'])->name('employees.create');
        Route::post('employees', [EmployeeController::class, 'store'])->name('employees.store');
    });
    Route::middleware('permission:view employees')->group(function () {
        Route::get('employees/{employee}', [EmployeeController::class, 'show'])->name('employees.show');
    });
    Route::middleware('permission:edit employees')->group(function () {
        Route::get('employees/{employee}/edit', [EmployeeController::class, 'edit'])->name('employees.edit');
        Route::put('employees/{employee}', [EmployeeController::class, 'update'])->name('employees.update');
    });

    // ── Payroll (payroll/create before payroll/{payroll}) ─────────────
    Route::middleware('permission:view payroll')->group(function () {
        Route::get('payroll', [PayrollController::class, 'index'])->name('payroll.index');
    });
    Route::middleware('permission:create payroll')->group(function () {
        Route::get('payroll/create', [PayrollController::class, 'create'])->name('payroll.create');
        Route::post('payroll', [PayrollController::class, 'store'])->name('payroll.store');
    });
    Route::middleware('permission:view payroll')->group(function () {
        Route::get('payroll/{payroll}', [PayrollController::class, 'show'])->name('payroll.show');
    });
    Route::post('payroll/{payroll}/process', [PayrollController::class, 'process'])
        ->name('payroll.process')->middleware('permission:process payroll');
    Route::delete('payroll/{payroll}', [PayrollController::class, 'destroy'])
        ->name('payroll.destroy')->middleware('permission:delete payroll');

}); // end auth
