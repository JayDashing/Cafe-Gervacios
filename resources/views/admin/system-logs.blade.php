@extends('layouts.admin')

@section('page_title', 'System Logs')

@section('panel_heading')
    <x-admin-panel-heading
        title="System Logs"
        subtitle="Operational records for waitlist, SMS, automation, priority, tables, and analytics." />
@endsection

@section('content')
    @php
        $summaryLabels = [
            'waitlist' => 'Waitlist records',
            'sms' => 'SMS logs',
            'automation' => 'Automation logs',
            'priority' => 'Priority entries',
            'tables' => 'Tables',
            'bookingsToday' => 'Bookings today',
        ];
    @endphp

    <div class="mx-auto flex w-full max-w-7xl flex-col gap-5">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
            @foreach ($summaryLabels as $key => $label)
                <div class="rounded-lg border border-panel-stroke bg-white px-4 py-3 shadow-sm">
                    <p class="text-[11px] font-bold uppercase tracking-[0.12em] text-slate-500">{{ $label }}</p>
                    <p class="mt-1 text-2xl font-semibold tabular-nums text-panel-primary">{{ $summary[$key] ?? 0 }}</p>
                </div>
            @endforeach
        </div>

        @foreach ($sections as $section)
            @php
                $isAutomationSection = ($section['type'] ?? null) === 'automation';
            @endphp
            <section class="overflow-hidden rounded-[14px] border border-panel-stroke bg-white shadow-[0_1px_3px_rgba(26,34,50,0.10)]">
                <div class="border-b border-panel-stroke bg-[#eef1f5] px-4 py-3 sm:px-5">
                    <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <h2 class="text-base font-semibold text-panel-primary">{{ $section['title'] }}</h2>
                            <p class="mt-0.5 text-sm text-[#5a6a7e]">{{ $section['description'] }}</p>
                        </div>
                        <span class="text-xs font-semibold uppercase tracking-[0.12em] text-[#5a6a7e]">
                            {{ $section['rows']->count() }} shown
                        </span>
                    </div>
                </div>

                @if (!empty($section['note']))
                    <div class="border-b border-amber-200 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-950 sm:px-5">
                        {{ $section['note'] }}
                    </div>
                @endif

                <div class="overflow-x-auto">
                    <table class="compact-table min-w-full text-sm">
                        <thead class="border-b border-panel-stroke bg-white">
                            @if ($isAutomationSection)
                                <tr>
                                    <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-[#5a6a7e]">Timestamp</th>
                                    <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-[#5a6a7e]">Task Name</th>
                                    <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-[#5a6a7e]">Status</th>
                                    <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-[#5a6a7e]">Affected Record</th>
                                    <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-[#5a6a7e]">Message</th>
                                    <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-[#5a6a7e]">SMS Log Result</th>
                                    <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-[#5a6a7e]">Source</th>
                                </tr>
                            @else
                                <tr>
                                    <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-[#5a6a7e]">Date/Time</th>
                                    <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-[#5a6a7e]">Action</th>
                                    <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-[#5a6a7e]">Related Record</th>
                                    <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-[#5a6a7e]">Status / Result</th>
                                    <th class="px-4 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-[#5a6a7e]">Source</th>
                                </tr>
                            @endif
                        </thead>
                        <tbody class="divide-y divide-[#c2cad6]/50">
                            @forelse ($section['rows'] as $row)
                                @if ($isAutomationSection)
                                    <tr class="transition-colors odd:bg-white even:bg-[#f8fafc] hover:bg-[#eef1f5]/70">
                                        <td class="whitespace-nowrap px-4 py-2.5 text-[#5a6a7e]">
                                            {{ optional($row['time'])->format('M d, Y g:i A') ?? 'No time' }}
                                        </td>
                                        <td class="min-w-[13rem] px-4 py-2.5">
                                            <span class="font-semibold text-panel-primary">{{ $row['task'] }}</span>
                                            <span class="mt-1 block font-mono text-[11px] text-[#64748b]">{{ $row['task_key'] }}</span>
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-2.5">
                                            <x-status-badge :status="$row['status_badge']" size="xs" />
                                            <span class="ml-1.5 text-[#5a6a7e]">{{ $row['status_text'] }}</span>
                                        </td>
                                        <td class="max-w-xs px-4 py-2.5 text-[#334155]">
                                            {{ $row['affected'] }}
                                        </td>
                                        <td class="max-w-sm px-4 py-2.5 text-[#5a6a7e]">
                                            {{ $row['message'] }}
                                        </td>
                                        <td class="max-w-sm px-4 py-2.5 text-[#5a6a7e]">
                                            <div class="mb-1">
                                                <x-status-badge :status="$row['sms_status']" size="xs" />
                                            </div>
                                            <span>{{ $row['sms_result'] }}</span>
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-2.5">
                                            <span class="inline-flex rounded-md bg-[#eef1f5] px-2 py-1 font-mono text-[11px] font-semibold text-[#475569]">
                                                {{ $row['source'] }}
                                            </span>
                                        </td>
                                    </tr>
                                @else
                                    @php
                                        $logText = strtolower(($row['action'] ?? '') . ' ' . ($row['related'] ?? '') . ' ' . ($row['status'] ?? ''));
                                        $logBadges = collect([
                                            'waiting',
                                            'notified',
                                            'seated',
                                            'priority',
                                            'pwd',
                                            'senior',
                                            'pregnant',
                                            'standard',
                                            'occupied',
                                            'available',
                                            'free',
                                            'reserved',
                                            'cancelled',
                                            'completed',
                                            'paid',
                                            'pending',
                                            'failed',
                                            'cleaning',
                                        ])->filter(fn ($status) => str_contains($logText, $status))->values();
                                    @endphp
                                    <tr class="transition-colors odd:bg-white even:bg-[#f8fafc] hover:bg-[#eef1f5]/70">
                                        <td class="whitespace-nowrap px-4 py-2.5 text-[#5a6a7e]">
                                            {{ optional($row['time'])->format('M d, Y g:i A') ?? 'No time' }}
                                        </td>
                                        <td class="px-4 py-2.5">
                                            <span class="font-semibold text-panel-primary">{{ $row['action'] }}</span>
                                        </td>
                                        <td class="max-w-xs px-4 py-2.5 text-[#334155]">
                                            {{ $row['related'] }}
                                        </td>
                                        <td class="max-w-md px-4 py-2.5 text-[#5a6a7e]">
                                            @if ($logBadges->isNotEmpty())
                                                <div class="mb-1.5 flex flex-wrap gap-1.5">
                                                    @foreach ($logBadges as $status)
                                                        <x-status-badge :status="$status" size="xs" />
                                                    @endforeach
                                                </div>
                                            @endif
                                            <span>{{ $row['status'] }}</span>
                                        </td>
                                        <td class="whitespace-nowrap px-4 py-2.5">
                                            <span class="inline-flex rounded-md bg-[#eef1f5] px-2 py-1 font-mono text-[11px] font-semibold text-[#475569]">
                                                {{ $row['source'] }}
                                            </span>
                                        </td>
                                    </tr>
                                @endif
                            @empty
                                <tr>
                                    <td colspan="{{ $isAutomationSection ? 7 : 5 }}" class="px-4 py-8 text-center text-sm text-[#5a6a7e]">
                                        No log records found yet for this section.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        @endforeach
    </div>
@endsection
