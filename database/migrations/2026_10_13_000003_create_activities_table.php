<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('activity_categories')->nullOnDelete();
            $table->string('title', 160);
            $table->text('description')->nullable();
            $table->timestampTz('start_at');
            $table->timestampTz('end_at');
            $table->string('timezone', 64);
            $table->string('location_name', 160);
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->string('status', 24)->default('planned');
            $table->jsonb('preferences')->nullable();
            $table->timestampsTz();

            $table->index(['user_id', 'start_at']);
            $table->index('category_id');
        });

        DB::statement('ALTER TABLE activities ADD CONSTRAINT activities_end_after_start_check CHECK (end_at > start_at)');
        DB::statement('ALTER TABLE activities ADD CONSTRAINT activities_latitude_check CHECK (latitude BETWEEN -90 AND 90)');
        DB::statement('ALTER TABLE activities ADD CONSTRAINT activities_longitude_check CHECK (longitude BETWEEN -180 AND 180)');
        DB::statement("ALTER TABLE activities ADD CONSTRAINT activities_status_check CHECK (status IN ('planned', 'completed', 'cancelled'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};
