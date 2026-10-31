<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained('activity_categories')->nullOnDelete();
            $table->string('code', 80);
            $table->string('variable', 64);
            $table->string('operator', 16);
            $table->decimal('threshold_a', 8, 3)->nullable();
            $table->decimal('threshold_b', 8, 3)->nullable();
            $table->string('severity', 16)->default('warning');
            $table->smallInteger('score_impact')->default(0);
            $table->boolean('essential')->default(false);
            $table->text('message_es');
            $table->boolean('active')->default(true);
            $table->unsignedInteger('version')->default(1);
            $table->timestampsTz();

            $table->index(['category_id', 'active']);
        });

        DB::statement('CREATE UNIQUE INDEX activity_rules_scope_code_unique ON activity_rules (category_id, code) WHERE category_id IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX activity_rules_global_code_unique ON activity_rules (code) WHERE category_id IS NULL');
        DB::statement("ALTER TABLE activity_rules ADD CONSTRAINT activity_rules_operator_check CHECK (operator IN ('lt', 'lte', 'gt', 'gte', 'between', 'outside'))");
        DB::statement("ALTER TABLE activity_rules ADD CONSTRAINT activity_rules_severity_check CHECK (severity IN ('info', 'warning', 'critical'))");
        DB::statement('ALTER TABLE activity_rules ADD CONSTRAINT activity_rules_score_impact_check CHECK (score_impact BETWEEN 0 AND 100)');
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_rules');
    }
};
