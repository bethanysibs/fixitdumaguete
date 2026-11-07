<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->timestamp('submitted_at')->nullable()->after('submitted_by');
        });

        Schema::create('payment_instructions', function (Blueprint $table) {
            $table->id();
            $table->string('method');
            $table->string('account_name')->nullable();
            $table->string('account_number')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('qr_path')->nullable();
            $table->text('instructions')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['method', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_instructions');

        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('submitted_at');
        });
    }
};
