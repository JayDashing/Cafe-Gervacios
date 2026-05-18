<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class LocalDevelopmentAdmin
{
    public const EMAIL = 'admin@kiosk.test';

    public const PASSWORD = 'admin123';

    public static function ensure(): User
    {
        // Development-only credentials. Never call this from production seed paths.
        return User::updateOrCreate(
            ['email' => self::EMAIL],
            [
                'name' => 'Local Development Admin',
                'password' => Hash::make(self::PASSWORD),
                'role' => 'admin',
                'must_change_password' => false,
                'password_changed_at' => now(),
                'is_active' => true,
                'deactivated_at' => null,
                'google2fa_secret' => null,
                'google2fa_enabled' => false,
            ],
        );
    }
}
