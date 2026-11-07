<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commission_settings', function (Blueprint $table) {
            $table->id();
            $table->dateTime('effective_at')->index();
            $table->decimal('default_rate', 5, 2);
            $table->decimal('first_booking_rate', 5, 2);
            $table->decimal('repeat_booking_rate', 5, 2);
            $table->string('basis');
            $table->decimal('minimum_amount', 10, 2)->nullable();
            $table->decimal('maximum_amount', 10, 2)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('commission_category_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commission_setting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_category_id')->constrained()->restrictOnDelete();
            $table->decimal('rate', 5, 2);
            $table->timestamps();

            $table->unique(['commission_setting_id', 'service_category_id'], 'commission_category_unique');
        });

        Schema::create('commission_worker_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commission_setting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('worker_profile_id')->constrained()->restrictOnDelete();
            $table->decimal('rate', 5, 2);
            $table->timestamps();

            $table->unique(['commission_setting_id', 'worker_profile_id'], 'commission_worker_unique');
        });

        $now = now();

        DB::table('commission_settings')->insert([
            'effective_at' => $now,
            'default_rate' => '10.00',
            'first_booking_rate' => '10.00',
            'repeat_booking_rate' => '5.00',
            'basis' => 'entire_service',
            'minimum_amount' => null,
            'maximum_amount' => null,
            'created_by' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_worker_rates');
        Schema::dropIfExists('commission_category_rates');
        Schema::dropIfExists('commission_settings');
    }
};
