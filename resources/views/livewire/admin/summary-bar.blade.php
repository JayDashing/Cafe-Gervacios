@php
    $hasPriority = $priorityWaiting > 0;
    $stats = [
        ['label' => 'Bookings', 'value' => $bookingsTodayTotal, 'meta' => 'Today'],
        ['label' => 'Waiting', 'value' => $partiesWaiting, 'meta' => $hasPriority ? $priorityWaiting . ' priority' : 'Now'],
        ['label' => 'Free tables', 'value' => $tablesFree, 'meta' => 'Available'],
        ['label' => 'Occupied', 'value' => $tablesOccupied, 'meta' => 'Seated'],
        ['label' => 'Walk-ins', 'value' => $walkInsTodayTotal, 'meta' => 'Today'],
    ];

    $channelRows = [
        [
            'label' => 'Website',
            'value' => $bookingsBySource['website'] ?? 0,
            'metric' => 'Bookings',
        ],
        [
            'label' => 'Staff Tablet',
            'value' => $queueBySource['staff'] ?? 0,
            'metric' => 'Queue',
        ],
    ];

    $queuePoints = [];
    $queueCount = count($queueLast7);
    foreach ($queueLast7 as $idx => $row) {
        $x = $queueCount <= 1 ? 50 : ($idx / ($queueCount - 1)) * 100;
        $y = 100 - ($maxQueue7 > 0 ? (((int) $row['count']) / $maxQueue7) * 82 : 0);
        $queuePoints[] = round($x, 2) . ',' . round(max(10, min(92, $y)), 2);
    }
    $queueLinePoints = implode(' ', $queuePoints);
    $queueAreaPoints = '0,100 ' . $queueLinePoints . ' 100,100';
@endphp

