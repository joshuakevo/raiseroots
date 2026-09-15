<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Client;
use App\Models\LoanGuarantor;
use App\Models\LoanProduct;
use Illuminate\Support\Facades\DB;

/**
 * One-time bulk import of historical loan disbursements from a spreadsheet ledger
 * (storage/app/imports/loan-disbursements.csv — see SettingsController::importLoanDisbursements()).
 * Reuses LoanService/AccountingService for every posting so disbursements, schedules,
 * and repayments go through the same double-entry/sub-ledger rules as the rest of the
 * app — nothing here hand-rolls GL entries.
 *
 * Admin cost and processing fee are posted as their own standalone fee-income
 * transactions (client pays them separately, not folded into the loan's own
 * principal/interest balance) — per explicit instruction, not LoanService's
 * built-in loan-product fee mechanism.
 *
 * Expected CSV columns: date, due_date, client_number, principal, interest,
 * admin_cost, processing_fee, guarantor_name, guarantor_phone, status.
 * Extra columns (sheet, sheet_fnumber, sheet_name, phone, loan_officer, match_type)
 * are ignored if present.
 */
class LoanImportService
{
    private const LOAN_PRODUCT_NAME       = 'EMERGENCY LOAN';
    private const CASH_ACCOUNT_CODE       = '1001'; // Cash At Hand
    private const ADMIN_FEE_ACCOUNT_CODE  = '4009'; // Loan Administrative Fee
    private const PROCESSING_FEE_ACCOUNT_CODE = '4005'; // Processing Fees

    public function __construct(
        protected LoanService $loanService,
        protected AccountingService $accounting,
    ) {}

    /** @return array{created:int, repaid:int, guarantors:int, fees_posted:int, skipped:string[], errors:string[]} */
    public function importFromCsv(string $path): array
    {
        $product = LoanProduct::where('name', self::LOAN_PRODUCT_NAME)->first();
        if (!$product) {
            throw new \RuntimeException('Loan product "' . self::LOAN_PRODUCT_NAME . '" not found — create it first.');
        }

        $cashAccount = Account::where('account_code', self::CASH_ACCOUNT_CODE)->first();
        $adminFeeAccount = Account::where('account_code', self::ADMIN_FEE_ACCOUNT_CODE)->first();
        $processingFeeAccount = Account::where('account_code', self::PROCESSING_FEE_ACCOUNT_CODE)->first();
        if (!$cashAccount || !$adminFeeAccount || !$processingFeeAccount) {
            throw new \RuntimeException('One or more required GL accounts (1001, 4009, 4005) are missing.');
        }

        $handle = fopen($path, 'r');
        $header = array_map(fn ($h) => strtolower(trim((string) $h)), fgetcsv($handle) ?: []);

        $created = 0;
        $repaid = 0;
        $guarantors = 0;
        $feesPosted = 0;
        $skipped = [];
        $errors = [];
        $rowNum = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $rowNum++;
            if (!array_filter($row, fn ($v) => trim((string) $v) !== '')) {
                continue; // blank row
            }

            $data = array_combine($header, array_pad($row, count($header), null));
            $clientNumber = trim((string) ($data['client_number'] ?? ''));

            if ($clientNumber === '') {
                $skipped[] = "Row {$rowNum}: {$data['sheet_name']} — no client_number, not imported";
                continue;
            }

            try {
                DB::transaction(function () use (
                    $data, $product, $cashAccount, $adminFeeAccount, $processingFeeAccount,
                    &$created, &$repaid, &$guarantors, &$feesPosted
                ) {
                    $client = Client::where('client_number', trim($data['client_number']))->firstOrFail();

                    $loan = $this->loanService->createLoan([
                        'client_id'       => $client->id,
                        'loan_product_id' => $product->id,
                        'principal'       => (float) $data['principal'],
                    ]);

                    $loan = $this->loanService->disburseLoan($loan, $data['date'], [
                        'application_fee_amount' => 0,
                        'management_fee_rate'    => 0,
                        'insurance_fee_rate'     => 0,
                    ]);
                    $created++;

                    if (!empty($data['guarantor_name'])) {
                        LoanGuarantor::create([
                            'loan_id' => $loan->id,
                            'name'    => trim($data['guarantor_name']),
                            'phone'   => !empty($data['guarantor_phone']) ? trim($data['guarantor_phone']) : null,
                        ]);
                        $guarantors++;
                    }

                    $adminCost = (float) ($data['admin_cost'] ?? 0);
                    if ($adminCost > 0.01) {
                        $this->accounting->post(
                            $data['date'],
                            "Admin cost - {$loan->loan_number}",
                            [
                                ['account_id' => $cashAccount->id, 'debit' => $adminCost, 'credit' => 0, 'client_id' => $client->id],
                                ['account_id' => $adminFeeAccount->id, 'debit' => 0, 'credit' => $adminCost],
                            ],
                            'loan',
                            $loan->id
                        );
                        $feesPosted++;
                    }

                    $processingFee = (float) ($data['processing_fee'] ?? 0);
                    if ($processingFee > 0.01) {
                        $this->accounting->post(
                            $data['date'],
                            "Processing fee - {$loan->loan_number}",
                            [
                                ['account_id' => $cashAccount->id, 'debit' => $processingFee, 'credit' => 0, 'client_id' => $client->id],
                                ['account_id' => $processingFeeAccount->id, 'debit' => 0, 'credit' => $processingFee],
                            ],
                            'loan',
                            $loan->id
                        );
                        $feesPosted++;
                    }

                    $status = strtoupper(trim((string) ($data['status'] ?? '')));
                    if (in_array($status, ['COMPLETED', 'PAID'], true)) {
                        $amount = round($loan->outstanding_principal + $loan->outstanding_interest, 2);
                        if ($amount > 0.01) {
                            $this->loanService->processRepayment($loan, [
                                'amount'                    => $amount,
                                'payment_date'               => $data['due_date'],
                                'payment_source_account_id'  => $cashAccount->id,
                                'payment_method'             => 'direct',
                            ]);
                            $repaid++;
                        }
                    }
                });
            } catch (\Throwable $e) {
                $errors[] = "Row {$rowNum} ({$clientNumber} / " . ($data['sheet_name'] ?? '?') . "): " . $e->getMessage();
            }
        }
        fclose($handle);

        return compact('created', 'repaid', 'guarantors', 'feesPosted', 'skipped', 'errors');
    }
}
