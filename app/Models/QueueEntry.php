<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QueueEntry extends Model
{
    use HasFactory;

    // NO SoftDeletes — RA 10173 requires hard delete within 24 hours

    protected $fillable = [
        'source',
        'device_type',
        'queue_display_number',
        'customer_name',
        'customer_phone',
        'party_size',
        'priority_type',
        'priority_score',
        'needs_accessible',
        'status',
        'estimated_wait',
        'last_estimated_wait',
        'joined_at',
        'notified_at',
        'seated_at',
        'otp_verified_at',
        'hold_expires_at',
        'hold_confirmation_code',
        'reserved_table_id',
        'skipped_at',
        'absent_at',
        'wait_alert_sent_at',
    ];

    protected $casts = [
        'needs_accessible' => 'boolean',
        'party_size' => 'integer',
        'priority_score' => 'integer',
        'estimated_wait' => 'integer',
        'last_estimated_wait' => 'integer',
        'joined_at' => 'datetime',
        'notified_at' => 'datetime',
        'seated_at' => 'datetime',
        'otp_verified_at' => 'datetime',
        'hold_expires_at' => 'datetime',
        'reserved_table_id' => 'integer',
        'skipped_at' => 'datetime',
        'absent_at' => 'datetime',
        'wait_alert_sent_at' => 'datetime',
    ];

    /**
     * Scope: entries waiting for a table.
     */
    public function scopeWaiting($query)
    {
        return $query->where('status', 'waiting');
    }

    /**
     * Scope: RA 9994 / RA 7277 — priority entries always before regular.
     * Priority score 100 (PWD, pregnant, senior) comes before score 0 (regular).
     * Within same score, earlier join time comes first. (Table type is not part of this ordering.)
     * NEVER change this sort order without legal review.
     */
    public function scopeSorted($query)
    {
        return $query->orderByDesc('priority_score')
            ->orderBy('joined_at');
    }

    /**
     * Check if this entry has priority status.
     */
    public function isPriority(): bool
    {
        return $this->priority_score >= 100;
    }

    public function waitEstimateMinutes(): ?int
    {
        $minutes = $this->estimated_wait ?? $this->last_estimated_wait;

        return $minutes === null ? null : (int) $minutes;
    }

    public function waitEstimateLabel(): string
    {
        $minutes = $this->waitEstimateMinutes();

        return $minutes === null ? 'Not calculated' : $minutes.' min';
    }

    /**
     * Whether this party can be seated at the given table (capacity + optional ♿ when needs_accessible).
     */
    public function accommodates(Table $table): bool
    {
        if ($table->capacity < $this->party_size) {
            return false;
        }

        if ($this->needs_accessible && !$table->is_accessible) {
            return false;
        }

        return true;
    }
}
