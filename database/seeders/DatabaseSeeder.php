<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            TableSeeder::class,
            // SeatSeeder::class — seats are placed on the admin map (Add seats…) or seed manually when ready.
        ]);

        if (app()->environment('local')) {
            $this->call(LocalDevelopmentSeeder::class);
        }
    }
}
