<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tables', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('venue_id')->default(1);
            $table->string('label', 50);
            $table->unsignedTinyInteger('capacity');
            $table->enum('status', ['available', 'occupied', 'reserved', 'unavailable'])->default('available');
            $table->boolean('is_accessible')->default(false);
            $table->string('accessible_features', 255)->nullable();
            $table->float('position_x')->nullable();
            $table->float('position_y')->nullable();
            $table->enum('shape', ['rect', 'circle'])->default('rect');
            $table->timestamp('occupied_at')->nullable();
            $table->unsignedTinyInteger('occupied_party')->nullable();
            $table->timestamps();

            $table->index('status', 'idx_tables_status');
            $table->index(['is_accessible', 'status'], 'idx_tables_accessible');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tables');
    }
};
