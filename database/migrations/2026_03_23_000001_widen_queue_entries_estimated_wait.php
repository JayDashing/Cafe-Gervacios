<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('queue_entries', 'estimated_wait')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE queue_entries MODIFY estimated_wait SMALLINT UNSIGNED NULL');
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('queue_entries', 'estimated_wait')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE queue_entries MODIFY estimated_wait TINYINT UNSIGNED NULL');
        }
    }
};
