<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

class SettingsController extends Controller
{
    public function index()
    {
        $settings = SystemSetting::whereNotIn('key', ['org_logo'])
            ->orderBy('group')->orderBy('label')->get()->groupBy('group');
        return view('settings.index', compact('settings'));
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

        $report = [
            'PHP version'                          => PHP_VERSION,
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
            $sourceRoot . '/storage/app/public/clients' => public_path('clients'),
            storage_path('app/public/clients')          => public_path('clients'),
            $sourceRoot . '/public/logos'                => public_path('logos'),
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

        $msg = "Sync complete. Copied {$copied} missing file(s), skipped {$skipped} already present.";
        if ($errors) {
            $msg .= ' Issues: ' . implode(' | ', array_slice($errors, 0, 10));
        }

        return back()->with('success', $msg);
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
}
