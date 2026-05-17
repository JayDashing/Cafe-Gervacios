@extends('layouts.admin')

@section('page_title', 'Bookings')

@section('panel_heading')
    <x-admin-panel-heading title="Bookings" />
@endsection

@section('content')
    @php
        $awaitingVerification = \App\Models\Booking::query()
            ->where('payment_status', 'pending_verification')
            ->where('payment_method', 'manual_qr')
            ->orderByDesc('created_at')
            ->get();
    @endphp

    <div class="tc-ios-card mb-8 overflow-hidden border-amber-200/80 ring-1 ring-amber-100/90 shadow-sm shadow-amber-900/5">
        <div class="border-b border-amber-200/70 bg-amber-50/80 px-4 py-4">
            <div class="flex flex-wrap items-center gap-2">
                <span class="inline-flex h-2 w-2 shrink-0 rounded-full bg-amber-500 shadow-[0_0_0_3px_rgba(245,158,11,0.25)]" aria-hidden="true"></span>
                <div>
                    <h2 class="text-base font-semibold text-amber-950">Awaiting Verification</h2>
                    <p class="mt-0.5 text-sm text-amber-900/70">Manual QR reference submissions pending payment verification.</p>
                </div>
            </div>
        </div>
        <div class="overflow-x-auto bg-white">
            <table class="min-w-full text-sm">
                <thead class="bg-amber-50/50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-amber-900/80">Guest Name</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-amber-900/80">Date &amp; Time</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-amber-900/80">Party Size</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-amber-900/80">Reference Number</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-amber-900/80">Expected Deposit</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-amber-900/80">Submitted At</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-amber-900/80">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-amber-100/90">
                    @forelse ($awaitingVerification as $booking)
                        <tr class="transition-colors odd:bg-white even:bg-amber-50/30 hover:bg-amber-50/60">
                            <td class="whitespace-nowrap px-4 py-3">
                                <div class="font-medium text-slate-800">{{ $booking->customer_name }}</div>
                                <div class="mt-1 flex flex-wrap gap-1.5">
                                    <x-status-badge :status="$booking->priority_type" size="xs" />
                                    <x-status-badge :status="$booking->payment_status" size="xs" />
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-slate-700">{{ $booking->booked_at?->format('M d, Y g:i A') ?? '—' }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-slate-800">{{ $booking->party_size }}</td>
                            <td class="whitespace-nowrap px-4 py-3 font-mono text-xs text-slate-700">{{ $booking->transaction_number ?? '—' }}</td>
                            <td class="whitespace-nowrap px-4 py-3 font-semibold text-slate-800">
                                @if ((int) $booking->deposit_amount > 0)
                                    ₱{{ number_format((int) $booking->deposit_amount) }}
                                @else
                                    <span class="font-normal text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-slate-600">{{ $booking->created_at->format('M d, Y g:i A') }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-right">
                                <div class="inline-flex flex-wrap items-center justify-end gap-2">
                                    <form method="POST" action="{{ route('admin.bookings.verify-payment', $booking) }}" class="m-0 inline">
                                        @csrf
                                        <button type="submit" class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-950 hover:bg-amber-100">Verify</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.bookings.reject-payment', $booking) }}" class="m-0 inline"
                                        onsubmit="return confirm('Reject this payment and notify the guest?');">
                                        @csrf
                                        <button type="submit" class="rounded-xl border border-red-200 bg-red-50 px-3 py-1.5 text-xs font-semibold text-red-900 hover:bg-red-100">Reject</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-10 text-center text-slate-500">
                                <i class="fa-solid fa-clipboard-check mb-2 block text-3xl text-amber-200"></i>
                                No bookings awaiting verification
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="tc-ios-card overflow-hidden">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-4 py-4">
            <div>
                <h2 class="text-base font-semibold text-slate-800">Booking history</h2>
                <p class="mt-0.5 text-sm text-slate-500">Last 50 reservations</p>
            </div>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50/90">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Ref</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Source</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Customer</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Email</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Party</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Table</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Ref. No.</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Status</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Payment</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Deposit</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Reservation</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-600">Created</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200/80">
                    @forelse ($recentBookings as $booking)
                        <tr class="transition-colors odd:bg-white even:bg-slate-50/50 hover:bg-slate-50">
                            <td class="whitespace-nowrap px-4 py-3 font-mono text-slate-800">{{ $booking->booking_ref }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-xs uppercase text-slate-600">
                                {{ $booking->source ?? 'website' }}<br>
                                <span class="normal-case text-slate-400">{{ $booking->device_type ?? '—' }}</span>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3">
                                <div class="font-medium text-slate-800">{{ $booking->customer_name }}</div>
                                <div class="text-xs text-slate-500">{{ $booking->customer_phone }}</div>
                                <div class="mt-1 flex flex-wrap gap-1.5">
                                    <x-status-badge :status="$booking->priority_type" size="xs" />
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-slate-600">{{ $booking->customer_email ?? '—' }}</td>
                            <td class="whitespace-nowrap px-4 py-3 font-medium text-slate-800">{{ $booking->party_size }}</td>
                            <td class="whitespace-nowrap px-4 py-3">
                                @if ($booking->table_id !== null)
                                    <span class="font-medium text-slate-800">{{ $booking->table->label }}</span>
                                @elseif ($booking->payment_status === 'paid')
                                    <span class="inline-flex items-center rounded-xl border border-yellow-200 bg-yellow-50 px-2.5 py-1 text-xs font-semibold text-yellow-900">Unassigned</span>
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 font-mono text-xs text-slate-600">{{ $booking->transaction_number ?? '—' }}</td>
                            <td class="whitespace-nowrap px-4 py-3">
                                <x-status-badge :status="$booking->status" size="md" />
                            </td>
                            <td class="whitespace-nowrap px-4 py-3">
                                <div class="flex flex-wrap items-center gap-2">
                                    @if ($booking->payment_status === 'paid')
                                        <x-status-badge status="paid" size="md" />
                                    @elseif ($booking->payment_method === 'manual_qr' && $booking->payment_status === 'pending_verification')
                                        <x-status-badge status="pending_verification" size="md" />
                                        <form method="POST" action="{{ route('admin.bookings.verify-payment', $booking) }}" class="m-0 inline">
                                            @csrf
                                            <button type="submit" class="rounded-xl border border-amber-200 bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-950 hover:bg-amber-100">Verify</button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.bookings.reject-payment', $booking) }}" class="m-0 inline"
                                            onsubmit="return confirm('Reject this payment and notify the guest?');">
                                            @csrf
                                            <button type="submit" class="rounded-xl border border-red-200 bg-red-50 px-2.5 py-1 text-xs font-semibold text-red-900 hover:bg-red-100">Reject</button>
                                        </form>
                                    @elseif ($booking->payment_status === 'pending')
                                        @if (! $booking->transaction_number)
                                            <span class="rounded-xl border border-slate-200 bg-slate-50 px-2.5 py-1 text-xs font-semibold text-slate-500">
                                                Unpaid
                                            </span>
                                        @else
                                            <x-status-badge status="pending" size="md" />
                                        @endif
                                    @elseif ($booking->payment_status === 'failed')
                                        <x-status-badge status="failed" size="md" />
                                    @else
                                        <span class="text-xs text-slate-500" title="{{ $booking->payment_status ?? '' }}">
                                            {{ $booking->payment_status ? ucfirst(str_replace('_', ' ', $booking->payment_status)) : '—' }}
                                        </span>
                                    @endif
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3">
                                @if ($booking->deposit_amount > 0)
                                    <span class="font-semibold text-slate-800">₱{{ number_format($booking->deposit_amount) }}</span>
                                    @if ($booking->paymongo_link_id)
                                        <a href="https://dashboard.paymongo.com/links/{{ $booking->paymongo_link_id }}"
                                            target="_blank"
                                            class="mt-0.5 block text-xs font-medium text-slate-700 hover:text-panel-primary hover:underline">
                                            {{ $booking->paymongo_link_id }}
                                        </a>
                                    @endif
                                @else
                                    <span class="text-slate-400">—</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-slate-600">{{ $booking->booked_at?->format('M d, Y g:i A') ?? '—' }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-slate-500">{{ $booking->created_at->diffForHumans() }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="12" class="px-4 py-10 text-center text-slate-500">
                                <i class="fa-solid fa-calendar-xmark mb-2 block text-3xl text-slate-300"></i>
                                No bookings found
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
