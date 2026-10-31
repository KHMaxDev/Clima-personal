<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! $this->pgvectorAvailable()) {
            return;
        }

        DB::statement('CREATE EXTENSION IF NOT EXISTS vector');

        DB::statement('ALTER TABLE knowledge_chunks ADD COLUMN embedding vector(1536)');
        DB::statement('ALTER TABLE knowledge_chunks ADD COLUMN embedding_model varchar(80)');

        DB::statement('CREATE INDEX knowledge_chunks_embedding_hnsw ON knowledge_chunks USING hnsw (embedding vector_cosine_ops)');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS knowledge_chunks_embedding_hnsw');
        DB::statement('ALTER TABLE knowledge_chunks DROP COLUMN IF EXISTS embedding');
        DB::statement('ALTER TABLE knowledge_chunks DROP COLUMN IF EXISTS embedding_model');
    }

    private function pgvectorAvailable(): bool
    {
        return DB::selectOne("SELECT 1 FROM pg_available_extensions WHERE name = 'vector'") !== null;
    }
};
