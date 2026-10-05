{{-- Results of an ElTech Credit Registry lookup: loans this national ID holds at OTHER ElTech lenders. --}}
@if(!$result['ok'])
    <div class="alert alert-warning small mb-0"><i class="bi bi-exclamation-triangle me-1"></i>{{ $result['error'] }}</div>
@elseif(empty($result['records']))
    <div class="text-success small"><i class="bi bi-check-circle me-1"></i>No loans found at other ElTech lenders.</div>
@else
    @php
        $records    = collect($result['records']);
        $defaulted  = $records->where('status', 'defaulted')->count();
        $active     = $records->where('status', 'active')->count();
        $inArrears  = $records->where('status', 'active')->where('days_in_arrears', '>', 0)->count();
        $owedElsewhere = $records->whereIn('status', ['active', 'defaulted'])->sum('outstanding');
    @endphp
    <div class="d-flex flex-wrap gap-2 mb-2">
        @if($defaulted)<span class="badge bg-danger"><i class="bi bi-x-octagon me-1"></i>{{ $defaulted }} defaulted elsewhere</span>@endif
        @if($active)<span class="badge bg-warning text-dark">{{ $active }} active elsewhere</span>@endif
        @if($inArrears)<span class="badge bg-danger-subtle text-danger border border-danger-subtle">{{ $inArrears }} in arrears</span>@endif
        @if($owedElsewhere > 0)<span class="badge bg-light text-dark border">Owes {{ number_format($owedElsewhere, $dp) }} elsewhere</span>@endif
    </div>
    <div class="table-responsive">
        <table class="table table-sm mb-0 small align-middle">
            <thead class="table-light"><tr>
                <th>Lender</th><th>Client (as recorded there)</th><th>Status</th>
                <th class="text-end">Principal</th><th class="text-end">Outstanding</th>
                <th>Disbursed</th><th class="text-end">Days in Arrears</th><th>Updated</th>
            </tr></thead>
            <tbody>
            @foreach($records as $r)
                <tr>
                    <td class="fw-semibold">{{ $r['lender'] }}</td>
                    <td>
                        {{ $r['client_name'] ?: '—' }}
                        <span class="badge bg-light text-secondary border font-monospace fw-normal ms-1">{{ $r['national_id'] }}</span>
                    </td>
                    <td>
                        <span class="badge {{ ['defaulted' => 'bg-danger', 'active' => 'bg-success', 'closed' => 'bg-secondary'][$r['status']] ?? 'bg-light text-dark' }}">{{ ucfirst($r['status']) }}</span>
                    </td>
                    <td class="text-end">{{ number_format($r['principal'], $dp) }}</td>
                    <td class="text-end fw-semibold">{{ number_format($r['outstanding'], $dp) }}</td>
                    <td>{{ $r['disbursed_on'] ? \Carbon\Carbon::parse($r['disbursed_on'])->format('d M Y') : '—' }}</td>
                    <td class="text-end {{ $r['days_in_arrears'] > 0 ? 'text-danger fw-semibold' : '' }}">{{ $r['days_in_arrears'] }}</td>
                    <td class="text-muted">{{ \Carbon\Carbon::parse($r['updated_at'])->format('d M Y') }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endif
