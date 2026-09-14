<?php

namespace App\Services;

use App\Models\Client;
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
        'client_number' => ['reference number', 'client number', 'client_number', 'reference', 'ref no', 'ref'],
        'name'          => ['name of client', 'client name', 'name', 'full name'],
        'phone'         => ['telephone number', 'telephone', 'phone number', 'phone', 'mobile'],
        'id_number'     => ['nin number', 'nin', 'id number', 'id_number', 'national id'],
    ];

    /** @return array{created:int, skipped_duplicate_in_file:string[], skipped_existing:string[], flagged_phones:string[]} */
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

    /** @return array{created:int, skipped_duplicate_in_file:string[], skipped_existing:string[], flagged_phones:string[]} */
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

        $created  = 0;
        $skippedDuplicateInFile = [];
        $skippedExisting        = [];
        $flaggedPhones          = [];
        $seenNumbers            = [];
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

                Client::create([
                    'client_number' => $clientNumber !== '' ? $clientNumber : $this->generateClientNumber(),
                    'client_type'   => 'individual',
                    'name'          => $name,
                    'phone'         => $phone !== '' ? $phone : null,
                    'id_number'     => $idNumber !== '' ? $idNumber : null,
                    'branch_id'     => $branchId,
                    'status'        => 'active',
                    'created_by'    => auth()->id(),
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
        ];
    }

    private function generateClientNumber(): string
    {
        $last = Client::withTrashed()->count() + 1;
        return 'CLT-' . str_pad($last, 6, '0', STR_PAD_LEFT);
    }
}
