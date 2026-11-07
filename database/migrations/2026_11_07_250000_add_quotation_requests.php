<?php

use App\Services\QuotationRequestGrouper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotation_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('request_number');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status')->default('open');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['booking_id', 'request_number'], 'quotation_requests_booking_number_unique');
            $table->index('status');
        });

        Schema::table('quotations', function (Blueprint $table) {
            $table->dropForeign(['current_booking_id']);
        });

        Schema::table('quotations', function (Blueprint $table) {
            $table->dropUnique(['current_booking_id']);
            $table->dropUnique('quotations_booking_version_unique');
        });

        Schema::table('quotations', function (Blueprint $table) {
            $table->foreign('current_booking_id')->references('id')->on('bookings')->restrictOnDelete();
            $table->foreignId('quotation_request_id')->nullable()->after('booking_id')->constrained('quotation_requests')->restrictOnDelete();
            $table->foreignId('current_request_id')->nullable()->unique()->after('is_current')->constrained('quotation_requests')->restrictOnDelete();
        });

        QuotationRequestGrouper::groupUnassigned();

        Schema::table('quotations', function (Blueprint $table) {
            $table->unique(['quotation_request_id', 'version_number'], 'quotations_request_version_unique');
        });
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->dropUnique('quotations_request_version_unique');
            $table->dropConstrainedForeignId('current_request_id');
            $table->dropConstrainedForeignId('quotation_request_id');
        });

        Schema::table('quotations', function (Blueprint $table) {
            $table->dropForeign(['current_booking_id']);
        });

        Schema::table('quotations', function (Blueprint $table) {
            $table->unique('current_booking_id');
            $table->unique(['booking_id', 'version_number'], 'quotations_booking_version_unique');
            $table->foreign('current_booking_id')->references('id')->on('bookings')->restrictOnDelete();
        });

        Schema::dropIfExists('quotation_requests');
    }
};
