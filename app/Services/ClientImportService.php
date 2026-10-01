<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Client;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Bulk-creates clients from a CSV file sitting on disk (storage/app/imports/clients.csv —
 * see SettingsController::importClients()). Deliberately bypasses the full client-creation
 * validation (gender, DOB, next of kin, etc.) — legacy member lists only ever have a handful
 * of columns, so this only requires a name and fills in whatever else the file has.
 */
class ClientImportService
{
    private const COLUMN_ALIASES = [
        'client_number'     => ['reference number', 'client number', 'client_number', 'client id', 'client_id', 'reference', 'ref no', 'ref'],
        'name'              => ['name of client', 'client name', 'name', 'full name'],
        'phone'             => ['telephone number', 'telephone', 'phone number', 'phone', 'mobile'],
        'id_number'         => ['nin number', 'nin', 'id number', 'id_number', 'national id'],
        'email'             => ['email', 'email address'],
        'client_type'       => ['type', 'client type', 'client_type'],
        'status'            => ['status'],
        'branch'            => ['branch', 'branch name'],
        'loan_officer'      => ['loan officer', 'loan_officer', 'relationship manager', 'relationship_manager'],
        'joining_date'      => ['joining date', 'joining_date', 'date joined', 'registration date'],
    ];

    /**
     * Known misspellings/variants of a staff name in a source sheet, mapped to the name
     * as it actually appears in `users`. Add to this as legacy sheets turn up new variants -
     * far safer than fuzzy-matching, which could just as easily match the wrong person.
     */
    private const OFFICER_NAME_ALIASES = [
        'jurua alex' => 'juma alex',
    ];

    /** Sheet values that mean "no specific officer recorded", not a real name to match. */
    private const OFFICER_PLACEHOLDER_VALUES = ['loans officer', 'loan officer', 'n/a', 'none', '-'];

    /** @return array{created:int, skipped_duplicate_in_file:string[], skipped_existing:string[], flagged_phones:string[], unmatched_officers:string[], unmatched_branches:string[]} */
    public function importFromCsv(string $path, ?int $branchId = null): array
    {
        $handle = fopen($path, 'r');
        try {
            return $this->importFromHandle($handle, $branchId);
        } finally {
            fclose($handle);
        }
    }

