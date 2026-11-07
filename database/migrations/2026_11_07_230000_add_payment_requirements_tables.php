<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['booking_id']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique(['booking_id']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreign('booking_id')->references('id')->on('bookings')->cascadeOnDelete();
            $table->foreignId('quotation_id')->nullable()->constrained('quotations')->restrictOnDelete();
            $table->unsignedInteger('quotation_version')->nullable();
            $table->string('payment_type')->nullable();
            $table->string('verification_status')->default('not_submitted');
            $table->string('payment_reference')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('notes')->nullable();
            $table->char('currency', 3)->default('PHP');
            $table->index(['quotation_id', 'quotation_version'], 'payments_quotation_version_index');
            $table->index('payment_type');
            $table->index('verification_status');
            $table->index('status');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->decimal('amount', 12, 2)->change();
        });

        DB::table('payments')->where('status', 'paid')->update([
            'verification_status' => 'verified',
        ]);
        DB::table('payments')->where('status', 'refunded')->update([
            'verification_status' => 'verified',
        ]);
        DB::table('payments')->whereNull('submitted_by')->update([
            'submitted_by' => DB::raw('customer_id'),
        ]);

        Schema::create('payment_settings', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('deposit_type');
            $table->decimal('deposit_value', 12, 2);
            $table->decimal('minimum_deposit_amount', 12, 2)->nullable();
            $table->decimal('maximum_deposit_amount', 12, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->dateTime('effective_at')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('payment_proofs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->string('file_path');
            $table->string('original_filename');
            $table->string('mime_type', 120);
            $table->unsignedBigInteger('file_size');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('uploaded_at');
            $table->timestamps();
        });

        Schema::create('payment_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->text('description')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['payment_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_logs');
        Schema::dropIfExists('payment_proofs');
        Schema::dropIfExists('payment_settings');

        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['quotation_id']);
            $table->dropForeign(['submitted_by']);
            $table->dropForeign(['verified_by']);
            $table->dropForeign(['rejected_by']);
            $table->dropIndex('payments_quotation_version_index');
            $table->dropIndex(['payment_type']);
            $table->dropIndex(['verification_status']);
            $table->dropIndex(['status']);
            $table->dropColumn([
                'quotation_id',
                'quotation_version',
                'payment_type',
                'verification_status',
                'payment_reference',
                'submitted_by',
                'verified_by',
                'verified_at',
                'rejected_by',
                'rejected_at',
                'rejection_reason',
                'notes',
                'currency',
            ]);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->decimal('amount', 10, 2)->change();
            $table->dropForeign(['booking_id']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->unique('booking_id');
            $table->foreign('booking_id')->references('id')->on('bookings')->cascadeOnDelete();
        });
    }
};
