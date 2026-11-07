<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commission_settings', function (Blueprint $table) {
            $table->dropColumn('default_rate');
        });
    }

    public function down(): void
    {
        Schema::table('commission_settings', function (Blueprint $table) {
            $table->decimal('default_rate', 5, 2)->default('10.00')->after('effective_at');
        });
    }
};
