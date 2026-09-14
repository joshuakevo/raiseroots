<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #1a1a1a; }
    .header { background: #0f2444; color: #fff; padding: 14px 20px; margin-bottom: 14px; }
    .header h1 { font-size: 15px; font-weight: bold; }
    .header p { font-size: 9px; opacity: 0.7; margin-top: 2px; }
    .header-right { float: right; text-align: right; }
    .clearfix::after { content: ''; display: table; clear: both; }
    .summary-row { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
    .summary-row td { border: 1px solid #e5e7eb; padding: 6px 10px; text-align: center; }
    .summary-row .lbl { font-size: 8px; color: #6b7280; display: block; }
    .summary-row .val { font-size: 11px; font-weight: bold; color: #0f2444; }
    table.main { width: 100%; border-collapse: collapse; }
    table.main thead th { background: #0f2444; color: #fff; padding: 5px 6px; font-size: 8px; text-transform: uppercase; letter-spacing: 0.05em; text-align: left; }
    table.main thead th.r { text-align: right; }
    table.main tbody td { padding: 5px 6px; border-bottom: 1px solid #f3f4f6; font-size: 9px; }
    table.main tbody td.r { text-align: right; }
    table.main tbody tr:nth-child(even) td { background: #f9fafb; }
    table.main tfoot td { padding: 6px; font-weight: bold; font-size: 9px; background: #e5e7eb; border-top: 2px solid #0f2444; }
    table.main tfoot td.r { text-align: right; }
    .text-muted { color: #6b7280; }
    .text-danger { color: #dc2626; }
    .text-primary { color: #2563eb; }
    .text-success { color: #16a34a; }
    .text-info { color: #0891b2; }
    .text-warning { color: #d97706; }
    .footer { margin-top: 12px; padding-top: 8px; border-top: 1px solid #e5e7eb; font-size: 8px; color: #9ca3af; }
</style>
</head>
<body>
<div class="header clearfix">
    <div class="header-right">
        <div>Generated: {{ now()->format('d M Y H:i') }}</div>
        @isset($asOf)<div>As of {{ $asOf }}</div>@endisset
    </div>
    <h1>@php $_logo = \App\Models\SystemSetting::get('org_logo'); @endphp@if($_logo)<img src="{{ public_path($_logo) }}" style="height:32px;max-width:160px;object-fit:contain;vertical-align:middle">@else{{ \App\Models\SystemSetting::get('org_name', 'ElTech Finance') }}@endif — Member Summary Statement</h1>
    <p>Outstanding loans and relationship manager per member</p>
</div>

{{-- Totals summary --}}
<table class="summary-row">
    <tr>
        <td><span class="lbl">Members</span><span class="val">{{ $members->count() }}</span></td>
        <td><span class="lbl">Loan Principal</span><span class="val text-danger">{{ number_format($totals['loan_principal'], 0) }}</span></td>
        <td><span class="lbl">Loan Interest</span><span class="val text-warning">{{ number_format($totals['loan_interest'], 0) }}</span></td>
    </tr>
</table>

<table class="main">
    <thead>
        <tr>
            <th>#</th>
            <th>Member Name</th>
            <th>Client #</th>
            <th class="r">Loan Principal</th>
            <th class="r">Loan Interest</th>
            <th>Relationship Manager</th>
        </tr>
    </thead>
    <tbody>
    @foreach($members as $i => $row)
    <tr>
        <td class="text-muted">{{ $i + 1 }}</td>
        <td><strong>{{ $row->client->name }}</strong></td>
        <td class="text-muted">{{ $row->client->client_number }}</td>
        <td class="r {{ $row->loan_principal > 0 ? 'text-danger' : 'text-muted' }}">
            {{ $row->loan_principal > 0 ? number_format($row->loan_principal, 0) : '—' }}
        </td>
        <td class="r {{ $row->loan_interest > 0 ? 'text-warning' : 'text-muted' }}">
            {{ $row->loan_interest > 0 ? number_format($row->loan_interest, 0) : '—' }}
        </td>
        <td class="text-muted">{{ $row->client->relationshipManager?->name ?? '—' }}</td>
    </tr>
    @endforeach
    </tbody>
    <tfoot>
        <tr>
            <td colspan="3">TOTALS ({{ $members->count() }} members)</td>
            <td class="r text-danger">{{ number_format($totals['loan_principal'], 0) }}</td>
            <td class="r text-warning">{{ number_format($totals['loan_interest'], 0) }}</td>
            <td></td>
        </tr>
    </tfoot>
</table>

<div class="footer">
    Loans = active outstanding balances
</div>
</body>
</html>
