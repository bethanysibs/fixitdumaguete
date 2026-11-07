<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('worker_profiles', function (Blueprint $table) {
            $table->unsignedTinyInteger('years_experience')->nullable()->after('bio');
            $table->string('availability_note', 160)->nullable()->after('years_experience');
            $table->string('photo_path')->nullable()->after('availability_note');
        });

        Schema::create('worker_skills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('worker_profile_id')->constrained()->cascadeOnDelete();
            $table->string('name', 40);
            $table->timestamps();

            $table->unique(['worker_profile_id', 'name']);
        });

        Schema::create('worker_projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('worker_profile_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('summary');
            $table->unsignedSmallInteger('completed_year')->nullable();
            $table->timestamps();
        });

        Schema::create('worker_portfolio_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('worker_profile_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('caption', 160)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('worker_portfolio_images');
        Schema::dropIfExists('worker_projects');
        Schema::dropIfExists('worker_skills');

        Schema::table('worker_profiles', function (Blueprint $table) {
            $table->dropColumn(['years_experience', 'availability_note', 'photo_path']);
        });
    }
};
