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
        Schema::create('queue_entries', function (Blueprint $table) {
            $table->id();
            $table->string('customer_name', 255);
            $table->string('customer_phone', 20);
            $table->unsignedTinyInteger('party_size');
            $table->enum('priority_type', ['none', 'pwd', 'pregnant', 'senior'])->default('none');
            $table->unsignedTinyInteger('priority_score')->default(0);
            $table->boolean('needs_accessible')->default(false);
            $table->enum('status', ['waiting', 'notified', 'seated', 'cancelled'])->default('waiting');
            $table->unsignedTinyInteger('estimated_wait')->nullable();
            $table->timestamp('joined_at')->useCurrent();
            $table->timestamp('notified_at')->nullable();
            $table->timestamp('seated_at')->nullable();
            $table->timestamps();

            // RA 9994 / RA 7277 — priority entries always before regular
            $table->index(['status', 'priority_score', 'joined_at'], 'idx_queue_sort');
            $table->index('customer_phone', 'idx_queue_entries_phone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('queue_entries');
    }
};
