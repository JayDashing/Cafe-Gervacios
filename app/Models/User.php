<?php

namespace App\Models;

use App\Notifications\StaffResetPasswordNotification;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'must_change_password',
        'password_changed_at',
        'is_active',
        'deactivated_at',
        'google2fa_secret',
        'google2fa_enabled',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'google2fa_secret',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'must_change_password' => 'boolean',
            'password_changed_at' => 'datetime',
            'is_active' => 'boolean',
            'deactivated_at' => 'datetime',
            'google2fa_enabled' => 'boolean',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin' || $this->role === 'superadmin';
    }

    /**
     * Full access to payment QR, PayMongo, PhilSMS, Facebook, and integration search.
     * Use role "superadmin" or list emails in config app.superadmin_emails (admin users only).
     */
    public function isSuperAdmin(): bool
    {
        if ($this->role === 'superadmin') {
            return true;
        }

        if ($this->role !== 'admin') {
            return false;
        }

        $emails = config('app.superadmin_emails', []);
        if ($emails === []) {
            return false;
        }

        $needle = strtolower((string) $this->email);

        return in_array($needle, array_map('strtolower', $emails), true);
    }

    public function isStaff(): bool
    {
        return $this->role === 'staff';
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new StaffResetPasswordNotification($token));
    }
}
