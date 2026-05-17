<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('tables', function (Blueprint $table) {
            if (! Schema::hasColumn('tables', 'planner_shape')) {
                $table->string('planner_shape', 32)->default('square')->after('shape');
            }
            if (! Schema::hasColumn('tables', 'layout_width')) {
                $table->decimal('layout_width', 8, 2)->default(120)->after('position_y');
            }
            if (! Schema::hasColumn('tables', 'layout_height')) {
                $table->decimal('layout_height', 8, 2)->default(90)->after('layout_width');
            }
            if (! Schema::hasColumn('tables', 'layout_rotation')) {
                $table->unsignedSmallInteger('layout_rotation')->default(0)->after('layout_height');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tables', function (Blueprint $table) {
            $drop = [];
            foreach (['planner_shape', 'layout_width', 'layout_height', 'layout_rotation'] as $column) {
                if (Schema::hasColumn('tables', $column)) {
                    $drop[] = $column;
                }
            }
            if ($drop !== []) {
                $table->dropColumn($drop);
            }
        });
    }
};
