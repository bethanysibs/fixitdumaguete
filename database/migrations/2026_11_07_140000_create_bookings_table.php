<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('worker_profile_id')->constrained()->restrictOnDelete();
            $table->foreignId('service_id')->constrained()->restrictOnDelete();
            $table->foreignId('location_id')->constrained()->restrictOnDelete();
            $table->dateTime('scheduled_at');
            $table->text('notes');
            $table->string('status')->default('pending')->index();
            $table->decimal('price_amount', 10, 2);
            $table->decimal('commission_rate', 5, 2);
            $table->decimal('commission_amount', 10, 2);
            $table->decimal('worker_amount', 10, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
