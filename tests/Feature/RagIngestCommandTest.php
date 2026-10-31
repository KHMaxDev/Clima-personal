<?php

namespace Tests\Feature;

use App\Models\ActivityCategory;
use App\Models\KnowledgeChunk;
use App\Models\KnowledgeDocument;
use Database\Seeders\ActivityCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RagIngestCommandTest extends TestCase
{
    use RefreshDatabase;

    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = sys_get_temp_dir().'/climaquest-rag-'.uniqid();
        mkdir($this->directory, 0777, true);

        $this->seed(ActivityCategorySeeder::class);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory.'/*') ?: [] as $file) {
            @unlink($file);
        }

        @rmdir($this->directory);

        parent::tearDown();
    }

    public function test_ingests_markdown_with_front_matter_and_approves(): void
    {
        file_put_contents($this->directory.'/running.md', $this->markdown());

        $this->artisan('rag:ingest', [
            'path' => $this->directory,
            '--approve' => true,
        ])->assertExitCode(0);

        $running = ActivityCategory::where('slug', 'running')->firstOrFail();

        $document = KnowledgeDocument::where('slug', 'running-test')->first();

        $this->assertNotNull($document);
        $this->assertSame('approved', $document->status);
        $this->assertSame($running->id, $document->category_id);
        $this->assertSame(['calor', 'hidratacion'], $document->tags);
        $this->assertGreaterThan(0, KnowledgeChunk::where('document_id', $document->id)->count());
    }

    public function test_reingesting_approved_content_is_idempotent(): void
    {
        file_put_contents($this->directory.'/running.md', $this->markdown());

        $this->artisan('rag:ingest', ['path' => $this->directory, '--approve' => true]);
        $firstChunks = KnowledgeChunk::count();

        $this->artisan('rag:ingest', ['path' => $this->directory, '--approve' => true]);

        $this->assertSame(1, KnowledgeDocument::count());
        $this->assertSame($firstChunks, KnowledgeChunk::count());
        $this->assertSame(1, KnowledgeDocument::first()->version);
    }

    public function test_skips_file_without_title(): void
    {
        file_put_contents($this->directory.'/no-title.md', "---\ncategory: running\n---\n\n# Sin título\n");

        $this->artisan('rag:ingest', ['path' => $this->directory, '--approve' => true])
            ->assertExitCode(0);

        $this->assertSame(0, KnowledgeDocument::count());
    }

    private function markdown(): string
    {
        return <<<'MD'
        ---
        title: "Carrera de prueba"
        slug: "running-test"
        category: running
        tags: [calor, hidratacion]
        status: draft
        version: 1
        effective_from: 2026-07-01
        ---

        # Introducción

        Texto de prueba sobre el calor y la hidratación al correr.

        # Recomendaciones

        Beber agua antes, durante y después del esfuerzo.
        MD;
    }
}
