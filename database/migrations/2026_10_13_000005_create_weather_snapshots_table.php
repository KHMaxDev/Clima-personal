<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weather_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 40)->default('open-meteo');
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->date('forecast_for');
            $table->smallInteger('forecast_days')->default(7);
            $table->timestampTz('fetched_at');
            $table->timestampTz('expires_at')->nullable();
            $table->jsonb('payload');
            $table->timestampsTz();

            $table->unique(['provider', 'latitude', 'longitude', 'forecast_for', 'forecast_days'], 'weather_snapshots_cache_unique');
            $table->index(['latitude', 'longitude', 'forecast_for'], 'weather_snapshots_location_idx');
        });

        DB::statement('ALTER TABLE weather_snapshots ADD CONSTRAINT weather_snapshots_latitude_check CHECK (latitude BETWEEN -90 AND 90)');
        DB::statement('ALTER TABLE weather_snapshots ADD CONSTRAINT weather_snapshots_longitude_check CHECK (longitude BETWEEN -180 AND 180)');
    }

    public function down(): void
    {
        Schema::dropIfExists('weather_snapshots');
    }
};
