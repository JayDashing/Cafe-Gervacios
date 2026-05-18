<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('seats') || ! Schema::hasColumn('seats', 'status')) {
            return;
        }

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE `seats` MODIFY `status` ENUM('free', 'reserved', 'occupied', 'cleaning') NOT NULL DEFAULT 'free'");
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('seats') || ! Schema::hasColumn('seats', 'status')) {
            return;
        }

        DB::table('seats')->where('status', 'cleaning')->update(['status' => 'free']);

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE `seats` MODIFY `status` ENUM('free', 'reserved', 'occupied') NOT NULL DEFAULT 'free'");
        }
    }
};