    /**
     * Same as importFromCsv(), but for CSV text pasted directly into a form field rather
     * than a file on disk — avoids ever writing the (often sensitive) source file to the
     * server's filesystem or to git.
     */
    public function importFromCsvText(string $csvText, ?int $branchId = null): array
    {
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $csvText);
        rewind($handle);
        try {
            return $this->importFromHandle($handle, $branchId);
        } finally {
            fclose($handle);
        }
    }

    /** @return array{created:int, skipped_duplicate_in_file:string[], skipped_existing:string[], flagged_phones:string[], unmatched_officers:string[], unmatched_branches:string[]} */
    private function importFromHandle($handle, ?int $branchId = null): array
    {
        $header = array_map(fn ($h) => strtolower(trim((string) $h)), fgetcsv($handle) ?: []);

        $col = [];
        foreach (self::COLUMN_ALIASES as $field => $aliases) {
            foreach ($aliases as $alias) {
                $idx = array_search($alias, $header, true);
                if ($idx !== false) {
                    $col[$field] = $idx;
                    break;
                }
            }
        }

        if (!isset($col['name'])) {
            throw new \RuntimeException('Could not find a "Name" column in the file. Expected a header like "Name" or "Name of Client".');
        }

        // Officers/branches are looked up by name once per import, not once per row.
        $officersByName = User::whereDoesntHave('roles', fn ($q) => $q->whereIn('name', ['client', 'group_member', 'group_leader']))
            ->get(['id', 'name'])
            ->keyBy(fn ($u) => strtolower(trim($u->name)));
        $branchesByName = Branch::all(['id', 'name'])->keyBy(fn ($b) => strtolower(trim($b->name)));

        $created  = 0;
        $skippedDuplicateInFile = [];
        $skippedExisting        = [];
        $flaggedPhones          = [];
        $unmatchedOfficers      = [];
        $unmatchedBranches      = [];
        $seenNumbers            = [];
        $seenOfficerMisses      = [];
        $seenBranchMisses       = [];
        $rowNum                 = 1;

        DB::beginTransaction();
        try {
            while (($row = fgetcsv($handle)) !== false) {
                $rowNum++;
                if (!array_filter($row, fn ($v) => trim((string) $v) !== '')) {
                    continue; // blank row
                }

                $name = trim((string) ($row[$col['name']] ?? ''));
                if ($name === '') {
                    continue;
                }

                $clientNumber = isset($col['client_number']) ? trim((string) ($row[$col['client_number']] ?? '')) : '';
                $phone        = isset($col['phone']) ? trim((string) ($row[$col['phone']] ?? '')) : '';
                $idNumber     = isset($col['id_number']) ? trim((string) ($row[$col['id_number']] ?? '')) : '';
                $email        = isset($col['email']) ? trim((string) ($row[$col['email']] ?? '')) : '';
                $joiningDate  = isset($col['joining_date']) ? trim((string) ($row[$col['joining_date']] ?? '')) : '';

                $clientType = isset($col['client_type']) ? strtolower(trim((string) ($row[$col['client_type']] ?? ''))) : 'individual';
                $clientType = $clientType === 'group' ? 'group' : 'individual';

                $status = isset($col['status']) ? strtolower(trim((string) ($row[$col['status']] ?? ''))) : 'active';
                $status = in_array($status, ['active', 'inactive', 'blacklisted'], true) ? $status : 'active';

                if ($clientNumber !== '') {
                    if (isset($seenNumbers[$clientNumber])) {
                        $skippedDuplicateInFile[] = "Row {$rowNum}: {$name} ({$clientNumber}) — duplicate reference number in file";
                        continue;
                    }
                    $seenNumbers[$clientNumber] = true;

                    if (Client::where('client_number', $clientNumber)->exists()) {
                        $skippedExisting[] = "Row {$rowNum}: {$name} ({$clientNumber}) — client number already exists";
                        continue;
                    }
                }

                if ($phone !== '') {
                    $digitsOnly = preg_replace('/\D/', '', $phone);
                    if (str_contains($phone, '/') || strlen($digitsOnly) < 9 || strlen($digitsOnly) > 10) {
                        $flaggedPhones[] = "Row {$rowNum}: {$name} — phone \"{$phone}\" looks off, check manually";
                    }
                }

                // Loan Officer: exact match, then the known-alias list, else flagged for manual
                // assignment afterward (via the inline dropdown on the Clients list) rather than
                // guessing at who a near-miss name might mean.
                $relationshipManagerId = null;
                $officerRaw = isset($col['loan_officer']) ? trim((string) ($row[$col['loan_officer']] ?? '')) : '';
                if ($officerRaw !== '') {
                    $officerKey = strtolower($officerRaw);
                    if (!in_array($officerKey, self::OFFICER_PLACEHOLDER_VALUES, true)) {
                        $resolvedKey = self::OFFICER_NAME_ALIASES[$officerKey] ?? $officerKey;
                        if (isset($officersByName[$resolvedKey])) {
                            $relationshipManagerId = $officersByName[$resolvedKey]->id;
                        } elseif (!isset($seenOfficerMisses[$officerKey])) {
                            $seenOfficerMisses[$officerKey] = true;
                            $unmatchedOfficers[] = "\"{$officerRaw}\" — no matching user; left unassigned on affected rows";
                        }
                    }
                }

                // Branch: exact match by name, else falls back to $branchId (or null/unscoped)
                // rather than guessing.
                $resolvedBranchId = $branchId;
                $branchRaw = isset($col['branch']) ? trim((string) ($row[$col['branch']] ?? '')) : '';
                if ($branchRaw !== '') {
                    $branchKey = strtolower($branchRaw);
                    if (isset($branchesByName[$branchKey])) {
                        $resolvedBranchId = $branchesByName[$branchKey]->id;
                    } elseif (!isset($seenBranchMisses[$branchKey])) {
                        $seenBranchMisses[$branchKey] = true;
                        $unmatchedBranches[] = "\"{$branchRaw}\" — no matching branch; used the default instead";
                    }
                }

                [$firstName, $middleName, $lastName] = Client::splitName($name);

                Client::create([
                    'client_number'           => $clientNumber !== '' ? $clientNumber : $this->generateClientNumber(),
                    'client_type'             => $clientType,
                    'name'                    => $name,
                    'first_name'              => $firstName,
                    'middle_name'             => $middleName !== '' ? $middleName : null,
                    'last_name'               => $lastName,
                    'phone'                   => $phone !== '' ? $phone : null,
                    'email'                   => $email !== '' ? $email : null,
                    'id_number'               => $idNumber !== '' ? $idNumber : null,
                    'branch_id'               => $resolvedBranchId,
                    'relationship_manager_id' => $relationshipManagerId,
                    'status'                  => $status,
                    'joining_date'            => $joiningDate !== '' ? $joiningDate : null,
                    'created_by'              => auth()->id(),
                ]);

                $created++;
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return [
            'created'                   => $created,
            'skipped_duplicate_in_file' => $skippedDuplicateInFile,
            'skipped_existing'          => $skippedExisting,
            'flagged_phones'            => $flaggedPhones,
            'unmatched_officers'        => $unmatchedOfficers,
            'unmatched_branches'        => $unmatchedBranches,
        ];
    }

    private function generateClientNumber(): string
    {
        $prefix = \App\Models\SystemSetting::get('client_number_prefix', 'CLT-');
        $pad    = (int) \App\Models\SystemSetting::get('client_number_padding', 6);

        $max = \Illuminate\Support\Facades\DB::table('clients')
            ->where('client_number', 'like', $prefix . '%')
            ->max(\Illuminate\Support\Facades\DB::raw("CAST(SUBSTRING(client_number, " . (strlen($prefix) + 1) . ") AS UNSIGNED)"));

        return $prefix . str_pad((int) $max + 1, $pad, '0', STR_PAD_LEFT);
    }
}
