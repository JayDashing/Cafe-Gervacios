@props([
    'entry',
])

@php
    $type = strtolower((string) ($entry->priority_type ?? 'none'));
    $score = (int) ($entry->priority_score ?? 0);
    $isPwd = $type === 'pwd';
    $accessibleRequired = (bool) ($entry->needs_accessible ?? false);
    $label = match ($type) {
        'pwd' => 'PWD',
        'senior' => 'Senior',
        'pregnant' => 'Pregnant',
        default => 'Regular',
    };
@endphp

<div {{ $attributes->class(['flex flex-wrap items-center gap-1.5']) }}>
    <span class="inline-flex items-center gap-1 rounded-md border border-slate-200 bg-white px-2 py-1 text-[11px] font-semibold text-slate-700">
        <span class="text-slate-500">Priority</span>
        <x-status-badge :status="$type" :label="$label" size="xs" />
    </span>

    @if ($isPwd)
        <span
            class="inline-flex items-center gap-1 rounded-md border px-2 py-1 text-[11px] font-bold {{ $accessibleRequired ? 'border-sky-200 bg-sky-50 text-sky-900' : 'border-slate-200 bg-slate-50 text-slate-700' }}">
            <i class="fa-solid fa-wheelchair-move text-[10px]" aria-hidden="true"></i>
            {{ $accessibleRequired ? 'Accessible table required' : 'Standard table allowed' }}
        </span>
    @endif

    <details class="rounded-md border border-slate-200 bg-white px-2 py-1 text-[11px] font-semibold text-slate-600">
        <summary class="cursor-pointer list-none [&::-webkit-details-marker]:hidden">
            Details
        </summary>
        <span class="mt-1 block font-mono text-slate-950">Priority Score {{ $score }}</span>
    </details>
</div>
