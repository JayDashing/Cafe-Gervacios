<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * UserSeeder
 *
 * Creates default admin and staff users for testing.
 */
class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Admin user
        User::firstOrCreate(
            ['email' => 'admin@kiosk.test'],
            [
                'name' => 'Administrator',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'must_change_password' => false,
                'password_changed_at' => now(),
                'is_active' => true,
            ],
        );

        // Staff user
        User::firstOrCreate(
            ['email' => 'staff@kiosk.test'],
            [
                'name' => 'Staff Member',
                'password' => Hash::make('password'),
                'role' => 'staff',
                'must_change_password' => false,
                'password_changed_at' => now(),
                'is_active' => true,
            ],
        );
    }
}
