<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex(['paymongo_link_id']);
            $table->unique('paymongo_link_id', 'bookings_paymongo_link_id_unique');
            $table->unique('paymongo_payment_id', 'bookings_paymongo_payment_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropUnique('bookings_paymongo_link_id_unique');
            $table->dropUnique('bookings_paymongo_payment_id_unique');
            $table->index('paymongo_link_id');
        });
    }
};
