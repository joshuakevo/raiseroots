<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
    * { margin:0; padding:0; box-sizing:border-box; }
    body { font-family: DejaVu Sans, sans-serif; font-size:8px; color:#1a1a1a; }
    .header { background:#0f2444; color:#fff; padding:10px 16px; margin-bottom:10px; }
    .header h1 { font-size:13px; font-weight:bold; }
    .header p  { font-size:8px; opacity:0.7; margin-top:2px; }
    .header-right { float:right; text-align:right; font-size:8px; }
    .clearfix::after { content:''; display:table; clear:both; }
    table { width:100%; border-collapse:collapse; }
    thead th { background:#0f2444; color:#fff; padding:4px 5px; font-size:7px; text-transform:uppercase; text-align:left; }
    thead th.r { text-align:right; }
    tbody td { padding:4px 5px; border-bottom:1px solid #f3f4f6; font-size:8px; }
    tbody td.r { text-align:right; }
    tbody tr:nth-child(even) td { background:#f9fafb; }
    .text-danger { color:#dc2626; }
    .footer { margin-top:8px; padding-top:5px; border-top:1px solid #e5e7eb; font-size:7px; color:#9ca3af; }
</style>
</head>
<body>
<div class="header clearfix">
    <div class="header-right">
        <div>Generated: {{ now()->format('d M Y H:i') }}</div>
        @if($fromDate)<div>Period: {{ $fromDate }} to {{ $toDate }}</div>@else<div>To {{ $toDate }}</div>@endif
    </div>
    <h1>@php $_logo = \App\Models\SystemSetting::get('org_logo'); @endphp@if($_logo)<img src="{{ public_path($_logo) }}" style="height:32px;max-width:160px;object-fit:contain;vertical-align:middle">@else{{ \App\Models\SystemSetting::get('org_name', 'ElTech Finance') }}@endif — General Ledger</h1>
    <p>{{ $account->account_code }} — {{ $account->account_name }}</p>
</div>
<table>
    <thead>
        <tr>
            <th style="width:70px">Date</th>
            <th style="width:90px">Reference</th>
            <th>Client</th>
            <th>Description</th>
            <th class="r" style="width:90px">Debit</th>
            <th class="r" style="width:90px">Credit</th>
            <th class="r" style="width:90px">Balance</th>
        </tr>
    </thead>
    <tbody>
    @forelse($rows as $row)
    <tr>
        <td>{{ $row['line']->transaction->date->format('d M Y') }}</td>
        <td style="font-family:monospace;font-size:7px">{{ $row['line']->transaction->reference }}</td>
        <td>{{ $row['client'] ? $row['client']->name . ' (' . $row['client']->client_number . ')' : '—' }}</td>
        <td>{{ $row['line']->description ?? $row['line']->transaction->description }}</td>
        <td class="r">{{ $row['line']->debit > 0 ? number_format($row['line']->debit, 2) : '' }}</td>
        <td class="r">{{ $row['line']->credit > 0 ? number_format($row['line']->credit, 2) : '' }}</td>
        <td class="r {{ $row['balance'] < 0 ? 'text-danger' : '' }}"><strong>{{ number_format($row['balance'], 2) }}</strong></td>
    </tr>
    @empty
    <tr><td colspan="7" style="text-align:center;color:#9ca3af;padding:10px">No ledger entries.</td></tr>
    @endforelse
    </tbody>
</table>
<div class="footer">Printed by {{ auth()->user()->name ?? 'System' }} &bull; {{ now()->format('d M Y H:i') }}</div>
</body>
</html>
