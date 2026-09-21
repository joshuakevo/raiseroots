<?php

namespace App\Http\Controllers;

use App\Services\StaffAnalysisService;
use Illuminate\Http\Request;

class StaffAnalysisController extends Controller
{
    public function __construct(protected StaffAnalysisService $analysis) {}

    public function index(Request $request)
    {
        $request->validate([
            'from' => 'nullable|date',
            'to'   => 'nullable|date|after_or_equal:from',
        ]);

        $data = $this->analysis->analyse($request->from, $request->to);

        if ($request->format === 'csv') {
            return $this->csv($data);
        }

        return view('staff-analysis.index', $data + ['thresholds' => [
            'strong_par' => StaffAnalysisService::STRONG_PAR30,
            'strong_eff' => StaffAnalysisService::STRONG_EFFICIENCY,
            'risk_par'   => StaffAnalysisService::RISK_PAR30,
            'risk_eff'   => StaffAnalysisService::RISK_EFFICIENCY,
        ]]);
    }

    private function csv(array $data)
    {
        return response()->streamDownload(function () use ($data) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Loan Officer', 'Clients', 'Borrowers (open loans)', 'Loans Disbursed', 'Amount Disbursed',
                'Active Loans', 'Closed Loans', 'Defaulted Loans', 'Defaulted Amount', 'Pending/Approved Loans',
                'Outstanding Principal', 'Outstanding Interest', 'PAR30 %', 'Default Rate %', 'Collection Efficiency %',
                "Disbursed {$data['from']} to {$data['to']}", "Collected {$data['from']} to {$data['to']}",
                'Average Loan', 'Repeat Borrowers %', 'Rating']);

            foreach ($data['officers']->concat([$data['totals']]) as $r) {
                fputcsv($out, [
                    $r['name'], $r['clients'], $r['borrowers'], $r['issued_count'], $r['issued_amount'],
                    $r['active_count'], $r['closed_count'], $r['defaulted_count'], $r['defaulted_amount'], $r['pipeline_count'],
                    $r['outstanding_principal'], $r['outstanding_interest'], $r['par30_pct'], $r['default_rate'], $r['collection_efficiency'],
                    $r['disbursed_period_amount'], $r['collected_period'], $r['avg_loan'], $r['repeat_rate'], $r['rating'],
                ]);
            }

            fclose($out);
        }, 'staff-analysis-' . now()->format('Y-m-d') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
