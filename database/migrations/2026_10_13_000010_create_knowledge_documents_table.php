<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_documents', function (Blueprint $table) {
            $table->id();
            $table->string('title', 200);
            $table->string('slug', 200)->unique();
            $table->foreignId('category_id')->nullable()->constrained('activity_categories')->nullOnDelete();
            $table->longText('content');
            $table->string('source', 200)->nullable();
            $table->string('author', 120)->nullable();
            $table->string('reviewer', 120)->nullable();
            $table->string('status', 16)->default('draft');
            $table->unsignedInteger('version')->default(1);
            $table->date('effective_from')->nullable();
            $table->date('review_date')->nullable();
            $table->jsonb('tags')->nullable();
            $table->char('content_hash', 40);
            $table->timestampsTz();

            $table->index('status');
            $table->index('category_id');
        });

        DB::statement("ALTER TABLE knowledge_documents ADD CONSTRAINT knowledge_documents_status_check CHECK (status IN ('draft', 'review', 'approved', 'retired'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_documents');
    }
};
