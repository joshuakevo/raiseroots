<?php

namespace App\Console\Commands;

use App\Models\Loan;
use App\Services\CreditRegistryService;
use Illuminate\Console\Command;

/**
 * Push every disbursed loan (active, defaulted, closed) to the ElTech Credit Registry.
 * Needed once when a system first joins the registry; afterwards loans are pushed
 * automatically as they change, so re-running is only a catch-up (e.g. after an outage).
 * Also refreshes days-in-arrears, which grows by itself without the loan changing.
 */
class SyncCreditRegistry extends Command
{
    protected $signature = 'eltech:crb-sync';

    protected $description = 'Push all disbursed loans to the ElTech Credit Registry';

    public function handle(CreditRegistryService $registry): int
    {
        if (!$registry->isConfigured()) {
            $this->error('Credit Registry is not configured - set the Registry URL and API Key in Settings first.');
            return self::FAILURE;
        }

        $saved = 0;
        $error = null;
        Loan::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->whereIn('status', ['active', 'defaulted', 'closed'])
            ->with(['client' => fn ($q) => $q->withoutGlobalScopes()])
            ->chunkById(200, function ($loans) use ($registry, &$saved, &$error) {
                $result = $registry->pushLoans($loans);
                $saved += $result['saved'];
                if ($result['error']) {
                    $error = $result['error'];
                    return false;
                }
            });

        if ($error) {
            $this->error($error);
            $this->line("Sent before the error: {$saved} loan(s).");
            return self::FAILURE;
        }

        $this->info("Done. {$saved} loan(s) sent to the registry.");
        $this->line("Loans whose client has no national ID number are skipped - add the ID on the client and they'll be sent automatically.");

        return self::SUCCESS;
    }
}
