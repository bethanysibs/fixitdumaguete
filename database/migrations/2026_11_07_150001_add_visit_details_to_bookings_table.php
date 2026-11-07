<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable()->after('location_id');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->string('landmark')->nullable()->after('longitude');
            $table->string('contact_phone', 20)->nullable()->after('landmark');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude', 'landmark', 'contact_phone']);
        });
    }
};
