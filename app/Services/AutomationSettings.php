<?php

namespace App\Services;

use App\Models\QueueEntry;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class AutomationSettings
{
    public static function masterEnabled(): bool
    {
        return Setting::get('automation_master_enabled', '1') === '1';
    }

    public static function bool(string $key, bool $default = true): bool
    {
        $def = $default ? '1' : '0';

        return Setting::get($key, $def) === '1';
    }

    public static function int(string $key, int $fallbackConfig): int
    {
        $v = Setting::get($key, null);
        if ($v === null || $v === '') {
            return $fallbackConfig;
        }

        return max(0, (int) $v);
    }

    public static function adminAlertPhone(): string
    {
        return (string) Setting::get('admin_alert_phone', '');
    }

    /**
     * Clear cached learned peak-hour bundles (e.g. after changing automation settings).
     */
    public static function forgetDynamicPeakQueueHoursCache(): void
    {
        for ($d = 0; $d < 7; $d++) {
            Cache::forget(static::dynamicPeakCacheKey($d));
        }
    }

    private static function dynamicPeakCacheKey(int $dayOfWeek): string
    {
        return 'automation.peak_queue_bundle.v3.dow'.$dayOfWeek;
    }

    public static function peakHoursLearnFromQueueEnabled(): bool
    {
        $def = config('automation.peak_hours_learn_from_queue', true) ? '1' : '0';

        return Setting::get('peak_hours_learn_from_queue', $def) === '1';
    }

    /**
     * Live admin preview: clock, in/out peak, join histogram, approximate busy window.
     * Matches the same cached bundle used for real notify decisions.
     */
    public static function queuePeakDiagnostics(): array
    {
        $tz = (string) config('app.timezone');
        $now = now($tz);
        $learn = static::peakHoursLearnFromQueueEnabled();
        $queueNotify = static::bool('automation_notify_queue_on_release', true);
        $inPeak = static::isWithinPeakHoursForQueueNotify();

        $bundle = $learn ? static::getLearnedPeakBundleForToday() : null;

        $staticHours = static::staticPeakHourIndices();

        return [
            'timezone' => $tz,
            'now_label' => $now->format('g:i:s A'),
            'now_day' => $now->format('l'),
            'current_hour' => (int) $now->hour,
            'learn_enabled' => $learn,
            'queue_notify_enabled' => $queueNotify,
            'in_peak' => $inPeak,
            'table_ready_sms_would_send' => $queueNotify && $inPeak,
            'approx_peak_label' => $learn
                ? static::formatApproximateHourRanges($bundle['peak_hours'])
                : static::formatApproximateHourRanges($staticHours),
            'counts' => $bundle['counts'] ?? array_fill(0, 24, 0),
            'peak_hours' => $bundle['peak_hours'] ?? $staticHours,
            'total_joins' => $bundle['total'] ?? 0,
            'dataset' => $bundle['dataset'] ?? 'fixed_hours',
            'threshold' => $bundle['threshold'] ?? 0,
            'computed_at' => $bundle['computed_at'] ?? null,
            'cache_ttl_seconds' => max(60, (int) config('automation.peak_queue_cache_ttl_seconds', 90)),
        ];
    }

    /**
     * @return list<int> hours 0–23 considered peak when learning is off
     */
    private static function staticPeakHourIndices(): array
    {
        $startStr = trim((string) Setting::get('peak_hours_start', config('automation.peak_hours_start', '17:00')));
        $endStr = trim((string) Setting::get('peak_hours_end', config('automation.peak_hours_end', '22:00')));

        try {
            $start = Carbon::createFromFormat('H:i', $startStr);
            $end = Carbon::createFromFormat('H:i', $endStr);
        } catch (\Throwable) {
            return range(0, 23);
        }

        $sh = (int) $start->hour;
        $eh = (int) $end->hour;

        if ($sh === $eh) {
            return range(0, 23);
        }

        $hours = [];
        if ($sh <= $eh) {
            for ($h = $sh; $h <= $eh; $h++) {
                $hours[] = $h;
            }
        } else {
            for ($h = $sh; $h <= 23; $h++) {
                $hours[] = $h;
            }
            for ($h = 0; $h <= $eh; $h++) {
                $hours[] = $h;
            }
        }

        return $hours;
    }

    /**
     * @param  list<int>  $hours
     */
    public static function formatApproximateHourRanges(array $hours): string
    {
        $hours = array_values(array_unique(array_map('intval', $hours)));
        sort($hours);

        if ($hours === [] || count($hours) >= 24) {
            return 'All day (approx.)';
        }

        $ranges = [];
        $i = 0;
        $n = count($hours);
        while ($i < $n) {
            $from = $hours[$i];
            $to = $from;
            while ($i + 1 < $n && $hours[$i + 1] === $to + 1) {
                $i++;
                $to = $hours[$i];
            }
            if ($from === $to) {
                $ranges[] = static::formatApproximateHour($from);
            } else {
                $ranges[] = static::formatApproximateHour($from).'–'.static::formatApproximateHour($to);
            }
            $i++;
        }

        return 'About '.implode(', ', $ranges);
    }

    private static function formatApproximateHour(int $hour): string
    {
        $h = $hour % 24;
        if ($h === 0) {
            return 'midnight';
        }
        if ($h === 12) {
            return 'noon';
        }
        if ($h < 12) {
            return $h.' AM';
        }

        return ($h - 12).' PM';
    }

    /**
     * True when queue "table ready" SMS on table release may run (peak / busy window).
     * Learned mode uses hourly buckets from waitlist joins (no minute-precise edges).
     * Fixed mode uses whole clock hours only (start/end times contribute hour only).
     */
    public static function isWithinPeakHoursForQueueNotify(): bool
    {
        if (static::peakHoursLearnFromQueueEnabled()) {
            return static::isWithinLearnedPeakHoursForQueueNotify();
        }

        return static::isWithinStaticPeakWindow();
    }

    /**
     * True when auto table-ready alerts may run for the queue (busy window OR staff override).
     */
    public static function effectivePeakForQueueNotify(): bool
    {
        if (Setting::get('waitlist_staff_peak_override', '0') === '1') {
            return true;
        }

        return static::isWithinPeakHoursForQueueNotify();
    }

    /**
     * @return array{
     *     peak_hours: list<int>,
     *     counts: array<int, int>,
     *     total: int,
     *     dataset: string,
     *     threshold: int,
     *     computed_at: string
     * }
     */
    private static function getLearnedPeakBundleForToday(): array
    {
        $tz = (string) config('app.timezone');
        $dow = (int) now($tz)->dayOfWeek;
        $ttl = max(60, (int) config('automation.peak_queue_cache_ttl_seconds', 90));

        return Cache::remember(static::dynamicPeakCacheKey($dow), $ttl, function () use ($tz, $dow) {
            return static::buildLearnedPeakBundle($tz, $dow);
        });
    }

    private static function isWithinLearnedPeakHoursForQueueNotify(): bool
    {
        $tz = (string) config('app.timezone');
        $bundle = static::getLearnedPeakBundleForToday();

        return in_array((int) now($tz)->hour, $bundle['peak_hours'], true);
    }

    /**
     * @return array{
     *     peak_hours: list<int>,
     *     counts: array<int, int>,
     *     total: int,
     *     dataset: string,
     *     threshold: int,
     *     computed_at: string
     * }
     */
    private static function buildLearnedPeakBundle(string $tz, int $dayOfWeek): array
    {
        $minSamples = (int) config('automation.peak_queue_min_samples', 30);

        [$counts, $total] = static::aggregateJoinCountsByHour($tz, $dayOfWeek);
        $dataset = 'same_weekday';
        if ($total < $minSamples) {
            [$counts, $total] = static::aggregateJoinCountsByHour($tz, null);
            $dataset = 'all_days';
        }

        $resolved = static::peakHoursFromCounts($counts, $total);
        if ($total < $minSamples) {
            $dataset = 'cold_start';
        }

        return [
            'peak_hours' => $resolved['peak_hours'],
            'counts' => $counts,
            'total' => $total,
            'dataset' => $dataset,
            'threshold' => $resolved['threshold'],
            'computed_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @return array{0: array<int, int>, 1: int}
     */
    private static function aggregateJoinCountsByHour(string $tz, ?int $onlyDayOfWeek): array
    {
        $days = max(1, (int) config('automation.peak_queue_lookback_days', 28));
        $counts = array_fill(0, 24, 0);
        $total = 0;

        QueueEntry::query()
            ->where('joined_at', '>=', now()->subDays($days))
            ->select(['joined_at'])
            ->orderBy('id')
            ->cursor()
            ->each(function (QueueEntry $row) use (&$counts, &$total, $tz, $onlyDayOfWeek) {
                $local = $row->joined_at->copy()->timezone($tz);
                if ($onlyDayOfWeek !== null && (int) $local->dayOfWeek !== $onlyDayOfWeek) {
                    return;
                }
                $counts[(int) $local->hour]++;
                $total++;
            });

        return [$counts, $total];
    }

    /**
     * @param  array<int, int>  $counts
     * @return array{peak_hours: list<int>, threshold: int}
     */
    private static function peakHoursFromCounts(array $counts, int $total): array
    {
        $minSamples = (int) config('automation.peak_queue_min_samples', 30);
        if ($total < $minSamples) {
            return ['peak_hours' => range(0, 23), 'threshold' => 0];
        }

        $max = max($counts);
        if ($max <= 0) {
            return ['peak_hours' => range(0, 23), 'threshold' => 0];
        }

        $ratio = (float) config('automation.peak_queue_busy_ratio_of_max', 0.35);
        $ratio = max(0.05, min(1.0, $ratio));
        $threshold = max(1, (int) ceil($max * $ratio));

        $peak = [];
        for ($h = 0; $h < 24; $h++) {
            if ($counts[$h] >= $threshold) {
                $peak[] = $h;
            }
        }

        if ($peak === []) {
            return ['peak_hours' => range(0, 23), 'threshold' => $threshold];
        }

        return ['peak_hours' => $peak, 'threshold' => $threshold];
    }

    /**
     * Whole hours only — no minute-level cutoffs (approximate “peak window”).
     */
    private static function isWithinStaticPeakWindow(): bool
    {
        $ch = (int) now()->hour;
        $hours = static::staticPeakHourIndices();

        return in_array($ch, $hours, true);
    }
}