<div wire:poll.5s class="dash-panel-stack space-y-4">
    <style>
        .dash-panel-card {
            border: 1px solid #d8dee8;
            border-radius: 0.75rem;
            background: #ffffff;
            box-shadow: 0 1px 2px rgb(15 23 42 / 0.04);
        }

        .dash-panel-head {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: flex-end;
            gap: 0.75rem;
            border-bottom: 1px solid #e2e8f0;
            padding: 0.8rem 1.25rem;
        }

        .dash-panel-meta {
            margin: 0;
            color: #64748b;
            font-size: 0.78rem;
            font-weight: 600;
        }

        .dash-stat-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.75rem;
            padding: 1rem;
        }

        @media (min-width: 768px) {
            .dash-stat-grid {
                grid-template-columns: repeat(5, minmax(0, 1fr));
            }
        }

        .dash-stat-tile {
            min-width: 0;
            border: 1px solid #e2e8f0;
            border-radius: 0.75rem;
            background: #f8fafc;
            padding: 0.9rem 1rem;
        }

        .dash-stat-label {
            margin: 0;
            overflow: hidden;
            color: #64748b;
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-overflow: ellipsis;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .dash-stat-value {
            margin: 0.55rem 0 0;
            color: #0f172a;
            font-size: 1.9rem;
            font-weight: 700;
            line-height: 1;
            font-variant-numeric: tabular-nums;
        }

        .dash-stat-meta {
            margin: 0.35rem 0 0;
            color: #64748b;
            font-size: 0.82rem;
            font-weight: 500;
        }

        .dash-channel-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr);
            gap: 0.75rem;
            padding: 1rem;
        }

        @media (min-width: 768px) {
            .dash-channel-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        .dash-channel-tile {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            align-items: center;
            gap: 1rem;
            border: 1px solid #e2e8f0;
            border-radius: 0.75rem;
            background: #f8fafc;
            padding: 0.95rem 1rem;
        }

        .dash-channel-label {
            margin: 0;
            color: #0f172a;
            font-size: 0.95rem;
            font-weight: 700;
        }

        .dash-channel-metric {
            min-width: 3.75rem;
            margin: 0;
            text-align: right;
        }

        .dash-channel-metric strong {
            display: block;
            color: #0f172a;
            font-size: 1.35rem;
            font-weight: 700;
            line-height: 1;
            font-variant-numeric: tabular-nums;
        }

        .dash-channel-metric span {
            display: block;
            margin-top: 0.25rem;
            color: #64748b;
            font-size: 0.7rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .dash-chart-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr);
            gap: 1rem;
        }

        @media (min-width: 1024px) {
            .dash-chart-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        .dash-chart-card {
            padding: 1rem;
        }

        @media (min-width: 768px) {
            .dash-chart-card {
                padding: 1.15rem 1.25rem 1.25rem;
            }
        }

        .dash-chart-title {
            margin: 0;
            color: #0f172a;
            font-size: 1rem;
            font-weight: 700;
        }

        .dash-chart-body {
            margin-top: 0.9rem;
            min-height: 14rem;
            border: 1px solid #e2e8f0;
            border-radius: 0.75rem;
            background: #f8fafc;
            padding: 1rem 1rem 0.85rem;
        }

        .dash-bars {
            display: flex;
            height: 11.5rem;
            align-items: end;
            gap: 0.65rem;
        }

        .dash-bar-col {
            display: flex;
            min-width: 0;
            flex: 1;
            flex-direction: column;
            align-items: center;
            gap: 0.35rem;
        }

        .dash-bar-count {
            color: #64748b;
            font-size: 0.68rem;
            font-weight: 600;
            font-variant-numeric: tabular-nums;
        }

        .dash-bar {
            width: 100%;
            max-width: 2.5rem;
            min-height: 0.45rem;
            border-radius: 0.4rem 0.4rem 0 0;
            background: #0033b8;
        }

        .dash-chart-label,
        .dash-line-label {
            overflow: hidden;
            max-width: 100%;
            color: #7a8da8;
            font-size: 0.68rem;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .dash-line-labels {
            display: flex;
            gap: 0.25rem;
            justify-content: space-between;
            margin-top: 0.6rem;
        }

        .dash-line-label {
            flex: 1;
            text-align: center;
        }
    </style>

    <section class="dash-panel-card" aria-label="Dashboard operations overview">
        <div class="dash-panel-head">
            <p class="dash-panel-meta">Updated {{ $lastUpdated }}</p>
        </div>

        <div class="dash-stat-grid" aria-label="Dashboard summary">
            @foreach ($stats as $stat)
                <article class="dash-stat-tile">
                    <p class="dash-stat-label">{{ $stat['label'] }}</p>
                    <p class="dash-stat-value">{{ $stat['value'] }}</p>
                    <p class="dash-stat-meta">{{ $stat['meta'] }}</p>
                </article>
            @endforeach
        </div>
    </section>

    <section class="dash-panel-card" aria-labelledby="dashboard-channel-title">
        <div class="dash-panel-head">
            <h2 id="dashboard-channel-title" class="dash-panel-title">Website vs Staff Tablet</h2>
        </div>

        <div class="dash-channel-grid" aria-label="Website and staff tablet comparison">
            @foreach ($channelRows as $row)
                <article class="dash-channel-tile">
                    <p class="dash-channel-label">{{ $row['label'] }}</p>
                    <p class="dash-channel-metric">
                        <strong>{{ $row['value'] }}</strong>
                        <span>{{ $row['metric'] }}</span>
                    </p>
                </article>
            @endforeach
        </div>
    </section>

    <div class="dash-chart-grid">
        <section class="dash-panel-card dash-chart-card" aria-labelledby="dashboard-bookings-chart">
            <h2 id="dashboard-bookings-chart" class="dash-chart-title">Bookings, last 7 days</h2>
            <div class="dash-chart-body">
                <div class="dash-bars">
                    @foreach ($bookingsLast7 as $row)
                        @php
                            $barHeight = $maxBookings7 > 0 ? max(8, round((((int) $row['count']) / $maxBookings7) * 124)) : 8;
                        @endphp
                        <div class="dash-bar-col">
                            <span class="dash-bar-count">{{ $row['count'] }}</span>
                            <span class="dash-bar" style="height: {{ $barHeight }}px" title="{{ $row['label'] }}: {{ $row['count'] }}"></span>
                            <span class="dash-chart-label">{{ $row['label'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="dash-panel-card dash-chart-card" aria-labelledby="dashboard-queue-chart">
            <h2 id="dashboard-queue-chart" class="dash-chart-title">Queue joins, last 7 days</h2>
            <div class="dash-chart-body">
                <svg class="h-[11.5rem] w-full" viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true">
                    <defs>
                        <linearGradient id="dashboardQueueAreaPanel" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="#0033b8" stop-opacity="0.13" />
                            <stop offset="100%" stop-color="#0033b8" stop-opacity="0.02" />
                        </linearGradient>
                    </defs>
                    <polygon points="{{ $queueAreaPoints }}" fill="url(#dashboardQueueAreaPanel)" />
                    <polyline points="{{ $queueLinePoints }}" fill="none" stroke="#0033b8" stroke-width="2"
                        vector-effect="non-scaling-stroke" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
                <div class="dash-line-labels">
                    @foreach ($queueLast7 as $row)
                        <span class="dash-line-label">{{ $row['label'] }}</span>
                    @endforeach
                </div>
            </div>
        </section>
    </div>
</div>
