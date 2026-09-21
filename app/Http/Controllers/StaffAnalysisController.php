<?php

namespace App\Http\Controllers;

use App\Services\StaffAnalysisService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StaffAnalysisController extends Controller
{
    public function __construct(protected StaffAnalysisService $analysis) {}

    public function index(Request $request)
    {
        $request->validate([
            'period' => ['nullable', Rule::in([...array_keys(StaffAnalysisService::PERIODS), 'custom'])],
            'from'   => 'nullable|date',
            'to'     => 'nullable|date|after_or_equal:from',
        ]);

        $period = StaffAnalysisService::resolvePeriod($request->period, $request->from, $request->to);
        $data   = $this->analysis->analyse($period['from'], $period['to']);

        if ($request->format === 'csv') {
            return $this->csv($data, $period);
        }

        // Same-length window straight before the chosen one, so activity can be shown as up/down.
        $previous = null;
        if ($period['prev_from']) {
            $prev = $this->analysis->analyse($period['prev_from'], $period['prev_to']);
            $previous = $prev['officers']->keyBy(fn ($r) => $r['id'] ?? 'none')->all() + ['total' => $prev['totals']];
        }

        return view('staff-analysis.index', $data + [
            'period'   => $period,
            'periods'  => StaffAnalysisService::PERIODS,
            'previous' => $previous,
            'thresholds' => [
                'strong_par' => StaffAnalysisService::STRONG_PAR30,
                'strong_eff' => StaffAnalysisService::STRONG_EFFICIENCY,
                'risk_par'   => StaffAnalysisService::RISK_PAR30,
                'risk_eff'   => StaffAnalysisService::RISK_EFFICIENCY,
            ],
        ]);
    }

    private function csv(array $data, array $period)
    {
        $range = "{$period['from']} to {$period['to']}";

        return response()->streamDownload(function () use ($data, $range) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Loan Officer', 'Clients', 'Borrowers (open loans)', 'Loans Disbursed (all time)', 'Amount Disbursed (all time)',
                'Active Loans', 'Closed Loans', 'Defaulted Loans', 'Defaulted Amount', 'Pending/Approved Loans',
                'Outstanding Principal', 'Outstanding Interest', 'PAR30 %', 'Default Rate %', 'Collection Efficiency % (to date)',
                'Average Loan', 'Repeat Borrowers %', 'Rating',
                "Loans Disbursed {$range}", "Amount Disbursed {$range}", "Collected {$range}", "Interest Collected {$range}",
                "Instalments Due {$range}", "Instalments Paid {$range}", "Period Collection Rate % {$range}"]);

            foreach ($data['officers']->concat([$data['totals']]) as $r) {
                fputcsv($out, [
                    $r['name'], $r['clients'], $r['borrowers'], $r['issued_count'], $r['issued_amount'],
                    $r['active_count'], $r['closed_count'], $r['defaulted_count'], $r['defaulted_amount'], $r['pipeline_count'],
                    $r['outstanding_principal'], $r['outstanding_interest'], $r['par30_pct'], $r['default_rate'], $r['collection_efficiency'],
                    $r['avg_loan'], $r['repeat_rate'], $r['rating'],
                    $r['disbursed_period_count'], $r['disbursed_period_amount'], $r['collected_period'], $r['interest_collected_period'],
                    $r['due_period'], $r['paid_period'], $r['period_efficiency'],
                ]);
            }

            fclose($out);
        }, 'staff-analysis-' . now()->format('Y-m-d') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
