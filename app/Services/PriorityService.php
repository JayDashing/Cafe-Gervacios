<?php

namespace App\Services;

use App\Models\QueueEntry;
use App\Models\AdminLog;
use App\Models\Setting;
use App\Models\Table;
use Illuminate\Support\Facades\Log;

/**
 * PriorityService handles priority access calculations and compliance logging.
 *
 * RA 9994 — Senior citizens get priority seating
 * RA 7277 — PWDs get priority seating
 * DOH guidelines — Pregnant women get priority seating
 *
 * Priority scores are fixed by law and cannot be changed without legal review.
 */
class PriorityService
{
    /**
     * Priority scores — fixed by law, never change without legal review.
     * RA 7277 (PWD), DOH (pregnant), RA 9994 (senior) all receive score 100.
     * Regular customers receive score 0.
     */
    private const SCORES = [
        'pwd' => 100,      // RA 7277
        'pregnant' => 100, // DOH guidelines
        'senior' => 100,   // RA 9994
        'none' => 0,
    ];

    /**
     * Get the priority score for a given priority type.
     *
     * @param string $priorityType One of: 'none', 'pwd', 'pregnant', 'senior'.
     * @return int Priority score (0 or 100).
     */
    public function getScore(string $priorityType): int
    {
        return self::SCORES[$priorityType] ?? 0;
    }

    /**
     * Check if a priority type qualifies for priority access.
     *
     * @param string $priorityType One of: 'none', 'pwd', 'pregnant', 'senior'.
     * @return bool True if priority score is 100.
     */
    public function isPriority(string $priorityType): bool
    {
        return (self::SCORES[$priorityType] ?? 0) === 100;
    }

    /**
     * Whether this party must be matched to an accessible (♿) table when seating or auto-notify.
     *
     * Queue priority (PWD / senior / pregnant) is separate: it only affects sort order via priority_score.
     * Senior and pregnant are never restricted to accessible tables. PWD is optional and controlled by
     * setting queue_pwd_requires_accessible_table (off by default).
     */
    public function requiresAccessibleTable(string $priorityType): bool
    {
        if ($priorityType !== 'pwd') {
            return false;
        }

        return Setting::get('queue_pwd_requires_accessible_table', '0') === '1';
    }

    /**
     * Log a priority seating event for compliance reporting.
     * RA 9994 / RA 7277 — venue must demonstrate compliance during inspections.
     *
     * @param QueueEntry $entry The queue entry being seated.
     * @param Table $table The table being assigned.
     */
    public function logSeatEvent(QueueEntry $entry, Table $table): void
    {
        Log::channel('priority_audit')->info('Priority seating event', [
            'entry_id' => $entry->id,
            'priority_type' => $entry->priority_type,
            'table_id' => $table->id,
            'accessible' => $table->is_accessible,
            'wait_minutes' => now()->diffInMinutes($entry->joined_at),
            'seated_at' => now()->toIso8601String(),
        ]);

        AdminLog::record(
            'priority_seating',
            'queue_entry',
            $entry->id,
            sprintf(
                'Priority seating: %s guest seated at %s | Score %d | Accessible table %s',
                strtoupper((string) $entry->priority_type),
                $table->label,
                (int) $entry->priority_score,
                $table->is_accessible ? 'yes' : 'no'
            )
        );
    }
}
