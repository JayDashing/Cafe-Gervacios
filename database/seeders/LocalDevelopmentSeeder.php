<?php

namespace Database\Seeders;

use App\Support\LocalDevelopmentAdmin;
use Illuminate\Database\Seeder;

class LocalDevelopmentSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            return;
        }

        // Development-only credentials.
        LocalDevelopmentAdmin::ensure();
    }
}
