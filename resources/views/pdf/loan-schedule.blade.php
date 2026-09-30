<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1a1a1a; background: #fff; }
    .header { background: #0f2444; color: #fff; padding: 18px 24px; margin-bottom: 20px; }
    .header h1 { font-size: 18px; font-weight: bold; margin-bottom: 2px; }
    .header p { font-size: 10px; opacity: 0.7; }
    .header-right { float: right; text-align: right; }
    .clearfix::after { content: ''; display: table; clear: both; }
    .section-title { font-size: 10px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.08em; color: #6b7280; border-bottom: 1px solid #e5e7eb; padding-bottom: 4px; margin: 16px 0 8px; }
    .info-grid { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
    .info-grid td { padding: 3px 8px 3px 0; font-size: 11px; vertical-align: top; }
    .info-grid td.label { color: #6b7280; width: 38%; }
    .info-grid td.value { font-weight: 600; }
    .data-table { width: 100%; border-collapse: collapse; margin-top: 4px; }
    .data-table th { background: #0f2444; color: #fff; font-size: 9px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.06em; padding: 6px 8px; text-align: left; }
    .data-table td { padding: 5px 8px; border-bottom: 1px solid #f3f4f6; font-size: 10px; }
    .data-table tbody tr:nth-child(even) td { background: #f9fafb; }
    .data-table tbody tr:last-child td { border-bottom: none; }
    .text-right { text-align: right; }
    .text-center { text-align: center; }
    .status-paid { color: #065f46; font-weight: bold; }
    .status-overdue { color: #991b1b; font-weight: bold; }
    .status-partial { color: #92400e; font-weight: bold; }
    .status-pending { color: #6b7280; }
    .footer { margin-top: 24px; padding-top: 10px; border-top: 1px solid #e5e7eb; font-size: 9px; color: #9ca3af; }
    .two-col { width: 100%; border-collapse: collapse; }
    .two-col td { width: 50%; vertical-align: top; padding-right: 20px; }
    .two-col td:last-child { padding-right: 0; }
    .summary-box { background: #f0f2f5; border: 1px solid #e5e7eb; padding: 10px 14px; margin-bottom: 14px; }
    .summary-box table { width: 100%; border-collapse: collapse; }
    .summary-box td { padding: 2px 12px 2px 0; font-size: 11px; }
    .summary-box td.lbl { color: #6b7280; width: 40%; }
    .summary-box td.val { font-weight: bold; }
</style>
</head>
<body>

<div class="header clearfix">
    <div class="header-right">
        <div style="font-size:10px;opacity:.7">Printed: {{ now()->format('d M Y H:i') }}</div>
    </div>
    <h1>@php $_logo = \App\Models\SystemSetting::get('org_logo'); @endphp@if($_logo)<img src="{{ public_path($_logo) }}" style="height:32px;max-width:160px;object-fit:contain;vertical-align:middle">@else{{ \App\Models\SystemSetting::get('org_name', 'ElTech Finance') }}@endif</h1>
    <p>Loan Repayment Schedule — {{ $loan->loan_number }}</p>
</div>

<div style="padding: 0 24px 24px;">

<table class="two-col">
<tr>
<td>
    <div class="section-title">Client Information</div>
    <table class="info-grid">
        <tr><td class="label">Full Name</td><td class="value">{{ $loan->client->name }}</td></tr>
        <tr><td class="label">Client No.</td><td class="value">{{ $loan->client->client_number }}</td></tr>
        @if($loan->client->phone)
        <tr><td class="label">Phone</td><td class="value">{{ $loan->client->phone }}</td></tr>
        @endif
    </table>
</td>
<td>
    <div class="section-title">Loan Information</div>
    <table class="info-grid">
        <tr><td class="label">Loan Number</td><td class="value">{{ $loan->loan_number }}</td></tr>
        <tr><td class="label">Product</td><td class="value">{{ $loan->product->name }}</td></tr>
        <tr><td class="label">Principal</td><td class="value">{{ number_format($loan->principal, $dp) }}</td></tr>
        <tr><td class="label">Interest Rate</td><td class="value">{{ $loan->interest_rate }}% {{ ucfirst($loan->interest_method) }}</td></tr>
        <tr><td class="label">Term</td><td class="value">{{ $loan->term_months }} months</td></tr>
        <tr><td class="label">Disbursement Date</td><td class="value">{{ $loan->disbursement_date?->format('d M Y') ?? '—' }}</td></tr>
        <tr><td class="label">Maturity Date</td><td class="value">{{ $loan->maturity_date?->format('d M Y') ?? '—' }}</td></tr>
    </table>
</td>
</tr>
</table>

@php
    $scheduleData = $loan->schedules->isNotEmpty() ? $loan->schedules : collect($schedulePreview);
    $totalPrincipal = $scheduleData->sum('principal_due');
    $totalInterest  = $scheduleData->sum('interest_due');
    $totalDue       = $scheduleData->sum('total_due') + $loan->admin_cost;
@endphp

<div class="summary-box">
    <table>
        <tr>
            <td class="lbl">Total Principal</td><td class="val">{{ number_format($totalPrincipal, $dp) }}</td>
            <td class="lbl">Total Interest</td><td class="val">{{ number_format($totalInterest, $dp) }}</td>
            <td class="lbl">Total Repayable</td><td class="val" style="color:#0f2444;font-size:13px">{{ number_format($totalDue, $dp) }}</td>
        </tr>
        @if($currentPenalty > 0)
        <tr>
            <td class="lbl" style="color:#991b1b">&#9888; Accrued Penalty</td>
            <td class="val" style="color:#991b1b">{{ number_format($currentPenalty, $dp) }}</td>
            <td class="lbl">Penalty Rate</td><td class="val">{{ $loan->product->penalty_rate }}%/day on overdue</td>
            <td class="lbl">Total Owing Now</td>
            <td class="val" style="color:#991b1b;font-size:13px">{{ number_format($loan->outstanding_principal + $loan->outstanding_interest + $currentPenalty + $loan->outstanding_admin_fee, $dp) }}</td>
        </tr>
        @endif
        @if($loan->admin_cost > 0)
        <tr>
            <td class="lbl">Admin Fee</td>
            <td class="val">{{ number_format($loan->admin_cost, $dp) }}</td>
            <td class="lbl">Admin Fee Outstanding</td>
            <td class="val" style="color:{{ $loan->outstanding_admin_fee > 0.01 ? '#991b1b' : '#065f46' }}">{{ number_format($loan->outstanding_admin_fee, $dp) }}</td>
        </tr>
        @endif
    </table>
</div>

<div class="section-title">Schedule</div>
@if($scheduleData->isEmpty())
<p style="color:#9ca3af;font-size:10px;padding:8px 0;">Schedule not yet generated (loan pending disbursement).</p>
@else
@php $pdfTotalPenalty = 0; @endphp
<table class="data-table">
    <thead>
        <tr>
            <th class="text-center">#</th>
            <th>Due Date</th>
            <th class="text-right">Principal</th>
            <th class="text-right">Interest</th>
            <th class="text-right">Penalty</th>
            <th class="text-right">Total Due</th>
            <th class="text-right">Balance After</th>
            <th class="text-center">Status</th>
        </tr>
    </thead>
    <tbody>
    @foreach($scheduleData as $row)
    @php
        $isModel       = $row instanceof \App\Models\LoanSchedule;
        $rowId         = $isModel ? $row->id : null;
        $installmentNo = $isModel ? $row->installment_no : $row['installment_no'];
        $dueDate       = $isModel ? $row->due_date->format('d M Y') : \Carbon\Carbon::parse($row['due_date'])->format('d M Y');
        $principalDue  = $isModel ? $row->principal_due : $row['principal_due'];
        $interestDue   = $isModel ? $row->interest_due : $row['interest_due'];
        $totalDueRow   = $isModel ? $row->total_due : $row['total_due'];
        $balanceAfter  = $isModel ? $row->balance_after : $row['balance_after'];
        $status        = $isModel ? $row->status : 'preview';
        $rowPenalty    = $rowId ? ($penaltyBreakdown[$rowId] ?? 0) : 0;
        $pdfTotalPenalty += $rowPenalty;
        $isOverdue     = $isModel && $row->isOverdue();
    @endphp
        <tr style="{{ $isOverdue ? 'background:#fef2f2' : ($status === 'paid' ? 'background:#f0fdf4' : '') }}">
            <td class="text-center">{{ $installmentNo }}</td>
            <td>{{ $dueDate }}</td>
            <td class="text-right">{{ number_format($principalDue, $dp) }}</td>
            <td class="text-right">{{ number_format($interestDue, $dp) }}</td>
            <td class="text-right" style="{{ $rowPenalty > 0 ? 'color:#991b1b;font-weight:bold' : 'color:#9ca3af' }}">
                {{ $rowPenalty > 0 ? number_format($rowPenalty, $dp) : '—' }}
            </td>
            <td class="text-right" style="font-weight:bold">{{ number_format($totalDueRow + $rowPenalty, $dp) }}</td>
            <td class="text-right">{{ number_format($balanceAfter, $dp) }}</td>
            <td class="text-center">
                @if($status === 'paid') <span class="status-paid">PAID</span>
                @elseif($isOverdue) <span class="status-overdue">OVERDUE</span>
                @elseif($status === 'partial') <span class="status-partial">PARTIAL</span>
                @elseif($status === 'preview') <span class="status-pending">PREVIEW</span>
                @else <span class="status-pending">PENDING</span>
                @endif
            </td>
        </tr>
    @endforeach
    @if($loan->admin_cost > 0)
        @php $adminFeePaid = $loan->admin_cost - $loan->outstanding_admin_fee; @endphp
        <tr style="background:#fffbeb">
            <td class="text-center">—</td>
            <td>Admin Fee <span style="color:#9ca3af">(collected from any repayment, after interest)</span></td>
            <td class="text-right" style="color:#9ca3af">—</td>
            <td class="text-right" style="color:#9ca3af">—</td>
            <td class="text-right" style="color:#9ca3af">—</td>
            <td class="text-right" style="font-weight:bold">{{ number_format($loan->admin_cost, $dp) }}</td>
            <td class="text-right">{{ number_format($loan->outstanding_admin_fee, $dp) }}</td>
            <td class="text-center">
                @if($loan->outstanding_admin_fee <= 0.01) <span class="status-paid">COLLECTED</span>
                @elseif($adminFeePaid > 0.01) <span class="status-partial">PARTIAL</span>
                @else <span class="status-pending">PENDING</span>
                @endif
            </td>
        </tr>
    @endif
    </tbody>
    <tfoot>
        <tr style="background:#0f2444;color:#fff;font-weight:bold;">
            <td colspan="2" style="padding:6px 8px;font-size:10px;">TOTALS</td>
            <td class="text-right" style="padding:6px 8px;">{{ number_format($totalPrincipal, $dp) }}</td>
            <td class="text-right" style="padding:6px 8px;">{{ number_format($totalInterest, $dp) }}</td>
            <td class="text-right" style="padding:6px 8px;">{{ $pdfTotalPenalty > 0 ? number_format($pdfTotalPenalty, $dp) : '—' }}</td>
            <td class="text-right" style="padding:6px 8px;">{{ number_format($totalDue + $pdfTotalPenalty, $dp) }}</td>
            <td colspan="2"></td>
        </tr>
    </tfoot>
</table>
@endif

<div class="footer clearfix">
    <div style="float:right">Page 1</div>
    <div>This schedule is computer generated. @php $_logo = \App\Models\SystemSetting::get('org_logo'); @endphp@if($_logo)<img src="{{ public_path($_logo) }}" style="height:32px;max-width:160px;object-fit:contain;vertical-align:middle">@else{{ \App\Models\SystemSetting::get('org_name', 'ElTech Finance') }}@endif</div>
</div>

</div>
</body>
</html>
