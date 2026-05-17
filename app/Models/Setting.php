<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Throwable;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    public static function get(string $key, mixed $default = null): mixed
    {
        try {
            if (! Schema::hasTable('settings')) {
                return $default;
            }

            $resolve = function () use ($key, $default) {
                $value = static::query()->where('key', $key)->value('value');

                return ($value !== null && $value !== '') ? $value : $default;
            };

            try {
                return Cache::remember('setting.'.$key, 300, $resolve);
            } catch (Throwable) {
                return $resolve();
            }
        } catch (Throwable) {
            return $default;
        }
    }

    public static function set(string $key, mixed $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget('setting.'.$key);
    }
}
