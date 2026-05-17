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
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->string('booking_ref', 12)->unique();
            $table->unsignedBigInteger('table_id');
            $table->string('customer_name', 255);
            $table->string('customer_phone', 20);
            $table->unsignedTinyInteger('party_size');
            $table->enum('priority_type', ['none', 'pwd', 'pregnant', 'senior'])->default('none');
            $table->enum('status', ['active', 'completed', 'cancelled'])->default('active');
            $table->timestamp('booked_at')->useCurrent();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('table_id')->references('id')->on('tables');
            $table->index('customer_phone', 'idx_bookings_phone');
            $table->index('status', 'idx_bookings_status');
            $table->index('booked_at', 'idx_bookings_booked');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
