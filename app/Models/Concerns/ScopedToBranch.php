<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Restricts queries to the authenticated user's branch, for branch-level
 * operational visibility ("staff should only see their branch").
 *
 * Exempt: super_admin/admin (see everything) and portal roles (client,
 * group_leader, group_member) whose visibility is already scoped to their
 * own client_id elsewhere and have no branch_id of their own.
 *
 * Note: this only scopes Eloquent queries. Raw DB::table()/DB::select()
 * queries (used in parts of Dashboard/Reports for performance) bypass this
 * and are NOT covered.
 */
trait ScopedToBranch
{
    protected static function bootScopedToBranch(): void
    {
        static::addGlobalScope('branch', function (Builder $query) {
            $user = auth()->user();
            if (!$user || !$user->isBranchScoped()) {
                return;
            }

            $table = $query->getModel()->getTable();
            if ($user->branch_id) {
                $query->where("{$table}.branch_id", $user->branch_id);
            } else {
                $query->whereRaw('1 = 0');
            }
        });
    }
}
