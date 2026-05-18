@extends('layouts.admin')

@section('page_title', 'System Logs')

@section('content')
    @php
        $totalRows = collect($sections)->sum(fn ($section) => $section['rows']->count());
        $navItems = collect($sections)
            ->map(fn ($section, $index) => [
                'id' => 'system-log-section-' . $index,
                'label' => Str::of($section['title'])
                    ->replace(['Recent ', ' Logs', ' Changes', ' Actions', ' Source Records', ' Status'], '')
                    ->trim()
                    ->toString(),
                'count' => $section['rows']->count(),
            ])
            ->prepend([
                'id' => 'system-log-all',
                'label' => 'Overview',
                'count' => $totalRows,
            ]);
    @endphp

    <div class="system-logs-page mx-auto w-full max-w-[1500px]">
        <style>
            .system-logs-page {
                color: #0f172a;
            }

            .sl-tabs {
                display: flex;
                gap: 1.85rem;
                overflow-x: auto;
                border-bottom: 1px solid #cfd8e3;
                padding: 0 0 0.15rem;
            }

            .sl-tab {
                position: relative;
                display: inline-flex;
                min-height: 3.15rem;
                flex: 0 0 auto;
                align-items: center;
                gap: 0.45rem;
                color: #475569;
                font-size: 0.95rem;
                font-weight: 600;
                text-decoration: none;
            }

            .sl-tab:hover,
            .sl-tab.is-active {
                color: #0f172a;
            }

            .sl-tab.is-active::after {
                position: absolute;
                right: 0;
                bottom: -0.15rem;
                left: 0;
                height: 0.25rem;
                border-radius: 999px 999px 0 0;
                background: #0f172a;
                content: "";
            }

            .sl-tab-count {
                border-radius: 999px;
                background: #f0f7ff;
                padding: 0.05rem 0.42rem;
                color: #0033b8;
                font-size: 0.72rem;
                font-weight: 800;
                font-variant-numeric: tabular-nums;
            }

            .sl-hero {
                padding: 3rem 0 1.5rem;
            }

            .sl-title {
                margin: 0;
                font-size: clamp(1.85rem, 3vw, 2.55rem);
                font-weight: 800;
                letter-spacing: -0.04em;
                line-height: 1.05;
                color: #0f172a;
            }

            .sl-subtitle {
                margin: 0.65rem 0 0;
                max-width: 54rem;
                color: #475569;
                font-size: 1rem;
                line-height: 1.65;
            }

            .sl-toolbar {
                display: grid;
                grid-template-columns: minmax(0, 1fr);
                gap: 1rem;
                padding-bottom: 1.45rem;
            }

            .sl-search {
                display: flex;
                min-height: 3.65rem;
                align-items: center;
                gap: 0.85rem;
                border: 1px solid #0f172a;
                border-radius: 999px;
                background: #ffffff;
                padding: 0 1.35rem;
            }

            .sl-search input {
                min-width: 0;
                flex: 1 1 auto;
                border: 0;
                background: transparent;
                color: #0f172a;
                font-size: 1rem;
                outline: none;
                box-shadow: none !important;
            }

            .sl-filter-row {
                display: flex;
                flex-wrap: wrap;
                align-items: center;
                justify-content: space-between;
                gap: 0.8rem;
            }

            .sl-filter-group {
                display: flex;
                flex-wrap: wrap;
                gap: 0.55rem;
            }

            .sl-chip {
                display: inline-flex;
                min-height: 2.35rem;
                align-items: center;
                gap: 0.55rem;
                border: 1px solid #d8dee8;
                border-radius: 999px;
                background: #ffffff;
                padding: 0 0.95rem;
                color: #0f172a;
                font-size: 0.9rem;
                font-weight: 600;
                white-space: nowrap;
            }

            .sl-chip.is-active {
                border-color: #0f172a;
                background: #0f172a;
                color: #ffffff;
            }

            .sl-sort {
                color: #0f172a;
                font-size: 0.95rem;
                font-weight: 700;
                white-space: nowrap;
            }

            .sl-section {
                border-top: 1px solid #d8dee8;
                padding: 1.65rem 0 0;
            }

            .sl-section + .sl-section {
                margin-top: 1.6rem;
            }

            .sl-section-head {
                display: flex;
                flex-wrap: wrap;
                align-items: end;
                justify-content: space-between;
                gap: 0.9rem;
                padding: 0 0 1rem;
            }

            .sl-section-title {
                margin: 0;
                font-size: 1.25rem;
                font-weight: 800;
                letter-spacing: -0.025em;
                color: #0f172a;
            }

            .sl-section-desc {
                margin: 0.35rem 0 0;
                max-width: 58rem;
                color: #475569;
                font-size: 0.95rem;
                line-height: 1.55;
            }

            .sl-shown {
                color: #475569;
                font-size: 0.76rem;
                font-weight: 800;
                letter-spacing: 0.16em;
                text-transform: uppercase;
                white-space: nowrap;
            }

            .sl-note {
                margin-bottom: 1rem;
                border-left: 3px solid #ffb800;
                background: #ffffe0;
                padding: 0.85rem 1rem;
                color: #7a4a00;
                font-size: 0.9rem;
                font-weight: 600;
            }

            .sl-table-wrap {
                overflow-x: auto;
                background: #ffffff;
            }

            .sl-table {
                min-width: 100%;
                border-collapse: collapse;
                font-size: 0.92rem;
            }

            .sl-table thead {
                border-top: 1px solid #eef2f7;
                border-bottom: 1px solid #d8dee8;
            }

            .sl-table th {
                padding: 0.8rem 1rem;
                color: #475569;
                font-size: 0.72rem;
                font-weight: 800;
                letter-spacing: 0.1em;
                text-align: left;
                text-transform: uppercase;
                white-space: nowrap;
            }

            .sl-table td {
                border-bottom: 1px solid #d8dee8;
                padding: 1rem;
                vertical-align: top;
            }

            .sl-table tbody tr:hover {
                background: #f8fafc;
            }

            .sl-primary {
                color: #0f172a;
                font-weight: 800;
            }

            .sl-muted {
                color: #475569;
            }

            .sl-source {
                display: inline-flex;
                align-items: center;
                border-radius: 0.45rem;
                background: #eef2f7;
                padding: 0.35rem 0.55rem;
                color: #334155;
                font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
                font-size: 0.72rem;
                font-weight: 800;
                white-space: nowrap;
            }

            .sl-status-stack {
                display: grid;
                gap: 0.45rem;
            }

            .sl-empty,
            .sl-no-results {
                padding: 2.2rem 1rem;
                text-align: center;
                color: #64748b;
                font-size: 0.95rem;
            }

            .sl-no-results[hidden] {
                display: none;
            }

            @media (max-width: 767px) {
                .sl-hero {
                    padding-top: 2rem;
                }

                .sl-tabs {
                    gap: 1.1rem;
                }

                .sl-table th,
                .sl-table td {
                    padding-right: 0.75rem;
                    padding-left: 0.75rem;
                }
            }
        </style>

        <nav id="system-log-all" class="sl-tabs tc-scrollbar" aria-label="System log sections">
            @foreach ($navItems as $index => $item)
                <a href="#{{ $item['id'] }}" class="sl-tab {{ $index === 0 ? 'is-active' : '' }}">
                    {{ $item['label'] }}
                    <span class="sl-tab-count">{{ $item['count'] }}</span>
                </a>
            @endforeach
        </nav>

        <header class="sl-hero">
            <h1 class="sl-title">System Logs ({{ $totalRows }})</h1>
            <p class="sl-subtitle">Operational records for waitlist changes, SMS attempts, automation, priority handling, table status, and analytics sources.</p>
        </header>

        <div class="sl-toolbar" data-system-log-controls>
            <label class="sl-search" for="system-log-search">
                <i class="fa-solid fa-magnifying-glass text-lg" aria-hidden="true"></i>
                <input id="system-log-search" type="search" placeholder="Search system logs" data-system-log-search autocomplete="off">
            </label>

            <div class="sl-filter-row">
                <div class="sl-filter-group" role="group" aria-label="Filter log sections">
                    <button type="button" class="sl-chip is-active" data-system-log-filter="all">
                        <i class="fa-solid fa-sliders text-[12px]" aria-hidden="true"></i>
                        All filters
                    </button>
                    @foreach ($sections as $index => $section)
                        <button type="button" class="sl-chip" data-system-log-filter="section-{{ $index }}">
                            {{ Str::of($section['title'])->replace(['Recent ', ' Logs', ' Changes', ' Actions', ' Source Records', ' Status'], '')->trim() }}
                        </button>
                    @endforeach
                </div>

                <div class="sl-sort">
                    Recently updated
                    <i class="fa-solid fa-caret-down ml-1 text-[12px]" aria-hidden="true"></i>
                </div>
            </div>
        </div>

        <div data-system-log-list>
            @foreach ($sections as $sectionIndex => $section)
                @php
                    $isAutomationSection = ($section['type'] ?? null) === 'automation';
                @endphp
                <section id="system-log-section-{{ $sectionIndex }}" class="sl-section" data-log-section="section-{{ $sectionIndex }}">
                    <div class="sl-section-head">
                        <div>
                            <h2 class="sl-section-title">{{ $section['title'] }}</h2>
                            <p class="sl-section-desc">{{ $section['description'] }}</p>
                        </div>
                        <span class="sl-shown">{{ $section['rows']->count() }} shown</span>
                    </div>

                    @if (!empty($section['note']))
                        <div class="sl-note">{{ $section['note'] }}</div>
                    @endif

                    <div class="sl-table-wrap">
                        <table class="sl-table">
                            <thead>
                                @if ($isAutomationSection)
                                    <tr>
                                        <th>Timestamp</th>
                                        <th>Task</th>
                                        <th>Status</th>
                                        <th>Affected record</th>
                                        <th>Message</th>
                                        <th>SMS result</th>
                                        <th>Source</th>
                                    </tr>
                                @else
                                    <tr>
                                        <th>Date/time</th>
                                        <th>Action</th>
                                        <th>Related record</th>
                                        <th>Status / result</th>
                                        <th>Source</th>
                                    </tr>
                                @endif
                            </thead>
                            <tbody>
                                @forelse ($section['rows'] as $row)
                                    @if ($isAutomationSection)
                                        @php
                                            $rowSearch = implode(' ', [
                                                optional($row['time'])->format('M d, Y g:i A'),
                                                $row['task'] ?? '',
                                                $row['task_key'] ?? '',
                                                $row['status_text'] ?? '',
                                                $row['affected'] ?? '',
                                                $row['message'] ?? '',
                                                $row['sms_result'] ?? '',
                                                $row['source'] ?? '',
                                            ]);
                                        @endphp
                                        <tr data-log-row data-log-text="{{ Str::lower($rowSearch) }}">
                                            <td class="whitespace-nowrap sl-muted">{{ optional($row['time'])->format('M d, Y g:i A') ?? 'No time' }}</td>
                                            <td class="min-w-[13rem]">
                                                <span class="sl-primary">{{ $row['task'] }}</span>
                                                <span class="mt-1 block font-mono text-[11px] text-slate-500">{{ $row['task_key'] }}</span>
                                            </td>
                                            <td class="whitespace-nowrap">
                                                <x-status-badge :status="$row['status_badge']" size="xs" />
                                                <span class="ml-1.5 sl-muted">{{ $row['status_text'] }}</span>
                                            </td>
                                            <td class="max-w-xs sl-muted">{{ $row['affected'] }}</td>
                                            <td class="max-w-sm sl-muted">{{ $row['message'] }}</td>
                                            <td class="max-w-sm">
                                                <div class="sl-status-stack">
                                                    <x-status-badge :status="$row['sms_status']" size="xs" />
                                                    <span class="sl-muted">{{ $row['sms_result'] }}</span>
                                                </div>
                                            </td>
                                            <td><span class="sl-source">{{ $row['source'] }}</span></td>
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
                                            $rowSearch = implode(' ', [
                                                optional($row['time'])->format('M d, Y g:i A'),
                                                $row['action'] ?? '',
                                                $row['related'] ?? '',
                                                $row['status'] ?? '',
                                                $row['source'] ?? '',
                                            ]);
                                        @endphp
                                        <tr data-log-row data-log-text="{{ Str::lower($rowSearch) }}">
                                            <td class="whitespace-nowrap sl-muted">{{ optional($row['time'])->format('M d, Y g:i A') ?? 'No time' }}</td>
                                            <td><span class="sl-primary">{{ $row['action'] }}</span></td>
                                            <td class="max-w-xs sl-muted">{{ $row['related'] }}</td>
                                            <td class="max-w-md">
                                                <div class="sl-status-stack">
                                                    @if ($logBadges->isNotEmpty())
                                                        <div class="flex flex-wrap gap-1.5">
                                                            @foreach ($logBadges as $status)
                                                                <x-status-badge :status="$status" size="xs" />
                                                            @endforeach
                                                        </div>
                                                    @endif
                                                    <span class="sl-muted">{{ $row['status'] }}</span>
                                                </div>
                                            </td>
                                            <td><span class="sl-source">{{ $row['source'] }}</span></td>
                                        </tr>
                                    @endif
                                @empty
                                    <tr>
                                        <td colspan="{{ $isAutomationSection ? 7 : 5 }}" class="sl-empty">
                                            No log records found yet for this section.
                                        </td>
                                    </tr>
                                @endforelse
                                <tr class="sl-no-results" data-log-empty hidden>
                                    <td colspan="{{ $isAutomationSection ? 7 : 5 }}">No logs match the current search.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            @endforeach
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (() => {
            const root = document.querySelector('.system-logs-page');
            if (!root) return;

            const search = root.querySelector('[data-system-log-search]');
            const filterButtons = Array.from(root.querySelectorAll('[data-system-log-filter]'));
            const sections = Array.from(root.querySelectorAll('[data-log-section]'));
            let activeFilter = 'all';

            const applyFilters = () => {
                const query = (search?.value || '').trim().toLowerCase();

                sections.forEach((section) => {
                    const sectionKey = section.getAttribute('data-log-section');
                    const sectionVisible = activeFilter === 'all' || activeFilter === sectionKey;
                    let visibleRows = 0;

                    section.querySelectorAll('[data-log-row]').forEach((row) => {
                        const rowMatches = !query || (row.getAttribute('data-log-text') || '').includes(query);
                        const show = sectionVisible && rowMatches;
                        row.hidden = !show;
                        if (show) visibleRows += 1;
                    });

                    const empty = section.querySelector('[data-log-empty]');
                    if (empty) empty.hidden = !sectionVisible || visibleRows > 0;
                    section.hidden = !sectionVisible;
                });
            };

            search?.addEventListener('input', applyFilters);

            filterButtons.forEach((button) => {
                button.addEventListener('click', () => {
                    activeFilter = button.getAttribute('data-system-log-filter') || 'all';
                    filterButtons.forEach((item) => item.classList.toggle('is-active', item === button));
                    applyFilters();
                });
            });
        })();
    </script>
@endpush
