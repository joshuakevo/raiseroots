<?php

namespace App\Console\Commands;

use App\Models\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-time (but safe to re-run) fix: the bulk CSV client import (ClientImportService) only
 * ever set `name`, never `first_name`/`middle_name`/`last_name` - but the Edit Client form
 * reads and requires those three separate fields, so every imported client shows up with a
 * blank name on Edit, and can't be saved again until someone retypes it from scratch.
 *
 * Splits `name` (first word -> first_name, last word -> last_name, anything between ->
 * middle_name) for every client whose first_name is still empty. Only ever touches a client
 * once - re-running after someone has since filled in or corrected the split by hand leaves
 * their edit alone, since first_name is no longer empty.
 *
 * Remove this command (and its Settings buttons) once the fix has been confirmed complete
 * on production.
 */
class BackfillClientNames extends Command
{
    protected $signature = 'eltech:backfill-client-names {--commit : Actually apply the fix; without this flag, only previews what would change}';

    protected $description = 'One-time fix: split clients.name into first_name/middle_name/last_name for clients still missing them';

    public function handle(): int
    {
        $commit = (bool) $this->option('commit');

        $clients = Client::where(function ($q) {
            $q->whereNull('first_name')->orWhere('first_name', '');
        })->get(['id', 'client_number', 'name']);

        if ($clients->isEmpty()) {
            $this->info('No clients missing first_name - nothing to do.');
            return self::SUCCESS;
        }

        $changes          = [];
        $singleWordFlags  = [];
        foreach ($clients as $client) {
            [$first, $middle, $last] = Client::splitName($client->name);
            $changes[] = ['client' => $client, 'first' => $first, 'middle' => $middle, 'last' => $last];
            if ($last === '') {
                $singleWordFlags[] = "{$client->client_number}: \"{$client->name}\" - single word, Last Name left blank, fill in manually";
            }
        }

        if (!$commit) {
            $this->warn('PREVIEW MODE — no changes will be saved. Re-run with --commit to apply.');
        }

        $this->line('');
        $this->line(($commit ? 'Setting' : 'Will set') . ' first/middle/last name on ' . count($changes) . ' client(s):');
        $this->table(['Client #', 'Name', 'First', 'Middle', 'Last'], array_map(
            fn ($c) => [$c['client']->client_number, $c['client']->name, $c['first'], $c['middle'], $c['last']],
            array_slice($changes, 0, 50)
        ));
        if (count($changes) > 50) {
            $this->line('... and ' . (count($changes) - 50) . ' more.');
        }

        if (!empty($singleWordFlags)) {
            $this->line('');
            $this->warn(count($singleWordFlags) . ' single-word name(s) - Last Name intentionally left blank rather than guessed:');
            foreach (array_slice($singleWordFlags, 0, 20) as $flag) {
                $this->line('  ' . $flag);
            }
        }

        if (!$commit) {
            $this->line('');
            $this->info('Re-run with --commit to apply.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($changes) {
            foreach ($changes as $c) {
                $c['client']->update([
                    'first_name'  => $c['first'],
                    'middle_name' => $c['middle'] !== '' ? $c['middle'] : null,
                    'last_name'   => $c['last'],
                ]);
            }
        });

        $this->line('');
        $this->info('Done. ' . count($changes) . ' client(s) updated.');

        return self::SUCCESS;
    }
}
