<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * For models with no branch_id column of their own but that belong (directly
 * or via one hop) to a model that IS branch-scoped (Client, Loan,
 * SavingsAccount, Group, ...). Restricts queries to rows whose related
 * record is visible to the acting user, by delegating to that related
 * model's own ScopedToBranch scope inside whereHas() — no new column needed.
 *
 * Each model using this trait must define:
 *   protected static string $branchScopeRelation = 'loan'; // or 'client', etc.
 */
trait ScopedToBranchViaRelation
{
    protected static function bootScopedToBranchViaRelation(): void
    {
        static::addGlobalScope('branch', function (Builder $query) {
            $user = auth()->user();
            if (!$user || !$user->isBranchScoped()) {
                return;
            }

            $query->whereHas(static::$branchScopeRelation);
        });
    }
}
