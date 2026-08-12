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
    .summary-row { width:100%; border-collapse:collapse; margin-bottom:10px; }
    .summary-row td { border:1px solid #e5e7eb; padding:5px 8px; text-align:center; }
    .summary-row .lbl { font-size:7px; color:#6b7280; display:block; }
    .summary-row .val { font-size:10px; font-weight:bold; color:#0f2444; }
    table { width:100%; border-collapse:collapse; }
    thead th { background:#0f2444; color:#fff; padding:4px 5px; font-size:7px; text-transform:uppercase; text-align:left; }
    tbody td { padding:4px 5px; border-bottom:1px solid #f3f4f6; font-size:8px; }
    tbody tr:nth-child(even) td { background:#f9fafb; }
    .badge { padding:2px 4px; border-radius:3px; font-size:7px; color:#fff; }
    .badge-active      { background:#059669; }
    .badge-inactive    { background:#6b7280; }
    .badge-blacklisted { background:#dc2626; }
    .footer { margin-top:8px; padding-top:5px; border-top:1px solid #e5e7eb; font-size:7px; color:#9ca3af; }
</style>
</head>
<body>
<div class="header clearfix">
    <div class="header-right">
        <div>Generated: {{ now()->format('d M Y H:i') }}</div>
    </div>
    <h1>@php $_logo = \App\Models\SystemSetting::get('org_logo'); @endphp@if($_logo)<img src="{{ public_path($_logo) }}" style="height:32px;max-width:160px;object-fit:contain;vertical-align:middle">@else{{ \App\Models\SystemSetting::get('org_name', 'ElTech Finance') }}@endif — Clients List</h1>
    <p>All client accounts summary</p>
</div>

<table class="summary-row">
    <tr>
        <td><span class="lbl">Total Clients</span><span class="val">{{ $summary['total'] }}</span></td>
        <td><span class="lbl">Active</span><span class="val" style="color:#059669">{{ $summary['active'] }}</span></td>
        <td><span class="lbl">Inactive</span><span class="val">{{ $summary['inactive'] }}</span></td>
        <td><span class="lbl">Blacklisted</span><span class="val" style="color:#dc2626">{{ $summary['blacklisted'] }}</span></td>
        <td><span class="lbl">Groups</span><span class="val">{{ $summary['groups'] }}</span></td>
    </tr>
</table>

<table>
    <thead>
        <tr>
            <th>Client Number</th>
            <th>Name</th>
            <th>Type</th>
            <th>Phone</th>
            <th>Email</th>
            <th>Branch</th>
            <th>Joining Date</th>
            <th>Status</th>
        </tr>
    </thead>
    <tbody>
    @forelse($clients as $client)
    <tr>
        <td style="font-family:monospace;font-size:7px">{{ $client->client_number }}</td>
        <td>{{ $client->name }}</td>
        <td style="font-size:7px">{{ ($client->client_type ?? 'individual') === 'group' ? 'Group' : 'Individual' }}</td>
        <td style="font-size:7px">{{ $client->phone ?? '—' }}</td>
        <td style="font-size:7px">{{ $client->email ?? '—' }}</td>
        <td style="font-size:7px">{{ $client->branch?->name ?? '—' }}</td>
        <td style="font-size:7px">{{ $client->joining_date?->format('d M Y') ?? '—' }}</td>
        <td><span class="badge badge-{{ $client->status }}">{{ ucfirst($client->status) }}</span></td>
    </tr>
    @empty
    <tr><td colspan="8" style="text-align:center;color:#9ca3af;padding:10px">No clients found.</td></tr>
    @endforelse
    </tbody>
</table>
<div class="footer">Printed by {{ auth()->user()->name ?? 'System' }} &bull; {{ now()->format('d M Y H:i') }}</div>
</body>
</html>
