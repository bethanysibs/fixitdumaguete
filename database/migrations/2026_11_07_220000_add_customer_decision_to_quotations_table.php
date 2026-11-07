<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->foreignId('customer_approved_by')->nullable()->after('rejection_reason')->constrained('users')->nullOnDelete();
            $table->timestamp('customer_approved_at')->nullable()->after('customer_approved_by');
            $table->foreignId('customer_rejected_by')->nullable()->after('customer_approved_at')->constrained('users')->nullOnDelete();
            $table->timestamp('customer_rejected_at')->nullable()->after('customer_rejected_by');
            $table->text('customer_rejection_reason')->nullable()->after('customer_rejected_at');
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
                'customer_rejected',
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
                'admin_approved',
            ])->change();
        });

        Schema::table('quotations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('customer_approved_by');
            $table->dropConstrainedForeignId('customer_rejected_by');
            $table->dropColumn([
                'customer_approved_at',
                'customer_rejected_at',
                'customer_rejection_reason',
            ]);
        });
    }
};
