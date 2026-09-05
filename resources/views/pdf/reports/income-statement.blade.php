<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
    * { margin:0; padding:0; box-sizing:border-box; }
    body { font-family: DejaVu Sans, sans-serif; font-size:9px; color:#1a1a1a; }
    .header { background:#0f2444; color:#fff; padding:12px 18px; margin-bottom:12px; }
    .header h1 { font-size:14px; font-weight:bold; }
    .header p  { font-size:8px; opacity:0.7; margin-top:2px; }
    .header-right { float:right; text-align:right; font-size:8px; }
    .clearfix::after { content:''; display:table; clear:both; }
    .summary-row { width:100%; border-collapse:collapse; margin-bottom:14px; }
    .summary-row td { border:1px solid #e5e7eb; padding:6px 10px; text-align:center; }
    .summary-row .lbl { font-size:8px; color:#6b7280; display:block; }
    .summary-row .val { font-size:12px; font-weight:bold; }
    .section-title { font-size:10px; font-weight:bold; color:#0f2444; padding:6px 0 4px 0; border-bottom:2px solid #0f2444; margin-bottom:4px; }
    table { width:100%; border-collapse:collapse; margin-bottom:14px; }
    thead th { background:#374151; color:#fff; padding:5px 6px; font-size:8px; text-transform:uppercase; text-align:left; }
    thead th.r { text-align:right; }
    tbody td { padding:5px 6px; border-bottom:1px solid #f3f4f6; font-size:9px; }
    tbody td.r { text-align:right; }
    tbody tr:nth-child(even) td { background:#f9fafb; }
    tfoot td { padding:6px; font-weight:bold; font-size:9px; background:#e5e7eb; border-top:2px solid #374151; }
    tfoot td.r { text-align:right; }
    .group-row td { background:#f3f4f6; font-weight:bold; text-transform:uppercase; font-size:8px; color:#4b5563; }
    .net-income { border:2px solid #0f2444; padding:10px 14px; text-align:center; margin-top:8px; }
    .net-income .lbl { font-size:9px; color:#6b7280; }
    .net-income .val { font-size:16px; font-weight:bold; }
    .text-success { color:#059669; }
    .text-danger  { color:#dc2626; }
    .footer { margin-top:10px; padding-top:6px; border-top:1px solid #e5e7eb; font-size:7px; color:#9ca3af; }
</style>
</head>
<body>
<div class="header clearfix">
    <div class="header-right">
        <div>Generated: {{ now()->format('d M Y H:i') }}</div>
        <div>Period: {{ $fromDate }} to {{ $toDate }}</div>
    </div>
    <h1>@php $_logo = \App\Models\SystemSetting::get('org_logo'); @endphp@if($_logo)<img src="{{ public_path($_logo) }}" style="height:32px;max-width:160px;object-fit:contain;vertical-align:middle">@else{{ \App\Models\SystemSetting::get('org_name', 'ElTech Finance') }}@endif — Income Statement</h1>
    <p>Revenue and Expenses for the selected period</p>
</div>

<table class="summary-row">
    <tr>
        <td><span class="lbl">Total Revenue</span><span class="val text-success">{{ number_format($data['total_revenue'], 2) }}</span></td>
        <td><span class="lbl">Total Expenses</span><span class="val text-danger">{{ number_format($data['total_expense'], 2) }}</span></td>
        <td><span class="lbl">Net Income</span><span class="val {{ $data['net_income'] >= 0 ? 'text-success' : 'text-danger' }}">{{ number_format($data['net_income'], 2) }}</span></td>
    </tr>
</table>

<div class="section-title">Revenue</div>
<table>
    <thead><tr><th>Code</th><th>Account Name</th><th class="r">Amount</th></tr></thead>
    <tbody>
    @forelse($data['revenue_rows'] as $row)
    <tr>
        <td style="font-family:monospace">{{ $row['account']->account_code }}</td>
        <td>{{ $row['account']->account_name }}</td>
        <td class="r text-success">{{ number_format($row['balance'], 2) }}</td>
    </tr>
    @empty
    <tr><td colspan="3" style="text-align:center;color:#9ca3af">No revenue.</td></tr>
    @endforelse
    </tbody>
    <tfoot><tr><td colspan="2">Total Revenue</td><td class="r text-success">{{ number_format($data['total_revenue'], 2) }}</td></tr></tfoot>
</table>

<div class="section-title">Expenses</div>
<table>
    <thead><tr><th>Code</th><th>Account Name</th><th class="r">Amount</th></tr></thead>
    <tbody>
    @forelse($data['expense_groups'] as $group)
    <tr class="group-row">
        <td colspan="2">{{ $group['label'] }}</td>
        <td class="r">{{ number_format($group['subtotal'], 2) }}</td>
    </tr>
    @foreach($group['rows'] as $row)
    <tr>
        <td style="font-family:monospace">{{ $row['account']->account_code }}</td>
        <td>{{ $row['account']->account_name }}</td>
        <td class="r text-danger">{{ number_format($row['balance'], 2) }}</td>
    </tr>
    @endforeach
    @empty
    <tr><td colspan="3" style="text-align:center;color:#9ca3af">No expenses.</td></tr>
    @endforelse
    </tbody>
    <tfoot><tr><td colspan="2">Total Expenses</td><td class="r text-danger">{{ number_format($data['total_expense'], 2) }}</td></tr></tfoot>
</table>

<div class="net-income">
    <div class="lbl">Net Income (Revenue − Expenses)</div>
    <div class="val {{ $data['net_income'] >= 0 ? 'text-success' : 'text-danger' }}">{{ number_format($data['net_income'], 2) }}</div>
</div>

<div class="footer">Printed by {{ auth()->user()->name ?? 'System' }} &bull; {{ now()->format('d M Y H:i') }}</div>
</body>
</html>
