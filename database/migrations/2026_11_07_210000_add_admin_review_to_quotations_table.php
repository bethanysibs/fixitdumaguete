<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->foreignId('source_quotation_id')->nullable()->after('booking_id')->constrained('quotations')->nullOnDelete();
            $table->text('admin_notes')->nullable()->after('notes');
            $table->boolean('is_admin_adjustment')->default(false)->after('is_current');
            $table->foreignId('admin_approved_by')->nullable()->after('submitted_at')->constrained('users')->nullOnDelete();
            $table->timestamp('admin_approved_at')->nullable()->after('admin_approved_by');
            $table->foreignId('rejected_by')->nullable()->after('admin_approved_at')->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable()->after('rejected_by');
            $table->text('rejection_reason')->nullable()->after('rejected_at');
        });

        Schema::table('quotations', function (Blueprint $table) {
            $table->enum('status', [
                'draft',
                'submitted',
                'superseded',
                'approved',
                'rejected',
                'expired',
                'admin_approved',
            ])->change();
        });
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->enum('status', [
                'draft',
                'submitted',
                'superseded',
                'approved',
                'rejected',
                'expired',
            ])->change();
        });

        Schema::table('quotations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('source_quotation_id');
            $table->dropConstrainedForeignId('admin_approved_by');
            $table->dropConstrainedForeignId('rejected_by');
            $table->dropColumn([
                'admin_notes',
                'is_admin_adjustment',
                'admin_approved_at',
                'rejected_at',
                'rejection_reason',
            ]);
        });
    }
};
