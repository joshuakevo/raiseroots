<?php

namespace App\Http\Controllers;

use App\Models\LoanCollateralCategory;
use App\Models\SystemSetting;
use App\Services\SmsSubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class SettingsController extends Controller
{
    public function index()
    {
        $settings = SystemSetting::whereNotIn('key', ['org_logo', 'sms_trial_used_count'])
            ->orderBy('group')->orderBy('label')->get()->groupBy('group');

        // Guarded: this table only exists after "Run Migrations" is clicked below, and
        // that button lives on this same page — querying it unconditionally would 500
        // the whole Settings page on any deploy where migrations haven't run yet.
        $collateralCategoriesReady = Schema::hasTable('loan_collateral_categories');
        $collateralCategories = $collateralCategoriesReady
            ? LoanCollateralCategory::orderBy('label')->get()
            : collect();

        $relationshipManagers = \App\Models\User::loanOfficers()->orderBy('name')->get();

        return view('settings.index', compact('settings', 'collateralCategoriesReady', 'collateralCategories', 'relationshipManagers'));
    }

    public function update(Request $request)
    {
        $submitted = $request->input('settings', []);

        // For boolean settings, unchecked checkboxes are absent from the request.
        // Explicitly set them to 0 when missing.
        $booleanKeys = SystemSetting::where('type', 'boolean')->pluck('key');
        foreach ($booleanKeys as $key) {
            SystemSetting::set($key, isset($submitted[$key]) ? 1 : 0);
        }

        foreach ($submitted as $key => $value) {
            SystemSetting::set($key, $value);
        }

        return back()->with('success', 'Settings saved successfully.');
    }

    public function uploadLogo(Request $request)
    {
        $request->validate([
            'logo' => 'required|image|mimes:png,jpg,jpeg,svg,webp|max:2048',
        ]);

        $logosDir = public_path('logos');
        if (!is_dir($logosDir)) {
            mkdir($logosDir, 0755, true);
        }

        // Delete old logo if exists
        $existing = SystemSetting::get('org_logo');
        if ($existing && file_exists(public_path($existing))) {
            @unlink(public_path($existing));
        }

        $file = $request->file('logo');
        $filename = 'logos/' . uniqid('logo_') . '.' . $file->getClientOriginalExtension();
        $file->move($logosDir, basename($filename));

        SystemSetting::set('org_logo', $filename);

        return back()->with('success', 'Organisation logo updated successfully.');
    }

    public function removeLogo()
    {
        $existing = SystemSetting::get('org_logo');
        if ($existing && file_exists(public_path($existing))) {
            @unlink(public_path($existing));
        }

        SystemSetting::set('org_logo', '');

        return back()->with('success', 'Logo removed.');
    }

    /**
     * Runs pending database migrations from the browser - this host has no
     * shell/SSH access and the cPanel Git deploy button is unavailable, so
     * there's no other way to apply schema changes after a deploy.
     */
    public function runMigrations()
    {
        Artisan::call('migrate', ['--force' => true]);
        $output = trim(Artisan::output());

        return back()->with('migrateOutput', $output);
    }

    /**
     * Re-runs the roles & permissions seeder - same reasoning as
     * runMigrations(): no shell access to run artisan db:seed directly.
     * Uses firstOrCreate/syncPermissions throughout, so safe to re-run;
     * it only adds new permissions/roles or updates a role's permission
     * set, never deletes users' role assignments.
     */
    public function runRolesSeeder()
    {
        Artisan::call('db:seed', [
            '--class' => \Database\Seeders\RolesAndPermissionsSeeder::class,
            '--force' => true,
        ]);
        $output = trim(Artisan::output());

        return back()->with('seederOutput', $output ?: 'Roles & permissions seeder ran successfully.');
    }

    /**
     * Clears cached config/routes/views — this host has no shell access, so if a
     * previous deploy ever ran `config:cache`, editing .env afterwards has no effect
     * until this runs. Safe to run any time.
     */
    public function clearCache()
    {
        Artisan::call('optimize:clear');
        $output = trim(Artisan::output());

        return back()->with('cacheOutput', $output ?: 'Cache cleared successfully.');
    }

    public function storageDiagnostics()
    {
        Artisan::call('storage:link', ['--force' => true]);
        $linkOutput = trim(Artisan::output());

        $publicDir = public_path();
        $storagePublic = storage_path('app/public');
        $storageClients = $storagePublic . '/clients';
        $symlinkPath = $publicDir . '/storage';
        $htaccess = $publicDir . '/.htaccess';

        $fileCount = 0;
        $sample = [];
        if (is_dir($storageClients)) {
            $files = array_values(array_diff(scandir($storageClients) ?: [], ['.', '..']));
            $fileCount = count($files);
            $sample = array_slice($files, 0, 5);
        }

        $importsDir = storage_path('app/imports');
        $importsListing = '(directory does not exist)';
        if (is_dir($importsDir)) {
            $importsFiles = array_values(array_diff(scandir($importsDir) ?: [], ['.', '..']));
            $importsListing = $importsFiles
                ? implode(', ', array_map(fn ($f) => $f . ' (' . number_format(filesize($importsDir . '/' . $f)) . ' bytes)', $importsFiles))
                : '(empty)';
        }

        $report = [
            'Server date/time (app timezone)'      => now()->format('Y-m-d H:i:s T'),
            'Server date/time (UTC)'                => now('UTC')->format('Y-m-d H:i:s') . ' UTC',
            'PHP version'                          => PHP_VERSION,
            'PHP max_execution_time'               => ini_get('max_execution_time') . 's (0 = unlimited)',
            'open_basedir'                         => ini_get('open_basedir') ?: '(not set)',
            'Server software'                      => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown',
            'App root'                              => base_path(),
            'storage/app/public exists'            => is_dir($storagePublic) ? 'YES' : 'NO',
            'storage/app/public/clients exists'    => is_dir($storageClients) ? 'YES' : 'NO',
            'clients/ file count'                  => $fileCount,
            'clients/ sample files'                => $sample ? implode(', ', $sample) : '(none)',
            'public/storage is_link()'             => is_link($symlinkPath) ? 'YES' : 'NO',
            'public/storage readlink()'            => is_link($symlinkPath) ? readlink($symlinkPath) : 'n/a',
            'public/storage is_dir() (resolves?)'  => is_dir($symlinkPath) ? 'YES' : 'NO',
            '.htaccess has storage rewrite rule'   => (file_exists($htaccess) && str_contains(file_get_contents($htaccess), 'storage/app/public')) ? 'YES' : 'NO',
            'storage:link command output'          => $linkOutput,
            'storage/app/imports/ contents'        => $importsListing,
            'transactions.branch_id distribution'  => \App\Models\Transaction::withoutGlobalScopes()
                ->selectRaw('branch_id, COUNT(*) as cnt')
                ->groupBy('branch_id')
                ->get()
                ->map(fn ($row) => ($row->branch_id === null ? 'NULL' : $row->branch_id) . '=' . $row->cnt)
                ->implode(', '),
        ];

        return back()->with('storageReport', $report);
    }

    /**
     * One-time migration helper for the raiseroots_clone -> raiseroots_clone_update
     * cutover, and for the move of client photos off the storage disk (blocked by
     * this host's symlink restrictions) onto public/clients directly. Pulls from
     * both the old live folder and this app's own now-obsolete storage/app/public
     * path, landing everything in public/clients and public/logos. Skips files
     * that already exist at the destination, so it's safe to run more than once.
     * Remove this once the migration is confirmed complete on all clients/records.
     */
    public function syncLegacyUploads()
    {
        $sourceRoot = '/home/eltexokn/public_html/raiseroots_clone';
        $targets = [
            $sourceRoot . '/storage/app/public/clients' => public_path('uploads/clients'),
            $sourceRoot . '/public/clients'              => public_path('uploads/clients'),
            storage_path('app/public/clients')           => public_path('uploads/clients'),
            public_path('clients')                       => public_path('uploads/clients'),
            $sourceRoot . '/public/logos'                 => public_path('logos'),
        ];

        $copied = 0;
        $skipped = 0;
        $errors = [];

        foreach ($targets as $source => $dest) {
            if (!is_dir($source)) {
                $errors[] = "Source not found: {$source}";
                continue;
            }
            if (!is_dir($dest)) {
                mkdir($dest, 0755, true);
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iterator as $item) {
                $relative = substr($item->getPathname(), strlen($source) + 1);
                $destPath = $dest . '/' . $relative;

                if ($item->isDir()) {
                    if (!is_dir($destPath)) {
                        mkdir($destPath, 0755, true);
                    }
                    continue;
                }

                if (file_exists($destPath)) {
                    $skipped++;
                    continue;
                }

                if (copy($item->getPathname(), $destPath)) {
                    $copied++;
                } else {
                    $errors[] = "Failed to copy: {$relative}";
                }
            }
        }

        // The old public/clients directory collides with the /clients route
        // (LiteSpeed 403s instead of routing to Laravel when it exists), so
        // everything must be moved out of it and the directory itself removed.
        $removedCollision = false;
        $collisionDir = public_path('clients');
        if (is_dir($collisionDir)) {
            $removedCollision = $this->removeDirRecursive($collisionDir);
        }

        $msg = "Sync complete. Copied {$copied} missing file(s), skipped {$skipped} already present.";
        if (is_dir($collisionDir)) {
            $msg .= $removedCollision
                ? ' Removed the colliding public/clients directory.'
                : ' WARNING: could not remove public/clients - it still collides with the /clients route, delete it manually via File Manager.';
        }
        if ($errors) {
            $msg .= ' Issues: ' . implode(' | ', array_slice($errors, 0, 10));
        }

        return back()->with('success', $msg);
    }

    private function removeDirRecursive(string $dir): bool
    {
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            if ($item->isDir()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }

        return @rmdir($dir);
    }

    public function reconcile()
    {
        Artisan::call('eltech:reconcile');
        $output = Artisan::output();

        // Count fixed vs ok from output
        preg_match('/Fixed:\s*(\d+)/', $output, $fixedMatch);
        preg_match('/Already correct:\s*(\d+)/', $output, $okMatch);
        $fixed = $fixedMatch[1] ?? '?';
        $ok    = $okMatch[1] ?? '?';

        $msg = "Reconciliation complete. Fixed: {$fixed} field(s). Already correct: {$ok}.";
        if ($fixed > 0) {
            // Include details of what was fixed
            $lines = collect(explode("\n", $output))
                ->filter(fn($l) => str_contains($l, '['))
                ->map(fn($l) => trim(strip_tags($l)))
                ->filter()
                ->implode(' | ');
            if ($lines) {
                $msg .= " Details: {$lines}";
            }
        }

        return back()->with($fixed > 0 ? 'success' : 'success', $msg);
    }

    /**
     * Reports which MarzSMS/MarzPay env vars are actually loaded (present/blank only,
     * never the values) — the fastest way to tell "not configured" apart from "configured
     * but rejected by the gateway" without shell/log access.
     */
    public function smsConfigCheck()
    {
        $keys = [
            'MARZSMS_API_KEY'        => config('services.marzsms.api_key'),
            'MARZSMS_SECRET'         => config('services.marzsms.secret'),
            'MARZSMS_SENDER_ID'      => config('services.marzsms.sender_id'),
            'MARZPAY_API_KEY'        => config('services.marzpay.api_key'),
            'MARZPAY_API_SECRET'     => config('services.marzpay.api_secret'),
            'MARZPAY_WEBHOOK_SECRET' => config('services.marzpay.webhook_secret'),
        ];

        $report = collect($keys)->mapWithKeys(fn ($value, $key) => [$key => $value ? 'SET' : 'MISSING'])->all();

        return back()->with('smsConfigReport', $report);
    }

    /**
     * Times a plain GET to the MarzPay base URL (no payment side effects) to see how
     * long outbound requests to it actually take from this host, and whether they
     * complete at all before PHP's max_execution_time would kill the real request.
     */
    public function testMarzPayConnectivity()
    {
        $baseUrl = config('services.marzpay.base_url');
        $start   = microtime(true);

        // MarzPay whitelists by the outbound IP it actually sees on its end — which,
        // on shared hosting behind NAT, can differ from the site's own/inbound IP.
        // Ask a public echo service from this same PHP process so it's the exact
        // address to hand MarzPay, not a guess.
        $outboundIp = 'unknown (lookup failed)';
        try {
            $ipResponse = Http::timeout(10)->get('https://api.ipify.org?format=json');
            $outboundIp = $ipResponse->json('ip') ?? $outboundIp;
        } catch (\Throwable $e) {
            // Non-fatal — the MarzPay check below still runs regardless.
        }

        try {
            $response  = Http::timeout(25)->get($baseUrl);
            $elapsedMs = round((microtime(true) - $start) * 1000);
            $report = [
                'Outbound IP (whitelist this with MarzPay)' => $outboundIp,
                'Target'                 => $baseUrl,
                'Result'                 => "Reached — HTTP {$response->status()}",
                'Round-trip time'        => "{$elapsedMs} ms",
                'PHP max_execution_time' => ini_get('max_execution_time') . 's (0 = unlimited)',
            ];
        } catch (\Throwable $e) {
            $elapsedMs = round((microtime(true) - $start) * 1000);
            $report = [
                'Outbound IP (whitelist this with MarzPay)' => $outboundIp,
                'Target'                 => $baseUrl,
                'Result'                 => 'FAILED: ' . $e->getMessage(),
                'Time before failure'    => "{$elapsedMs} ms",
                'PHP max_execution_time' => ini_get('max_execution_time') . 's (0 = unlimited)',
            ];
        }

        return back()->with('marzpayConnReport', $report);
    }

    /**
     * One-click bulk client import: reads storage/app/imports/clients.csv (upload it there
     * via File Manager first — see the note on this button) rather than a web upload form,
     * since this runs once per deployment onboarding and a file picker isn't needed for that.
     * See ClientImportService for the column matching / skip / flag rules.
     */
    public function importClients(\App\Services\ClientImportService $importer)
    {
        $path = storage_path('app/imports/clients.csv');

        if (!file_exists($path)) {
            return back()->with('error', 'No file found at storage/app/imports/clients.csv — upload your client CSV there first (via File Manager), then click this again.');
        }

        try {
            $result = $importer->importFromCsv($path);
        } catch (\Throwable $e) {
            return back()->with('error', 'Import failed, nothing was saved: ' . $e->getMessage());
        }

        return back()->with('clientImportResult', $result);
    }

    /**
     * Same import as importClients(), but for CSV text pasted directly into the Settings
     * page rather than a file placed on the server first — avoids ever writing the (often
     * sensitive) source file to disk or to git for a one-off import.
     */
    public function importClientsFromText(Request $request, \App\Services\ClientImportService $importer)
    {
        $request->validate(['csv_text' => 'required|string']);

        try {
            $result = $importer->importFromCsvText($request->csv_text);
        } catch (\Throwable $e) {
            return back()->with('error', 'Import failed, nothing was saved: ' . $e->getMessage());
        }

        return back()->with('clientImportResult', $result);
    }

    /**
     * One-off data fix: prepend a leading 0 to every client phone number stored without
     * one (the sipmart import landed 9-digit numbers like 787356969 instead of the local
     * 0787356969 format). Skips numbers already starting with 0, blank, or null, so it's
     * safe to run more than once.
     */
    public function prependZeroToClientPhones()
    {
        $affected = \App\Models\Client::whereNotNull('phone')
            ->where('phone', '!=', '')
            ->where('phone', 'not like', '0%')
            ->update(['phone' => \Illuminate\Support\Facades\DB::raw("CONCAT('0', phone)")]);

        return back()->with('success', "Added a leading 0 to {$affected} client phone number(s).");
    }

    /** Bulk-assigns one employee as relationship_manager_id for every client, overwriting any existing value. */
    public function setDefaultRelationshipManager(Request $request)
    {
        $request->validate(['relationship_manager_id' => 'required|exists:users,id']);

        $user     = \App\Models\User::findOrFail($request->relationship_manager_id);
        $affected = \App\Models\Client::query()->update(['relationship_manager_id' => $user->id]);

        return back()->with('success', "Set {$user->name} as relationship manager for {$affected} client(s).");
    }

    /**
     * One-off data fix: bulk-imported clients only ever get the single `name` field
     * populated, but the multi-step Edit Client form requires first_name/last_name to
     * advance past step 1 — blocking editing (including relationship manager) entirely.
     * Splits name on the first space (first word -> first_name, remainder -> last_name;
     * single-word names duplicate into both). Only touches clients with no first_name
     * yet, so it's safe to run more than once.
     */
    public function backfillClientNames()
    {
        $affected = 0;

        \App\Models\Client::where(fn ($q) => $q->whereNull('first_name')->orWhere('first_name', ''))
            ->get()
            ->each(function ($client) use (&$affected) {
                $name = trim(preg_replace('/\s+/', ' ', $client->name));
                if ($name === '') {
                    return;
                }

                $parts = explode(' ', $name, 2);
                $client->update([
                    'first_name' => $parts[0],
                    'last_name'  => $parts[1] ?? $parts[0],
                ]);
                $affected++;
            });

        return back()->with('success', "Backfilled first/last name for {$affected} client(s).");
    }

    /**
     * One-off data fix: Loan/SavingsAccount/FixedDeposit rows created before branch
     * scoping was added have no branch_id. Backfills each from its client's branch_id.
     * Only touches rows with a null branch_id, so it's safe to run more than once —
     * including after future imports that don't yet set branch_id.
     */
    public function backfillBranchIds()
    {
        $counts = [];

        // Clients created before a branch was assignable (or via bulk import) have no
        // branch_id. If exactly one branch exists, it's safe to assume that's where they
        // all belong; with multiple branches, this needs a human decision instead.
        $branchCount = \App\Models\Branch::count();
        if ($branchCount === 1) {
            $onlyBranch = \App\Models\Branch::first();
            $affected = \App\Models\Client::withoutGlobalScopes()->whereNull('branch_id')->update(['branch_id' => $onlyBranch->id]);
            $counts[] = "Client: {$affected} (assigned to '{$onlyBranch->name}')";
        } elseif ($branchCount > 1) {
            $stillNull = \App\Models\Client::withoutGlobalScopes()->whereNull('branch_id')->count();
            if ($stillNull > 0) {
                $counts[] = "Client: 0 — {$stillNull} client(s) have no branch and multiple branches exist; assign manually";
            }
        }

        foreach ([\App\Models\Loan::class, \App\Models\SavingsAccount::class, \App\Models\FixedDeposit::class, \App\Models\Group::class] as $model) {
            $affected = 0;
            $model::withoutGlobalScopes()
                ->whereNull('branch_id')
                ->with('client')
                ->get()
                ->each(function ($record) use (&$affected) {
                    if ($record->client && $record->client->branch_id) {
                        $record->update(['branch_id' => $record->client->branch_id]);
                        $affected++;
                    }
                });
            $counts[] = class_basename($model) . ": {$affected}";
        }

        // Transactions (the GL journal): derive from whichever client is tagged on the
        // lines, else the linked loan's branch, else the poster's own branch, else
        // AccountingService::defaultBranchId() (head office). Same order as posting,
        // so nothing is left null - a null branch_id is invisible to every
        // branch-scoped user and makes their totals differ from the org-wide view.
        $accounting = app(\App\Services\AccountingService::class);
        $defaultBranchId = $accounting->defaultBranchId();
        $txnAffected = 0;

        \App\Models\Transaction::withoutGlobalScopes()
            ->whereNull('branch_id')
            ->with('lines.client', 'createdBy')
            ->get()
            ->each(function ($txn) use (&$txnAffected, $accounting, $defaultBranchId) {
                $branchId = $txn->lines->map(fn ($l) => $l->client?->branch_id)->filter()->first()
                    ?? $accounting->moduleBranchId($txn->module, $txn->module_id)
                    ?? $txn->createdBy?->branch_id
                    ?? $defaultBranchId;
                if ($branchId) {
                    $txn->update(['branch_id' => $branchId]);
                    $txnAffected++;
                }
            });
        $counts[] = "Transaction: {$txnAffected}";

        return back()->with('success', 'Backfilled branch_id — ' . implode(', ', $counts) . '.');
    }

    /**
     * One-time bulk import of historical loan disbursements. Reads
     * storage/app/imports/loan-disbursements.csv — upload it there via File Manager
     * first — rather than a web upload form, matching importClients(). See
     * LoanImportService for the full posting rules.
     */
    public function importLoanDisbursements(\App\Services\LoanImportService $importer)
    {
        $path = storage_path('app/imports/loan-disbursements.csv');

        if (!file_exists($path)) {
            return back()->with('error', 'No file found at storage/app/imports/loan-disbursements.csv — upload it there first (via File Manager), then click this again.');
        }

        try {
            $result = $importer->importFromCsv($path);
        } catch (\Throwable $e) {
            return back()->with('error', 'Import failed: ' . $e->getMessage());
        }

        return back()->with('loanImportResult', $result);
    }

    public function resetSmsTrial()
    {
        SystemSetting::set('sms_trial_used_count', 0);

        return back()->with('success', 'Free SMS trial reset — ' . SmsSubscriptionService::TRIAL_LIMIT . ' free SMS are available again.');
    }

    /**
     * Preview app\Console\Commands\FixLoanReceivablePostings.php without applying it.
     * Remove this + runLoanReceivableFix() once the one-time fix is confirmed complete.
     */
    public function previewLoanReceivableFix()
    {
        Artisan::call('eltech:fix-loan-receivables');
        $output = trim(Artisan::output());

        return back()->with('loanFixOutput', $output);
    }

    public function runLoanReceivableFix()
    {
        Artisan::call('eltech:fix-loan-receivables', ['--commit' => true]);
        $output = trim(Artisan::output());

        return back()->with('loanFixOutput', $output);
    }

    /**
     * "Go-live wipe" — clears client/transaction data, keeps setup (chart of
     * accounts, products, branches, collateral categories, financial periods,
     * settings) and exactly one superadmin: whoever is running this.
     */
    public function previewResetProductionData()
    {
        Artisan::call('eltech:reset-production-data', ['--keep-user' => auth()->id()]);
        $output = trim(Artisan::output());

        return back()->with('resetProductionOutput', $output);
    }

    public function runResetProductionData(Request $request)
    {
        $request->validate([
            'confirm_phrase' => 'required|string',
        ]);

        if ($request->input('confirm_phrase') !== 'RESET PRODUCTION DATA') {
            return back()->with('resetProductionOutput', 'Confirmation phrase did not match — nothing was deleted.');
        }

        Artisan::call('eltech:reset-production-data', [
            '--keep-user' => auth()->id(),
            '--commit'    => true,
        ]);
        $output = trim(Artisan::output());

        return back()->with('resetProductionOutput', $output);
    }
}
