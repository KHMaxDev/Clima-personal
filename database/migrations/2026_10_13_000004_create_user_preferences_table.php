<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->time('preferred_start_time')->nullable();
            $table->time('preferred_end_time')->nullable();
            $table->boolean('notifications_enabled')->default(true);
            $table->boolean('email_notifications_enabled')->default(true);
            $table->smallInteger('max_wind_kmh')->nullable();
            $table->boolean('avoid_rain')->default(true);
            $table->boolean('uv_protection')->default(true);
            $table->text('notes')->nullable();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_preferences');
    }
};
