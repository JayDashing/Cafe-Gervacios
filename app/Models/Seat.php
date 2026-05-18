<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Seat extends Model
{
    public const STATUS_FREE = 'free';
    public const STATUS_RESERVED = 'reserved';
    public const STATUS_OCCUPIED = 'occupied';
    public const STATUS_CLEANING = 'cleaning';

    public const STATUSES = [
        self::STATUS_FREE,
        self::STATUS_RESERVED,
        self::STATUS_OCCUPIED,
        self::STATUS_CLEANING,
    ];

    protected $fillable = [
        'table_id',
        'seat_index',
        'status',
        'pos_x',
        'pos_y',
    ];

    protected function casts(): array
    {
        return [
            'pos_x' => 'float',
            'pos_y' => 'float',
        ];
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(Table::class);
    }
}
