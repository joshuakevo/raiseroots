<?php

namespace App\Console\Commands;

use App\Models\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-time (but safe to re-run) fix: clients.client_number has a DB-level unique index that
 * doesn't know about soft deletes, so a deleted client's number stayed blocked for reuse even
 * though the client is gone everywhere in the app - a new client, an edit, or a CSV import
 * trying to use that same number was rejected. Client::booted() now frees the number at the
 * moment of deletion (see app/Models/Client.php), but that only covers clients deleted from
 * now on - this backfills every client already soft-deleted before that fix existed.
 *
 * Remove this command (and its Settings buttons) once the fix has been confirmed complete on
 * production.
 */
class BackfillDeletedClientNumbers extends Command
{
    protected $signature = 'eltech:backfill-deleted-client-numbers {--commit : Actually apply the fix; without this flag, only previews what would change}';

    protected $description = 'One-time fix: free up client_number on every already soft-deleted client';

    public function handle(): int
    {
        $commit = (bool) $this->option('commit');

        $clients = Client::onlyTrashed()
            ->where('client_number', 'not like', '%~deleted~%')
            ->get(['id', 'client_number', 'name']);

        if ($clients->isEmpty()) {
            $this->info('No deleted clients with a still-blocked client_number — nothing to do.');
            return self::SUCCESS;
        }

        $changes = $clients->map(fn ($c) => [
            'client' => $c,
            'from'   => $c->client_number,
            'to'     => $c->client_number . '~deleted~' . $c->id,
        ]);

        if (!$commit) {
            $this->warn('PREVIEW MODE — no changes will be saved. Re-run with --commit to apply.');
        }

        $this->line('');
        $this->line(($commit ? 'Freeing up' : 'Will free up') . ' client_number on ' . $changes->count() . ' deleted client(s):');
        $this->table(['Name', 'From', 'To'], $changes->map(fn ($c) => [$c['client']->name, $c['from'], $c['to']])->take(50)->all());
        if ($changes->count() > 50) {
            $this->line('... and ' . ($changes->count() - 50) . ' more.');
        }

        if (!$commit) {
            $this->line('');
            $this->info('Re-run with --commit to apply.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($changes) {
            foreach ($changes as $c) {
                $c['client']->update(['client_number' => $c['to']]);
            }
        });

        $this->line('');
        $this->info('Done. ' . $changes->count() . ' deleted client(s) updated.');

        return self::SUCCESS;
    }
}
