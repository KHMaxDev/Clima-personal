<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_id')->constrained('activities')->cascadeOnDelete();
            $table->foreignId('weather_snapshot_id')->nullable()->constrained('weather_snapshots')->nullOnDelete();
            $table->unsignedSmallInteger('score')->nullable();
            $table->string('status', 32);
            $table->jsonb('reasons');
            $table->decimal('coverage', 4, 3)->nullable();
            $table->char('rule_version', 40)->nullable();
            $table->timestampTz('evaluated_at');
            $table->timestampsTz();

            $table->index(['activity_id', 'evaluated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_assessments');
    }
};
