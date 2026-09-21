<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Performance of each Loan Officer (a client's relationship manager), measured on the
 * loans of the clients assigned to them. Honours the viewer's branch scope through the
 * Loan/Client global scopes.
 */
class StaffAnalysisService
{
    /** Statuses that count as "disbursed" - the loan has actually gone out. */
    private const ISSUED = ['active', 'closed', 'defaulted'];

    /** Rating thresholds: PAR30 % (lower is better) and collection efficiency % (higher is better). */
    public const STRONG_PAR30 = 5;
    public const STRONG_EFFICIENCY = 90;
    public const RISK_PAR30 = 15;
    public const RISK_EFFICIENCY = 70;

    /** Quick-pick periods, in display order. "custom" is handled separately (own from/to). */
    public const PERIODS = [
        'today'        => 'Today',
        'this_week'    => 'This week',
        'last_week'    => 'Last week',
        'this_month'   => 'This month',
        'last_month'   => 'Last month',
        'this_quarter' => 'This quarter',
        'last_quarter' => 'Last quarter',
        'this_year'    => 'This year',
        'last_year'    => 'Last year',
        'all'          => 'All time',
    ];

    /**
     * Turn a preset key (or custom from/to) into a concrete range, plus the equal-length range
     * immediately before it for "vs previous period". The end is capped at today: nothing has
     * happened in the future, and counting not-yet-due instalments would make a running period look bad.
     *
     * @return array{key:string,label:string,from:string,to:string,prev_from:?string,prev_to:?string}
     */
    public static function resolvePeriod(?string $key, ?string $from = null, ?string $to = null): array
    {
        $now = now();

        if (!$key && ($from || $to)) {
            $key = 'custom';
        }
        if ($key !== 'custom' && !isset(self::PERIODS[$key])) {
            $key = 'this_year';
        }

        [$start, $end] = match ($key) {
            'today'        => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            'this_week'    => [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()],
            'last_week'    => [$now->copy()->subWeek()->startOfWeek(), $now->copy()->subWeek()->endOfWeek()],
            'this_month'   => [$now->copy()->startOfMonth(), $now->copy()->endOfMonth()],
            'last_month'   => [$now->copy()->subMonthNoOverflow()->startOfMonth(), $now->copy()->subMonthNoOverflow()->endOfMonth()],
            'this_quarter' => [$now->copy()->startOfQuarter(), $now->copy()->endOfQuarter()],
            'last_quarter' => [$now->copy()->subQuarterNoOverflow()->startOfQuarter(), $now->copy()->subQuarterNoOverflow()->endOfQuarter()],
            'this_year'    => [$now->copy()->startOfYear(), $now->copy()->endOfYear()],
            'last_year'    => [$now->copy()->subYear()->startOfYear(), $now->copy()->subYear()->endOfYear()],
            'all'          => [\Illuminate\Support\Carbon::parse('2000-01-01'), $now->copy()->endOfDay()],
            'custom'       => [
                \Illuminate\Support\Carbon::parse($from ?: $now->copy()->startOfYear()->toDateString())->startOfDay(),
                \Illuminate\Support\Carbon::parse($to ?: $now->toDateString())->endOfDay(),
            ],
        };

        if ($end->gt($now)) {
            $end = $now->copy()->endOfDay();
        }
        if ($start->gt($end)) {
            $start = $end->copy()->startOfDay();
        }

        $days = $start->copy()->startOfDay()->diffInDays($end->copy()->startOfDay()) + 1;

        return [
            'key'       => $key,
            'label'     => $key === 'custom' ? 'Custom range' : self::PERIODS[$key],
            'from'      => $start->toDateString(),
            'to'        => $end->toDateString(),
            'days'      => $days,
            'prev_from' => $key === 'all' ? null : $start->copy()->subDays($days)->toDateString(),
            'prev_to'   => $key === 'all' ? null : $start->copy()->subDay()->toDateString(),
        ];
    }

    /**
     * @param  string|null  $from  start of the "activity in period" figures (disbursed / collected / due); default Jan 1 this year
     * @param  string|null  $to    end of the period; default today
     */
    public function analyse(?string $from = null, ?string $to = null): array
    {
        $from = $from ?: now()->startOfYear()->toDateString();
        $to   = $to ?: now()->toDateString();

        $rows = $this->blankRows();

        $this->addLoanTotals($rows, $from, $to);
        $this->addBorrowerCounts($rows);
        $this->addClientCounts($rows);
        $this->addPortfolioAtRisk($rows);
        $this->addCollectionEfficiency($rows);
        $this->addPeriodCollections($rows, $from, $to);
        $this->addPeriodEfficiency($rows, $from, $to);

        $rows = collect($rows)
            // A loan officer with no clients and no loans is still worth showing (flagged users);
            // the "Unassigned" bucket is only worth showing when it holds something.
            ->filter(fn ($r) => $r['id'] !== null || $r['clients'] > 0 || $r['issued_count'] > 0 || $r['pipeline_count'] > 0)
            ->map(fn ($r) => $this->finish($r));

        $totalOutstanding = $rows->sum('outstanding_principal');
        $rows = $rows
            ->map(function ($r) use ($totalOutstanding) {
                $r['portfolio_share'] = $totalOutstanding > 0 ? round($r['outstanding_principal'] / $totalOutstanding * 100, 1) : 0;
                return $r;
            })
            ->sortByDesc('outstanding_principal')
            ->values();

        return [
            'from'     => $from,
            'to'       => $to,
            'officers' => $rows,
            'totals'   => $this->totals($rows),
            'insights' => $this->insights($rows),
        ];
    }

    /** One zeroed row per Loan Officer, plus anyone who already has clients assigned, plus "Unassigned". */
    private function blankRows(): array
    {
        $assignedIds = Client::query()->whereNotNull('relationship_manager_id')->distinct()->pluck('relationship_manager_id');
        $listedIds   = User::loanOfficers()->pluck('id');

        $users = User::whereIn('id', $listedIds->merge($assignedIds)->unique())->orderBy('name')->get();

        $rows = [];
        foreach ($users as $user) {
            $rows[$user->id] = $this->blank($user->id, $user->name, $listedIds->contains($user->id), (bool) $user->is_active);
        }
        $rows['none'] = $this->blank(null, 'Unassigned', false, true);

        return $rows;
    }

    private function blank(?int $id, string $name, bool $listed, bool $active): array
    {
        return [
            'id' => $id, 'name' => $name, 'listed' => $listed, 'active' => $active,
            'clients' => 0, 'borrowers' => 0, 'repeat_borrowers' => 0, 'issued_borrowers' => 0,
            'issued_count' => 0, 'issued_amount' => 0.0,
            'active_count' => 0, 'active_amount' => 0.0,
            'closed_count' => 0, 'closed_amount' => 0.0,
            'defaulted_count' => 0, 'defaulted_amount' => 0.0,
            'pipeline_count' => 0, 'pipeline_amount' => 0.0,
            'outstanding_principal' => 0.0, 'outstanding_interest' => 0.0,
            'par30_amount' => 0.0,
            'disbursed_period_count' => 0, 'disbursed_period_amount' => 0.0,
            'collected_period' => 0.0, 'interest_collected_period' => 0.0,
            'due_to_date' => 0.0, 'paid_to_date' => 0.0,
            'due_period' => 0.0, 'paid_period' => 0.0,
        ];
    }

    private function key(mixed $rm): string|int
    {
        return $rm === null ? 'none' : (int) $rm;
    }

    /** Loans joined to their client so they can be grouped by the client's Loan Officer. */
    private function loanBase(): Builder
    {
        return Loan::query()->join('clients', 'clients.id', '=', 'loans.client_id');
    }

    /** Sub-select of loan ids the viewer may see - for raw joins, which skip the model scopes. */
    private function visibleLoanIds(): Builder
    {
        return Loan::query()->select('loans.id');
    }

    private function addLoanTotals(array &$rows, string $from, string $to): void
    {
        $issued = "'" . implode("','", self::ISSUED) . "'";

        $data = $this->loanBase()->selectRaw("
            clients.relationship_manager_id as rm,
            SUM(loans.status IN ({$issued})) as issued_count,
            SUM(CASE WHEN loans.status IN ({$issued}) THEN loans.principal ELSE 0 END) as issued_amount,
            SUM(loans.status = 'active') as active_count,
            SUM(CASE WHEN loans.status = 'active' THEN loans.principal ELSE 0 END) as active_amount,
            SUM(loans.status = 'closed') as closed_count,
            SUM(CASE WHEN loans.status = 'closed' THEN loans.principal ELSE 0 END) as closed_amount,
            SUM(loans.status = 'defaulted') as defaulted_count,
            SUM(CASE WHEN loans.status = 'defaulted' THEN loans.principal ELSE 0 END) as defaulted_amount,
            SUM(loans.status IN ('pending','approved')) as pipeline_count,
            SUM(CASE WHEN loans.status IN ('pending','approved') THEN loans.principal ELSE 0 END) as pipeline_amount,
            SUM(CASE WHEN loans.status IN ('active','defaulted') THEN loans.outstanding_principal ELSE 0 END) as outstanding_principal,
            SUM(CASE WHEN loans.status IN ('active','defaulted') THEN loans.outstanding_interest ELSE 0 END) as outstanding_interest,
            SUM(loans.status IN ({$issued}) AND loans.disbursement_date BETWEEN ? AND ?) as disbursed_period_count,
            SUM(CASE WHEN loans.status IN ({$issued}) AND loans.disbursement_date BETWEEN ? AND ? THEN loans.principal ELSE 0 END) as disbursed_period_amount
        ", [$from, $to, $from, $to])
            ->groupBy('clients.relationship_manager_id')
            ->get();

        foreach ($data as $d) {
            $k = $this->key($d->rm);
            if (!isset($rows[$k])) {
                continue;
            }
            foreach (['issued_count', 'active_count', 'closed_count', 'defaulted_count', 'pipeline_count', 'disbursed_period_count'] as $f) {
                $rows[$k][$f] = (int) $d->$f;
            }
            foreach (['issued_amount', 'active_amount', 'closed_amount', 'defaulted_amount', 'pipeline_amount',
                      'outstanding_principal', 'outstanding_interest', 'disbursed_period_amount'] as $f) {
                $rows[$k][$f] = (float) $d->$f;
            }
        }
    }

    /** Borrowers = clients with a loan currently out; repeat = clients who have taken more than one loan. */
    private function addBorrowerCounts(array &$rows): void
    {
        $perClient = $this->loanBase()
            ->whereIn('loans.status', self::ISSUED)
            ->selectRaw("clients.relationship_manager_id as rm, loans.client_id, COUNT(*) as n, MAX(loans.status IN ('active','defaulted')) as is_open")
            ->groupBy('clients.relationship_manager_id', 'loans.client_id')
            ->get();

        foreach ($perClient as $c) {
            $k = $this->key($c->rm);
            if (!isset($rows[$k])) {
                continue;
            }
            $rows[$k]['issued_borrowers']++;
            $rows[$k]['borrowers'] += (int) $c->is_open;
            $rows[$k]['repeat_borrowers'] += $c->n > 1 ? 1 : 0;
        }
    }

    private function addClientCounts(array &$rows): void
    {
        $counts = Client::query()
            ->selectRaw('relationship_manager_id as rm, COUNT(*) as n')
            ->groupBy('relationship_manager_id')
            ->get();

        foreach ($counts as $c) {
            $k = $this->key($c->rm);
            if (isset($rows[$k])) {
                $rows[$k]['clients'] = (int) $c->n;
            }
        }
    }

    /** PAR30 - outstanding principal on loans with an installment more than 30 days overdue. Same definition as the dashboard. */
    private function addPortfolioAtRisk(array &$rows): void
    {
        $cutoff = now()->subDays(30)->toDateString();

        $data = $this->loanBase()
            ->whereIn('loans.status', ['active', 'defaulted'])
            ->whereExists(function ($q) use ($cutoff) {
                $q->select(DB::raw(1))->from('loan_schedules')
                    ->whereColumn('loan_schedules.loan_id', 'loans.id')
                    ->where('loan_schedules.due_date', '<', $cutoff)
                    ->whereIn('loan_schedules.status', ['pending', 'partial', 'overdue']);
            })
            ->selectRaw('clients.relationship_manager_id as rm, SUM(loans.outstanding_principal) as amt')
            ->groupBy('clients.relationship_manager_id')
            ->get();

        foreach ($data as $d) {
            $k = $this->key($d->rm);
            if (isset($rows[$k])) {
                $rows[$k]['par30_amount'] = (float) $d->amt;
            }
        }
    }

    /** Collection efficiency = paid ÷ due across every installment that has fallen due so far (overpayments not counted). */
    private function addCollectionEfficiency(array &$rows): void
    {
        $data = DB::table('loan_schedules')
            ->join('loans', 'loans.id', '=', 'loan_schedules.loan_id')
            ->join('clients', 'clients.id', '=', 'loans.client_id')
            ->whereIn('loans.id', $this->visibleLoanIds())
            ->whereIn('loans.status', self::ISSUED)
            ->where('loan_schedules.due_date', '<=', now()->toDateString())
            ->selectRaw('clients.relationship_manager_id as rm,
                SUM(loan_schedules.total_due) as due,
                SUM(LEAST(loan_schedules.principal_paid + loan_schedules.interest_paid, loan_schedules.total_due)) as paid')
            ->groupBy('clients.relationship_manager_id')
            ->get();

        foreach ($data as $d) {
            $k = $this->key($d->rm);
            if (isset($rows[$k])) {
                $rows[$k]['due_to_date']  = (float) $d->due;
                $rows[$k]['paid_to_date'] = (float) $d->paid;
            }
        }
    }

    /** Same idea as collection efficiency, but only for instalments that fell due inside the period. */
    private function addPeriodEfficiency(array &$rows, string $from, string $to): void
    {
        $to = min($to, now()->toDateString());
        if ($from > $to) {
            return;
        }

        $data = DB::table('loan_schedules')
            ->join('loans', 'loans.id', '=', 'loan_schedules.loan_id')
            ->join('clients', 'clients.id', '=', 'loans.client_id')
            ->whereIn('loans.id', $this->visibleLoanIds())
            ->whereIn('loans.status', self::ISSUED)
            ->whereBetween('loan_schedules.due_date', [$from, $to])
            ->selectRaw('clients.relationship_manager_id as rm,
                SUM(loan_schedules.total_due) as due,
                SUM(LEAST(loan_schedules.principal_paid + loan_schedules.interest_paid, loan_schedules.total_due)) as paid')
            ->groupBy('clients.relationship_manager_id')
            ->get();

        foreach ($data as $d) {
            $k = $this->key($d->rm);
            if (isset($rows[$k])) {
                $rows[$k]['due_period']  = (float) $d->due;
                $rows[$k]['paid_period'] = (float) $d->paid;
            }
        }
    }

    private function addPeriodCollections(array &$rows, string $from, string $to): void
    {
        $data = DB::table('loan_repayments')
            ->join('loans', 'loans.id', '=', 'loan_repayments.loan_id')
            ->join('clients', 'clients.id', '=', 'loans.client_id')
            ->whereIn('loans.id', $this->visibleLoanIds())
            ->whereBetween('loan_repayments.payment_date', [$from, $to])
            ->selectRaw('clients.relationship_manager_id as rm, SUM(loan_repayments.amount) as collected, SUM(loan_repayments.interest_paid) as interest')
            ->groupBy('clients.relationship_manager_id')
            ->get();

        foreach ($data as $d) {
            $k = $this->key($d->rm);
            if (isset($rows[$k])) {
                $rows[$k]['collected_period']          = (float) $d->collected;
                $rows[$k]['interest_collected_period'] = (float) $d->interest;
            }
        }
    }

    /** Derived ratios and the rating for one row. */
    private function finish(array $r): array
    {
        $r['par30_pct']  = $r['outstanding_principal'] > 0 ? round($r['par30_amount'] / $r['outstanding_principal'] * 100, 1) : null;
        $r['default_rate'] = $r['issued_count'] > 0 ? round($r['defaulted_count'] / $r['issued_count'] * 100, 1) : null;
        $r['collection_efficiency'] = $r['due_to_date'] > 0 ? round($r['paid_to_date'] / $r['due_to_date'] * 100, 1) : null;
        $r['period_efficiency'] = $r['due_period'] > 0 ? round($r['paid_period'] / $r['due_period'] * 100, 1) : null;
        $r['avg_loan']   = $r['issued_count'] > 0 ? round($r['issued_amount'] / $r['issued_count'], 2) : 0;
        $r['repeat_rate'] = $r['issued_borrowers'] > 0 ? round($r['repeat_borrowers'] / $r['issued_borrowers'] * 100, 1) : null;
        $r['rating']     = $this->rate($r['par30_pct'], $r['collection_efficiency']);

        return $r;
    }

    /** strong | watch | risk | none (no loan book to judge yet). */
    private function rate(?float $par30, ?float $efficiency): string
    {
        if ($par30 === null && $efficiency === null) {
            return 'none';
        }
        if (($par30 !== null && $par30 >= self::RISK_PAR30) || ($efficiency !== null && $efficiency < self::RISK_EFFICIENCY)) {
            return 'risk';
        }
        if (($par30 === null || $par30 < self::STRONG_PAR30) && ($efficiency === null || $efficiency >= self::STRONG_EFFICIENCY)) {
            return 'strong';
        }

        return 'watch';
    }

    /** Team-wide row: sums, with ratios recomputed from the sums (not averaged). */
    private function totals($rows): array
    {
        $sum = fn (string $f) => $rows->sum($f);
        $t = $this->blank(null, 'All Loan Officers', false, true);

        foreach (array_keys($t) as $f) {
            if (!in_array($f, ['id', 'name', 'listed', 'active'], true)) {
                $t[$f] = $sum($f);
            }
        }

        return $this->finish($t) + ['portfolio_share' => 100];
    }

    /** The standout officers - only among those with enough of a book to compare. */
    private function insights($rows): array
    {
        $named   = $rows->filter(fn ($r) => $r['id'] !== null);
        $withBook = $named->filter(fn ($r) => $r['outstanding_principal'] > 0);

        return [
            'largest_portfolio' => $withBook->sortByDesc('outstanding_principal')->first(),
            'lowest_par'        => $withBook->sortBy([['par30_pct', 'asc'], ['outstanding_principal', 'desc']])->first(),
            'best_collection'   => $named->filter(fn ($r) => $r['collection_efficiency'] !== null)
                ->sortBy([['collection_efficiency', 'desc'], ['due_to_date', 'desc']])->first(),
            'top_disburser'     => $named->filter(fn ($r) => $r['disbursed_period_amount'] > 0)->sortByDesc('disbursed_period_amount')->first(),
            'top_collector'     => $named->filter(fn ($r) => $r['collected_period'] > 0)->sortByDesc('collected_period')->first(),
            'needs_attention'   => $withBook->filter(fn ($r) => $r['rating'] === 'risk')->sortByDesc('par30_amount')->first(),
        ];
    }
}
