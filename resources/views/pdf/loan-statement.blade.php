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
    .stats-table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
    .stats-table td { padding: 8px 12px; border: 1px solid #e5e7eb; font-size: 11px; }
    .stats-table .stat-label { color: #6b7280; font-size: 10px; display: block; }
    .stats-table .stat-value { font-size: 13px; font-weight: bold; color: #0f2444; }
    .data-table { width: 100%; border-collapse: collapse; margin-top: 4px; }
    .data-table th { background: #f3f4f6; font-size: 9px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.06em; color: #6b7280; padding: 6px 8px; text-align: left; border-bottom: 1px solid #e5e7eb; }
    .data-table td { padding: 6px 8px; border-bottom: 1px solid #f3f4f6; font-size: 10px; }
    .data-table tbody tr:last-child td { border-bottom: none; }
    .text-right { text-align: right; }
    .text-center { text-align: center; }
    .badge { display: inline-block; padding: 1px 6px; border-radius: 10px; font-size: 9px; font-weight: bold; }
    .badge-deposit { background: #d1fae5; color: #065f46; }
    .badge-withdrawal { background: #fee2e2; color: #991b1b; }
    .badge-interest { background: #e0f2fe; color: #0369a1; }
    .badge-transfer { background: #ede9fe; color: #5b21b6; }
    .badge-other { background: #f3f4f6; color: #6b7280; }
    .footer { margin-top: 24px; padding-top: 10px; border-top: 1px solid #e5e7eb; font-size: 9px; color: #9ca3af; }
    .amount-pos { color: #065f46; }
    .amount-neg { color: #991b1b; }
    .two-col { width: 100%; border-collapse: collapse; }
    .two-col td { width: 50%; vertical-align: top; padding-right: 20px; }
    .two-col td:last-child { padding-right: 0; }
</style>
</head>
<body>

<div class="header clearfix">
    <div class="header-right">
        <div style="font-size:10px;opacity:.7">Printed: {{ now()->format('d M Y H:i') }}</div>
        <div style="font-size:10px;opacity:.7">Statement Date: {{ now()->format('d M Y') }}</div>
    </div>
    <h1>@php $_logo = \App\Models\SystemSetting::get('org_logo'); @endphp@if($_logo)<img src="{{ public_path($_logo) }}" style="height:32px;max-width:160px;object-fit:contain;vertical-align:middle">@else{{ \App\Models\SystemSetting::get('org_name', 'ElTech Finance') }}@endif</h1>
    <p>Loan Statement — {{ $loan->loan_number }}</p>
</div>

<div style="padding: 0 24px 24px;">

{{-- Client & Loan Info --}}
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
        @if($loan->client->email)
        <tr><td class="label">Email</td><td class="value">{{ $loan->client->email }}</td></tr>
        @endif
    </table>
</td>
<td>
    <div class="section-title">Loan Information</div>
    <table class="info-grid">
        <tr><td class="label">Loan Number</td><td class="value">{{ $loan->loan_number }}</td></tr>
        <tr><td class="label">Product</td><td class="value">{{ $loan->product->name }}</td></tr>
        <tr><td class="label">Principal</td><td class="value">{{ number_format($loan->principal, $dp) }}</td></tr>
        <tr><td class="label">Interest Rate</td><td class="value">{{ $loan->interest_rate }}% ({{ ucfirst($loan->interest_method) }})</td></tr>
        <tr><td class="label">Term</td><td class="value">{{ $loan->term_months }} months</td></tr>
        <tr><td class="label">Disbursement</td><td class="value">{{ $loan->disbursement_date?->format('d M Y') ?? '—' }}</td></tr>
        <tr><td class="label">Maturity Date</td><td class="value">{{ $loan->maturity_date?->format('d M Y') ?? '—' }}</td></tr>
        <tr><td class="label">Status</td><td class="value">{{ strtoupper($loan->status) }}</td></tr>
    </table>
</td>
</tr>
</table>

{{-- Summary Stats --}}
<div class="section-title">Account Summary</div>
<table class="stats-table">
<tr>
    <td><span class="stat-label">Outstanding Principal</span><span class="stat-value">{{ number_format($loan->outstanding_principal, $dp) }}</span></td>
    <td><span class="stat-label">Outstanding Interest</span><span class="stat-value">{{ number_format($loan->outstanding_interest, $dp) }}</span></td>
    <td><span class="stat-label">Accrued Penalty</span><span class="stat-value" style="color:#991b1b">{{ number_format($currentPenalty, $dp) }}</span>@if($currentPenalty > 0)<span class="stat-label">{{ $loan->product->penalty_rate }}%/day on overdue</span>@endif</td>
    @php $liveTotal = $loan->outstanding_principal + $loan->outstanding_interest + $currentPenalty + $loan->outstanding_admin_fee; @endphp
    <td><span class="stat-label">Total Outstanding</span><span class="stat-value" style="color:#991b1b">{{ number_format($liveTotal, $dp) }}</span></td>
    <td><span class="stat-label">Total Paid</span><span class="stat-value" style="color:#065f46">{{ number_format($loan->total_paid, $dp) }}</span></td>
</tr>
@if($loan->admin_cost > 0)
<tr>
    <td><span class="stat-label">Outstanding Admin Fee</span><span class="stat-value" style="color:#991b1b">{{ number_format($loan->outstanding_admin_fee, $dp) }}</span></td>
</tr>
@endif
</table>

{{-- Repayment History --}}
<div class="section-title">Repayment History</div>
@if($loan->repayments->isEmpty())
<p style="color:#9ca3af;font-size:10px;padding:8px 0;">No repayments recorded.</p>
@else
<table class="data-table">
    <thead>
        <tr>
            <th>#</th>
            <th>Date</th>
            <th>Method</th>
            <th>Reference</th>
            <th class="text-right">Principal</th>
            <th class="text-right">Interest</th>
            <th class="text-right">Penalty</th>
            <th class="text-right">Total Paid</th>
            <th class="text-right">Balance</th>
        </tr>
    </thead>
    @php $runningBalance = $loan->principal; @endphp
    <tbody>
    @foreach($loan->repayments as $i => $r)
    @php $runningBalance -= $r->principal_paid; @endphp
        <tr>
            <td>{{ $i + 1 }}</td>
            <td>{{ $r->payment_date->format('d M Y') }}</td>
            <td>{{ ucfirst(str_replace('_', ' ', $r->payment_method)) }}</td>
            <td>{{ $r->reference ?? '—' }}</td>
            <td class="text-right">{{ number_format($r->principal_paid, $dp) }}</td>
            <td class="text-right">{{ number_format($r->interest_paid, $dp) }}</td>
            <td class="text-right">{{ number_format($r->penalty_paid, $dp) }}</td>
            <td class="text-right" style="font-weight:bold">{{ number_format($r->amount, $dp) }}</td>
            <td class="text-right" style="font-weight:bold;color:{{ $runningBalance <= 0 ? '#065f46' : '#1a1a1a' }}">{{ number_format(max($runningBalance,0), $dp) }}</td>
        </tr>
    @endforeach
    </tbody>
    <tfoot>
        <tr style="background:#f9fafb;font-weight:bold;">
            <td colspan="4" style="padding:6px 8px;font-size:10px;">TOTALS</td>
            <td class="text-right" style="padding:6px 8px;">{{ number_format($loan->repayments->sum('principal_paid'), $dp) }}</td>
            <td class="text-right" style="padding:6px 8px;">{{ number_format($loan->repayments->sum('interest_paid'), $dp) }}</td>
            <td class="text-right" style="padding:6px 8px;">{{ number_format($loan->repayments->sum('penalty_paid'), $dp) }}</td>
            <td class="text-right" style="padding:6px 8px;">{{ number_format($loan->repayments->sum('amount'), $dp) }}</td>
            <td class="text-right" style="padding:6px 8px;color:{{ $loan->outstanding_principal <= 0 ? '#065f46' : '#991b1b' }}">{{ number_format($loan->outstanding_principal, $dp) }}</td>
        </tr>
    </tfoot>
</table>
@endif

{{-- Repayment Schedule --}}
@if($loan->schedules->isNotEmpty())
<div class="section-title" style="margin-top:20px">
    Repayment Schedule
    @if($currentPenalty > 0)
    <span style="color:#991b1b;font-size:9px;font-weight:bold;margin-left:12px">&#9888; Accrued Penalty: {{ number_format($currentPenalty, $dp) }}</span>
    @endif
</div>
@php $schTotalPenalty = 0; @endphp
<table class="data-table">
    <thead>
        <tr>
            <th class="text-center">#</th><th>Due Date</th>
            <th class="text-right">Principal</th><th class="text-right">Interest</th>
            <th class="text-right">Penalty</th><th class="text-right">Total Due</th>
            <th class="text-right">Paid</th><th class="text-center">Status</th>
        </tr>
    </thead>
    <tbody>
    @foreach($loan->schedules as $s)
    @php $rowPenalty = $penaltyBreakdown[$s->id] ?? 0; $schTotalPenalty += $rowPenalty; @endphp
    <tr style="{{ $s->isOverdue() ? 'background:#fef2f2' : ($s->status === 'paid' ? 'background:#f0fdf4' : '') }}">
        <td class="text-center">{{ $s->installment_no }}</td>
        <td>{{ $s->due_date->format('d M Y') }}</td>
        <td class="text-right">{{ number_format($s->principal_due, $dp) }}</td>
        <td class="text-right">{{ number_format($s->interest_due, $dp) }}</td>
        <td class="text-right" style="{{ $rowPenalty > 0 ? 'color:#991b1b;font-weight:bold' : 'color:#9ca3af' }}">
            {{ $rowPenalty > 0 ? number_format($rowPenalty, $dp) : '—' }}
        </td>
        <td class="text-right" style="font-weight:bold">{{ number_format($s->total_due + $rowPenalty, $dp) }}</td>
        <td class="text-right" style="color:#065f46">{{ number_format($s->principal_paid + $s->interest_paid, $dp) }}</td>
        <td class="text-center">
            @if($s->status === 'paid') <span class="status-paid">PAID</span>
            @elseif($s->isOverdue()) <span class="status-overdue">OVERDUE</span>
            @elseif($s->status === 'partial') <span class="status-partial">PARTIAL</span>
            @else <span class="status-pending">PENDING</span>
            @endif
        </td>
    </tr>
    @endforeach
    </tbody>
    <tfoot>
        <tr style="background:#0f2444;color:#fff;font-weight:bold;">
            <td colspan="2" style="padding:6px 8px;font-size:10px;">TOTALS</td>
            <td class="text-right" style="padding:6px 8px;">{{ number_format($loan->schedules->sum('principal_due'), $dp) }}</td>
            <td class="text-right" style="padding:6px 8px;">{{ number_format($loan->schedules->sum('interest_due'), $dp) }}</td>
            <td class="text-right" style="padding:6px 8px;">{{ $schTotalPenalty > 0 ? number_format($schTotalPenalty, $dp) : '—' }}</td>
            <td class="text-right" style="padding:6px 8px;">{{ number_format($loan->schedules->sum('total_due') + $schTotalPenalty, $dp) }}</td>
            <td class="text-right" style="padding:6px 8px;">{{ number_format($loan->schedules->sum('principal_paid') + $loan->schedules->sum('interest_paid'), $dp) }}</td>
            <td></td>
        </tr>
    </tfoot>
</table>
@endif

<div class="footer clearfix">
    <div style="float:right">Page 1</div>
    <div>This statement is computer generated. @php $_logo = \App\Models\SystemSetting::get('org_logo'); @endphp@if($_logo)<img src="{{ public_path($_logo) }}" style="height:32px;max-width:160px;object-fit:contain;vertical-align:middle">@else{{ \App\Models\SystemSetting::get('org_name', 'ElTech Finance') }}@endif</div>
</div>

</div>
</body>
</html>
