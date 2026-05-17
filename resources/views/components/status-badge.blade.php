@props([
    'status',
    'label' => null,
    'size' => 'sm',
])

@php
    $rawStatus = strtolower(trim((string) $status));
    $normalized = match ($rawStatus) {
        'available' => 'free',
        'none' => 'standard',
        'preg' => 'pregnant',
        'sc' => 'senior',
        default => $rawStatus,
    };

    $display = $label ?? match ($normalized) {
        'free' => 'FREE',
        'reserved' => 'RESERVED',
        'occupied' => 'OCCUPIED',
        'waiting' => 'WAITING',
        'notified' => 'NOTIFIED',
        'seated' => 'SEATED',
        'priority' => 'PRIORITY',
        'pwd' => 'PWD',
        'senior' => 'SENIOR',
        'pregnant' => 'PREGNANT',
        'standard' => 'REGULAR',
        'cancelled' => 'CANCELLED',
        'completed' => 'COMPLETED',
        'active' => 'ACTIVE',
        'pending' => 'PENDING',
        'paid' => 'PAID',
        'failed' => 'FAILED',
        'pending_verification' => 'VERIFY',
        'cleaning' => 'CLEANING',
        default => strtoupper(str_replace(['_', '-'], ' ', $rawStatus ?: 'status')),
    };

    $tone = match ($normalized) {
        'free', 'seated', 'completed', 'paid' => 'border-emerald-200 bg-emerald-50 text-emerald-800',
        'waiting', 'standard', 'pending' => 'border-slate-200 bg-slate-50 text-slate-700',
        'notified', 'pending_verification' => 'border-amber-200 bg-amber-50 text-amber-900',
        'reserved' => 'border-blue-200 bg-blue-50 text-blue-800',
        'occupied' => 'border-orange-200 bg-orange-50 text-orange-800',
        'priority' => 'border-rose-200 bg-rose-50 text-rose-800',
        'pwd' => 'border-violet-200 bg-violet-50 text-violet-800',
        'senior' => 'border-sky-200 bg-sky-50 text-sky-800',
        'pregnant' => 'border-pink-200 bg-pink-50 text-pink-800',
        'cancelled', 'failed' => 'border-red-200 bg-red-50 text-red-700',
        'active' => 'border-blue-200 bg-blue-50 text-blue-800',
        'cleaning' => 'border-slate-200 bg-slate-100 text-slate-700',
        default => 'border-slate-200 bg-slate-50 text-slate-700',
    };

    $sizes = [
        'xs' => 'px-1.5 py-0.5 text-[10px] gap-1',
        'sm' => 'px-2 py-0.5 text-[11px] gap-1',
        'md' => 'px-2.5 py-1 text-xs gap-1.5',
    ];

    $sizeClass = $sizes[$size] ?? $sizes['sm'];

    $dot = match ($normalized) {
        'free', 'seated', 'completed', 'paid' => 'bg-emerald-500',
        'waiting', 'standard', 'pending' => 'bg-slate-400',
        'notified', 'pending_verification' => 'bg-amber-500',
        'reserved' => 'bg-blue-500',
        'occupied' => 'bg-orange-500',
        'priority' => 'bg-rose-500',
        'pwd' => 'bg-violet-500',
        'senior' => 'bg-sky-500',
        'pregnant' => 'bg-pink-500',
        'cancelled', 'failed' => 'bg-red-500',
        'active' => 'bg-blue-500',
        'cleaning' => 'bg-slate-500',
        default => 'bg-slate-400',
    };
@endphp

<span {{ $attributes->class([
    'inline-flex shrink-0 items-center rounded-full border font-bold uppercase leading-none tracking-wide',
    $tone,
    $sizeClass,
]) }}>
    <span class="h-1.5 w-1.5 rounded-full {{ $dot }}" aria-hidden="true"></span>
    <span>{{ $display }}</span>
</span>
