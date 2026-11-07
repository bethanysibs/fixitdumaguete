<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('version_number');
            $table->enum('status', ['draft', 'submitted', 'superseded', 'approved', 'rejected', 'expired']);
            $table->decimal('subtotal', 12, 2);
            $table->decimal('total_amount', 12, 2);
            $table->char('currency', 3)->default('PHP');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_current')->default(false);
            $table->foreignId('current_booking_id')->nullable()->unique()->constrained('bookings')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['booking_id', 'version_number'], 'quotations_booking_version_unique');
            $table->index('status');
            $table->index(['booking_id', 'is_current']);
        });

        Schema::create('quotation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_id')->constrained()->cascadeOnDelete();
            $table->enum('item_type', ['labor', 'material', 'service_charge', 'inspection_fee', 'other']);
            $table->string('description');
            $table->decimal('quantity', 10, 2);
            $table->string('unit', 40);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('line_total', 12, 2);
            $table->unsignedInteger('sort_order');
            $table->timestamps();

            $table->index(['quotation_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotation_items');
        Schema::dropIfExists('quotations');
    }
};
