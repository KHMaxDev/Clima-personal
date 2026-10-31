<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('activity_id')->nullable()->constrained('activities')->nullOnDelete();
            $table->string('type', 40);
            $table->string('channel', 24)->default('mail');
            $table->string('dedup_key', 40)->unique();
            $table->timestampTz('scheduled_for');
            $table->timestampTz('sent_at')->nullable();
            $table->string('status', 16)->default('pending');
            $table->jsonb('payload')->nullable();
            $table->timestampsTz();

            $table->index(['user_id', 'scheduled_for']);
            $table->index(['status', 'scheduled_for']);
        });

        DB::statement("ALTER TABLE notifications ADD CONSTRAINT notifications_status_check CHECK (status IN ('pending', 'sent', 'skipped', 'failed'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
