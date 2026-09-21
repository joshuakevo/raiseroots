@php
    [$cls, $label] = match($rating) {
        'strong' => ['success', 'Strong'],
        'watch'  => ['warning', 'Watch'],
        'risk'   => ['danger', 'At risk'],
        default  => ['secondary', 'No book yet'],
    };
@endphp
<span class="badge bg-{{ $cls }}-subtle text-{{ $cls === 'warning' ? 'warning-emphasis' : $cls }} border border-{{ $cls }}-subtle">{{ $label }}</span>
