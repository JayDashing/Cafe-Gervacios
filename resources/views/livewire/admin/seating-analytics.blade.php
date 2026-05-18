<div wire:poll.30s class="space-y-4">

    @include('admin.partials.sa-panel-styles')

    @php
        $peakValues = $this->peakHourData;
        $topTables = $this->topTableUsage;
        $hasPeakData = array_sum($peakValues) > 0;
        $hasTopData = count($topTables) > 0;
        $lastUpdated = now()->timezone(config('app.timezone'))->format('M d, Y g:i:s A');
        $cards = [
            [
                'label' => 'Bookings',
                'value' => $this->totalBookingsToday,
                'meta' => 'Today',
                'href' => route('admin.bookings'),
            ],
            [
                'label' => 'Check-ins',
                'value' => $this->totalCheckedInToday,
                'meta' => 'Today',
                'href' => route('admin.bookings'),
            ],
            [
                'label' => 'Queue seated',
                'value' => $this->totalSeatedFromQueue,
                'meta' => 'Today',
                'href' => route('admin.waitlist'),
            ],
            [
                'label' => 'Occupied',
                'value' => $this->tablesOccupiedNow,
                'meta' => 'Tables',
                'href' => route('admin.tables'),
            ],
            [
                'label' => 'Free tables',
                'value' => $this->tablesFreeNow,
                'meta' => 'Available',
                'href' => route('admin.tables'),
            ],
        ];
    @endphp

    <section class="sa-panel-card">
        <div class="sa-panel-head sa-panel-head--compact">
            <div class="sa-panel-actions">
                <p class="sa-timestamp">Updated {{ $lastUpdated }}</p>
                <a href="{{ route('admin.system-logs') }}" class="sa-source-link">
                    <i class="fa-solid fa-clipboard-list text-[11px]" aria-hidden="true"></i>
                    System Logs
                </a>
            </div>
        </div>

        <div class="sa-stat-grid">
            @foreach ($cards as $card)
                <a href="{{ $card['href'] }}" class="sa-stat-tile" aria-label="View {{ strtolower($card['label']) }}">
                    <p class="sa-stat-label">{{ $card['label'] }}</p>
                    <p class="sa-stat-value">{{ $card['value'] }}</p>
                    <p class="sa-stat-meta">{{ $card['meta'] }}</p>
                </a>
            @endforeach
        </div>
    </section>

    @unless ($this->hasSourceData)
        <div class="sa-empty-state" role="status">
            <i class="fa-solid fa-chart-simple text-lg text-slate-400" aria-hidden="true"></i>
            <div>
                <p class="font-bold text-slate-900">No analytics data yet.</p>
                <p class="mt-0.5 text-sm text-slate-600">Create a booking, seat a queue guest, or update table status to populate this page.</p>
            </div>
        </div>
    @endunless

    <div class="grid grid-cols-1 gap-3 xl:grid-cols-2">

        <div class="sa-chart-card">
            <div class="sa-chart-head">
                <div>
                    <p class="sa-chart-title">Bookings by hour</p>
                    <p class="sa-chart-sub">Last 7 days</p>
                </div>
                <a href="{{ route('admin.bookings') }}" class="sa-source-link">Bookings</a>
            </div>
            @unless ($hasPeakData)
                <p class="sa-chart-empty">No booking-hour data yet.</p>
            @endunless
            <div class="sa-chart-scroll" aria-label="Bookings by hour chart, horizontally scrollable on small screens">
                <div class="sa-chart-wrap sa-chart-wrap--wide">
                    <canvas id="seating-peak-chart"></canvas>
                </div>
            </div>
        </div>

        <div class="sa-chart-card">
            <div class="sa-chart-head">
                <div>
                    <p class="sa-chart-title">Top tables</p>
                    <p class="sa-chart-sub">Last 30 days</p>
                </div>
                <a href="{{ route('admin.tables') }}" class="sa-source-link">Tables</a>
            </div>
            @unless ($hasTopData)
                <p class="sa-chart-empty">No table usage yet.</p>
            @endunless
            <div class="sa-chart-scroll" aria-label="Top table usage chart, horizontally scrollable on small screens">
                <div class="sa-chart-wrap">
                    <canvas id="seating-top-chart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <script type="application/json" id="seating-analytics-json">@json($chartPayload)</script>

    <script>
        (function () {
            var BAR_FILL = '#1e293b';
            var BAR_BORDER = '#0f172a';
            var GRID = '#e5e7eb';
            var TICK = '#475569';
            var FONT = { size: 11, weight: '600', family: "'Inter', system-ui, sans-serif" };

            var GRID_AXIS = {
                grid: { color: GRID, drawBorder: false },
                border: { display: false },
                ticks: { color: TICK, font: FONT },
            };

            var barRadiusV = { topLeft: 4, topRight: 4, bottomLeft: 0, bottomRight: 0 };
            var barRadiusH = { topLeft: 0, bottomLeft: 0, topRight: 4, bottomRight: 4 };

            function buildCharts() {
                if (typeof Chart === 'undefined') return;

                var el = document.getElementById('seating-analytics-json');
                if (!el) return;

                var payload;
                try { payload = JSON.parse(el.textContent); } catch (e) { return; }

                var peakCanvas = document.getElementById('seating-peak-chart');
                var topCanvas = document.getElementById('seating-top-chart');
                if (!peakCanvas || !topCanvas) return;

                if (window.__seatingPeakChart) { window.__seatingPeakChart.destroy(); window.__seatingPeakChart = null; }
                if (window.__seatingTopChart) { window.__seatingTopChart.destroy(); window.__seatingTopChart = null; }

                var isSmall = window.matchMedia && window.matchMedia('(max-width: 640px)').matches;
                var peakLabels = payload.peakLabels || [];
                var peak = payload.peak || {};
                var peakValues = [];
                for (var h = 0; h < 24; h++) {
                    peakValues.push(typeof peak[h] === 'number' ? peak[h] : 0);
                }

                window.__seatingPeakChart = new Chart(peakCanvas.getContext('2d'), {
                    type: 'bar',
                    data: {
                        labels: peakLabels,
                        datasets: [{
                            label: 'Bookings',
                            data: peakValues,
                            backgroundColor: BAR_FILL,
                            borderColor: BAR_BORDER,
                            borderWidth: 1,
                            borderRadius: barRadiusV,
                            borderSkipped: 'bottom',
                        }],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            x: {
                                ...GRID_AXIS,
                                ticks: {
                                    ...GRID_AXIS.ticks,
                                    maxRotation: 0,
                                    minRotation: 0,
                                    autoSkip: false,
                                    callback: function (val, index) {
                                        var step = isSmall ? 6 : 4;
                                        if (index % step !== 0) return '';
                                        return peakLabels[index] || '';
                                    },
                                },
                            },
                            y: {
                                ...GRID_AXIS,
                                beginAtZero: true,
                                ticks: { ...GRID_AXIS.ticks, precision: 0, stepSize: 1 },
                            },
                        },
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                backgroundColor: '#0f172a',
                                titleFont: { size: 11, weight: '700', family: FONT.family },
                                bodyFont: { size: 11, family: FONT.family },
                                padding: 10,
                                cornerRadius: 8,
                            },
                        },
                    },
                });

                var topRows = payload.top || [];
                var topLabels = topRows.map(function (r) { return r.label; });
                var topCounts = topRows.map(function (r) { return r.count; });

                window.__seatingTopChart = new Chart(topCanvas.getContext('2d'), {
                    type: 'bar',
                    data: {
                        labels: topLabels,
                        datasets: [{
                            label: 'Bookings',
                            data: topCounts,
                            backgroundColor: BAR_FILL,
                            borderColor: BAR_BORDER,
                            borderWidth: 1,
                            borderRadius: barRadiusH,
                            borderSkipped: 'left',
                        }],
                    },
                    options: {
                        indexAxis: 'y',
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            x: {
                                ...GRID_AXIS,
                                beginAtZero: true,
                                ticks: { ...GRID_AXIS.ticks, precision: 0, stepSize: 1 },
                            },
                            y: {
                                ...GRID_AXIS,
                                ticks: {
                                    ...GRID_AXIS.ticks,
                                    callback: function (val, index) {
                                        var label = topLabels[index] || '';
                                        return label.length > 16 ? label.slice(0, 15) + '...' : label;
                                    },
                                },
                            },
                        },
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                backgroundColor: '#0f172a',
                                titleFont: { size: 11, weight: '700', family: FONT.family },
                                bodyFont: { size: 11, family: FONT.family },
                                padding: 10,
                                cornerRadius: 8,
                            },
                        },
                    },
                });
            }

            function tryInit() {
                if (typeof Chart === 'undefined') { setTimeout(tryInit, 50); return; }
                buildCharts();
            }

            document.addEventListener('DOMContentLoaded', tryInit);
            document.addEventListener('livewire:init', function () {
                Livewire.hook('morph.updated', function () {
                    requestAnimationFrame(function () {
                        if (document.getElementById('seating-analytics-json')) tryInit();
                    });
                });
            });
        })();
    </script>
</div>
