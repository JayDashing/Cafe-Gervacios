<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmsLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'phone_hash',
        'phone',
        'message',
        'status',
        'provider_message_id',
        'semaphore_message_id',
        'error_message',
        'template',
        'context',
        'created_at',
    ];

    protected $casts = [
        'context' => 'array',
        'created_at' => 'datetime',
    ];

    public function getProviderMessageIdAttribute(): ?string
    {
        $value = $this->attributes['semaphore_message_id'] ?? null;

        return $value === null ? null : (string) $value;
    }

    public function setProviderMessageIdAttribute(mixed $value): void
    {
        $this->attributes['semaphore_message_id'] = $value === null ? null : (string) $value;
    }
}
