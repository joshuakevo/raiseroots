@php
    $orgName    = \App\Models\SystemSetting::get('org_name', 'ElTech Finance');
    $orgAddress = \App\Models\SystemSetting::get('org_address');
    $orgPhone   = \App\Models\SystemSetting::get('org_phone');
    $orgEmail   = \App\Models\SystemSetting::get('org_email');
    $orgLogo    = \App\Models\SystemSetting::get('org_logo');
    $currency   = \App\Models\SystemSetting::get('currency', 'UGX');
    $isReversed = $transaction->isReversed() || $transaction->isReversal();
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt {{ $transaction->reference }} — {{ $orgName }}</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; background: #eef1f6; font-family: 'Segoe UI', system-ui, sans-serif; color: #1f2937; }
        .toolbar { max-width: 560px; margin: 20px auto 0; display: flex; justify-content: flex-end; gap: 8px; padding: 0 16px; }
        .btn { border: 1px solid #d1d5db; background: #fff; border-radius: 8px; padding: 8px 14px; font-size: 14px; cursor: pointer; color: #1f2937; text-decoration: none; }
        .btn-primary { background: #2563eb; border-color: #2563eb; color: #fff; }
        .receipt { position: relative; max-width: 560px; margin: 12px auto 32px; background: #fff; border-radius: 12px; box-shadow: 0 6px 24px rgba(15,36,68,.12); padding: 28px 30px; overflow: hidden; }
        .head { display: flex; gap: 14px; align-items: center; border-bottom: 2px solid #0f2444; padding-bottom: 14px; }
        .head img { width: 58px; height: 58px; object-fit: contain; }
        .org { font-weight: 700; font-size: 18px; color: #0f2444; }
        .org-sub { font-size: 12px; color: #6b7280; line-height: 1.45; }
        .title { display: flex; justify-content: space-between; align-items: baseline; margin: 18px 0 12px; }
        .title h1 { margin: 0; font-size: 20px; letter-spacing: .12em; color: #0f2444; }
        .meta { font-size: 13px; text-align: right; color: #374151; line-height: 1.5; }
        .meta strong { font-family: ui-monospace, Consolas, monospace; }
        .party { background: #f8fafc; border: 1px solid #e5e7eb; border-radius: 8px; padding: 10px 14px; font-size: 13px; margin-bottom: 14px; display: grid; grid-template-columns: 1fr 1fr; gap: 6px 16px; }
        .party .lbl { color: #6b7280; font-size: 11px; text-transform: uppercase; letter-spacing: .04em; }
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        th { text-align: left; font-size: 11px; text-transform: uppercase; letter-spacing: .04em; color: #6b7280; border-bottom: 1px solid #e5e7eb; padding: 6px 0; }
        td { padding: 8px 0; border-bottom: 1px dashed #e5e7eb; }
        .r { text-align: right; font-variant-numeric: tabular-nums; }
        .total td { border-bottom: none; border-top: 2px solid #0f2444; font-weight: 700; font-size: 16px; padding-top: 10px; }
        .words { font-size: 12px; color: #374151; margin-top: 8px; font-style: italic; }
        .balance { margin-top: 12px; font-size: 13px; background: #ecfdf3; color: #065f46; border-radius: 8px; padding: 8px 12px; }
        .sign { display: flex; justify-content: space-between; gap: 24px; margin-top: 36px; font-size: 12px; color: #6b7280; }
        .sign div { flex: 1; border-top: 1px solid #9ca3af; padding-top: 4px; text-align: center; }
        .foot { margin-top: 18px; text-align: center; font-size: 11px; color: #9ca3af; }
        .void { position: absolute; top: 42%; left: 50%; transform: translate(-50%, -50%) rotate(-22deg); font-size: 64px; font-weight: 800; color: rgba(220,38,38,.16); letter-spacing: .1em; pointer-events: none; }
        @media print {
            @page { size: A5 portrait; margin: 10mm; }
            body { background: #fff; }
            .toolbar { display: none; }
            .receipt { box-shadow: none; margin: 0; max-width: none; padding: 0; border-radius: 0; }
        }
    </style>
</head>
<body>
<div class="toolbar">
    <a href="{{ route('transactions.show', $transaction) }}" class="btn"><i class="bi bi-arrow-left"></i> Back</a>
    <button class="btn btn-primary" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
</div>

<div class="receipt">
    @if($isReversed)<div class="void">REVERSED</div>@endif

    <div class="head">
        @if($orgLogo && file_exists(public_path($orgLogo)))<img src="{{ asset($orgLogo) }}" alt="">@endif
        <div>
            <div class="org">{{ $orgName }}</div>
            <div class="org-sub">
                {{ $orgAddress }}@if($orgAddress && ($orgPhone || $orgEmail))<br>@endif
                {{ $orgPhone }}{{ $orgPhone && $orgEmail ? ' · ' : '' }}{{ $orgEmail }}
            </div>
        </div>
    </div>

    <div class="title">
        <h1>RECEIPT</h1>
        <div class="meta">
            No. <strong>{{ $transaction->reference }}</strong><br>
            Date: {{ $transaction->date->format('d M Y') }}
        </div>
    </div>

    <div class="party">
        <div><div class="lbl">Received from</div><strong>{{ $client?->name ?? '—' }}</strong></div>
        <div><div class="lbl">Client No.</div>{{ $client?->client_number ?? '—' }}</div>
        @if($loan)
            <div><div class="lbl">Loan No.</div>{{ $loan->loan_number }}</div>
            <div><div class="lbl">Payment method</div>{{ $repayment ? ucfirst(str_replace('_', ' ', $repayment->payment_method ?? 'cash')) : '—' }}</div>
        @endif
        <div style="grid-column: 1 / -1"><div class="lbl">Being payment for</div>{{ $transaction->description }}</div>
    </div>

    <table>
        <thead><tr><th>Particulars</th><th class="r">Amount ({{ $currency }})</th></tr></thead>
        <tbody>
        @forelse($items as $item)
            <tr><td>{{ $item['label'] }}</td><td class="r">{{ number_format($item['amount'], $dp) }}</td></tr>
        @empty
            <tr><td colspan="2" style="color:#9ca3af">No amounts on this entry.</td></tr>
        @endforelse
        <tr class="total"><td>Total</td><td class="r">{{ $currency }} {{ number_format($total, $dp) }}</td></tr>
        </tbody>
    </table>
    <div class="words">{{ \App\Support\AmountInWords::make($total) }} {{ $currency === 'UGX' ? 'shillings' : $currency }} only.</div>

    @if($loanBalance !== null)
        <div class="balance"><i class="bi bi-info-circle"></i> Loan balance remaining: <strong>{{ $currency }} {{ number_format($loanBalance, $dp) }}</strong></div>
    @endif

    <div class="sign">
        <div>Received by: {{ $transaction->createdBy?->name }}</div>
        <div>Client signature</div>
    </div>

    <div class="foot">Printed {{ now()->format('d M Y H:i') }} · Thank you.</div>
</div>
</body>
</html>
