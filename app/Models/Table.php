<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Table status lifecycle: available → reserved (waitlist or reservation hold) → occupied → cleaning → available.
 * Reservation holds use {@see reserveForBooking()} and set {@see $booking_id}. Waitlist uses {@see reserve()} only.
 * (release moves occupied → cleaning; staff marks Free when ready.)
 */
class Table extends Model
{
    use HasFactory;

    protected $fillable = [
        'venue_id',
        'booking_id',
        'label',
        'capacity',
        'status',
        'is_accessible',
        'accessible_features',
        'position_x',
        'position_y',
        'shape',
        'planner_shape',
        'layout_width',
        'layout_height',
        'layout_rotation',
        'furniture_type',
        'floor_col',
        'floor_row',
        'floor_col_span',
        'floor_row_span',
        'occupied_at',
        'occupied_party',
        'cleaning_started_at',
    ];

    protected $casts = [
        'is_accessible' => 'boolean',
        'position_x' => 'float',
        'position_y' => 'float',
        'layout_width' => 'float',
        'layout_height' => 'float',
        'layout_rotation' => 'integer',
        'occupied_at' => 'datetime',
        'cleaning_started_at' => 'datetime',
    ];

    public function isAvailable(): bool
    {
        return $this->status === 'available';
    }

    /**
     * Atomically marks a table as occupied.
     * Uses a conditional SQL update to prevent double-booking race conditions.
     *
     * @param int $partySize Number of people being seated.
     * @return bool True if the table was successfully occupied, false if already taken.
     */
    public function occupy(int $partySize): bool
    {
        $updated = static::where('id', $this->id)
            ->where('status', 'available')
            ->update([
                'status' => 'occupied',
                'occupied_at' => now(),
                'occupied_party' => $partySize,
            ]);

        if ($updated) {
            $this->refresh();
            Seat::where('table_id', $this->id)
                ->update(['status' => 'occupied']);
        }

        return (bool) $updated;
    }

    /**
     * Hold a free table for a notified waitlist guest (available → reserved).
     * Does not set {@see $booking_id} — reservation holds use {@see reserveForBooking()} instead.
     */
    public function reserve(): bool
    {
        $updated = static::where('id', $this->id)
            ->where('status', 'available')
            ->update([
                'status' => 'reserved',
            ]);

        if ($updated) {
            $this->refresh();
        }

        return (bool) $updated;
    }

    /**
     * Hold a table when a reservation is confirmed (available → reserved). Sets {@see $booking_id} for staff.
     */
    public function reserveForBooking(Booking $booking): bool
    {
        $updated = static::where('id', $this->id)
            ->where('status', 'available')
            ->update([
                'status' => 'reserved',
                'booking_id' => $booking->id,
            ]);

        if ($updated) {
            $this->refresh();
        }

        return (bool) $updated;
    }

    /**
     * Seat a party on a table that was held for them (reserved → occupied).
     */
    public function occupyFromReserved(int $partySize): bool
    {
        $updated = static::where('id', $this->id)
            ->where('status', 'reserved')
            ->update([
                'status' => 'occupied',
                'occupied_at' => now(),
                'occupied_party' => $partySize,
            ]);

        if ($updated) {
            $this->refresh();
            Seat::where('table_id', $this->id)
                ->update(['status' => 'occupied']);
        }

        return (bool) $updated;
    }

    /**
     * Free the table for bussing/cleaning after guests leave (not immediately bookable).
     */
    public function release(): void
    {
        // Offset by table id so two tables never share the same cleaning_started_at second
        // (avoids mistaken UNIQUE(cleaning_started_at) or other collisions on some DBs).
        $started = now()->copy()->addMicroseconds(min((int) $this->id, 999_999));

        $this->update([
            'status' => 'cleaning',
            'occupied_at' => null,
            'occupied_party' => null,
            'booking_id' => null,
            'cleaning_started_at' => $started,
        ]);
    }

    public function scopeAvailable($query, int $minCapacity = 1)
    {
        return $query->where('status', 'available')
            ->where('capacity', '>=', $minCapacity);
    }

    public function scopeAccessible($query)
    {
        return $query->where('is_accessible', true);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function seats(): HasMany
    {
        return $this->hasMany(Seat::class)->orderBy('seat_index');
    }
}
