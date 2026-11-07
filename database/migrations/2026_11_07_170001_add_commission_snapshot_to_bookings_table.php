<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->foreignId('commission_setting_id')->nullable()->after('worker_amount')->constrained('commission_settings')->nullOnDelete();
            $table->string('commission_rule')->nullable()->after('commission_setting_id');
            $table->string('commission_basis')->nullable()->after('commission_rule');
            $table->decimal('commission_base_amount', 10, 2)->nullable()->after('commission_basis');
            $table->decimal('commission_original_rate', 5, 2)->nullable()->after('commission_base_amount');
            $table->decimal('commission_adjustment', 10, 2)->nullable()->after('commission_original_rate');
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('commission_setting_id');
            $table->dropColumn([
                'commission_rule',
                'commission_basis',
                'commission_base_amount',
                'commission_original_rate',
                'commission_adjustment',
            ]);
        });
    }
};
