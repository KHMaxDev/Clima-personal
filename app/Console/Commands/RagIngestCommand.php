<?php

namespace App\Console\Commands;

use App\Models\ActivityCategory;
use App\Models\KnowledgeChunk;
use App\Models\KnowledgeDocument;
use App\Services\Ai\EmbeddingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class RagIngestCommand extends Command
{
    protected $signature = 'rag:ingest
        {path : Archivo Markdown o directorio con documentos}
        {--approve : Marca los documentos ingeridos como aprobados}';

    protected $description = 'Ingiere documentos Markdown (front-matter YAML) en la base de conocimiento RAG';

    public function handle(EmbeddingService $embeddings): int
    {
        $path = $this->argument('path');

        if (! file_exists($path)) {
            $this->error("La ruta no existe: {$path}");

            return self::FAILURE;
        }

        $files = is_dir($path)
            ? glob(rtrim($path, '/\\').'/*.md') ?: []
            : [$path];

        if ($files === []) {
            $this->warn('No se encontraron archivos .md.');

            return self::SUCCESS;
        }

        $approved = (bool) $this->option('approve');
        $processed = 0;

        foreach ($files as $file) {
            if ($this->ingestFile($file, $approved, $embeddings)) {
                $processed++;
            }
        }

        $this->info("Documentos procesados: {$processed}.");

        return self::SUCCESS;
    }

    private function ingestFile(string $file, bool $approved, EmbeddingService $embeddings): bool
    {
        $raw = file_get_contents($file);

        if ($raw === false) {
            $this->warn("No se pudo leer {$file}.");

            return false;
        }

        [$meta, $body] = $this->splitFrontMatter($raw);

        if (($meta['title'] ?? null) === null) {
            $this->warn("Sin 'title' en front-matter: {$file}.");

            return false;
        }

        $slug = (string) ($meta['slug'] ?? Str::slug((string) $meta['title']));
        $contentHash = sha1(trim($body));
        $status = $approved ? 'approved' : (string) ($meta['status'] ?? 'draft');

        $existing = KnowledgeDocument::query()->where('slug', $slug)->first();

        if ($existing !== null && $existing->content_hash === $contentHash && $existing->status === $status) {
            $this->line("Sin cambios: {$slug}");

            return false;
        }

        $document = KnowledgeDocument::updateOrCreate(
            ['slug' => $slug],
            [
                'title' => (string) $meta['title'],
                'category_id' => $this->resolveCategory($meta['category'] ?? null),
                'content' => trim($body),
                'source' => $meta['source'] ?? null,
                'author' => $meta['author'] ?? null,
                'reviewer' => $meta['reviewer'] ?? null,
                'status' => $status,
                'version' => $existing !== null ? $existing->version + 1 : (int) ($meta['version'] ?? 1),
                'effective_from' => $meta['effective_from'] ?? null,
                'review_date' => $meta['review_date'] ?? null,
                'tags' => $meta['tags'] ?? null,
                'content_hash' => $contentHash,
            ],
        );

        $document->chunks()->delete();

        $chunks = $this->chunk($body);
        $embeddingModel = $embeddings->model();
        $canStoreEmbeddings = $embeddingModel !== null && $this->embeddingsColumnExists();

        foreach ($chunks as $index => $chunk) {
            $attributes = [
                'document_id' => $document->id,
                'content' => $chunk,
                'content_hash' => sha1($chunk),
                'token_count' => (int) round(mb_strlen($chunk) / 4),
                'metadata' => ['heading' => null, 'index' => $index],
            ];

            if ($canStoreEmbeddings) {
                $attributes['embedding_model'] = $embeddingModel;
            }

            $model = KnowledgeChunk::create($attributes);

            if ($canStoreEmbeddings) {
                $vector = $embeddings->embed($chunk);

                if (is_array($vector)) {
                    DB::table('knowledge_chunks')
                        ->where('id', $model->id)
                        ->update(['embedding' => '['.implode(',', $vector).']']);
                }
            }
        }

        $this->info("Ingerido: {$slug} ({$document->status}, v{$document->version}, ".count($chunks).' fragmentos).');

        return true;
    }

    private function resolveCategory(mixed $slug): ?int
    {
        if (! is_string($slug) || $slug === '') {
            return null;
        }

        return ActivityCategory::query()->where('slug', $slug)->value('id');
    }

    /**
     * @return array{0: array<string, mixed>|null, 1: string}
     */
    private function splitFrontMatter(string $raw): array
    {
        $trimmed = ltrim($raw);

        if (! str_starts_with($trimmed, '---')) {
            return [null, $raw];
        }

        $end = strpos($trimmed, "\n---", 3);

        if ($end === false) {
            return [null, $raw];
        }

        $yaml = substr($trimmed, 3, $end - 3);
        $body = ltrim(substr($trimmed, $end + 4), "\r\n");

        return [$this->parseYaml($yaml), $body];
    }

    /**
     * @return array<string, mixed>
     */
    private function parseYaml(string $yaml): array
    {
        $data = [];

        foreach (preg_split('/\r?\n/', $yaml) ?: [] as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#') || ! str_contains($line, ':')) {
                continue;
            }

            [$key, $value] = explode(':', $line, 2);
            $data[trim($key)] = $this->parseScalar(trim($value));
        }

        return $data;
    }

    private function parseScalar(string $value): mixed
    {
        if ($value === '' || $value === 'null' || $value === '~') {
            return null;
        }

        if (preg_match('/^\[(.*)\]$/', $value, $matches) === 1) {
            return array_values(array_filter(array_map(
                fn (string $item) => $this->unquote($item),
                explode(',', $matches[1]),
            ), fn (string $item) => $item !== ''));
        }

        return $this->unquote($value);
    }

    private function unquote(string $value): string
    {
        $value = trim($value);

        if (strlen($value) >= 2
            && (($value[0] === '"' && str_ends_with($value, '"'))
                || ($value[0] === "'" && str_ends_with($value, "'")))) {
            return substr($value, 1, -1);
        }

        return $value;
    }

    /**
     * @return string[]
     */
    private function chunk(string $body): array
    {
        $target = (int) config('climaquest.rag.chunk_target_tokens', 500) * 4;
        $blocks = preg_split('/\r?\n\r?\n/', trim($body)) ?: [];

        $chunks = [];
        $current = '';

        foreach ($blocks as $block) {
            $block = trim($block);

            if ($block === '') {
                continue;
            }

            if ($current !== '' && mb_strlen($current) + mb_strlen($block) > $target) {
                $chunks[] = trim($current);
                $current = mb_substr($current, -1 * (int) (config('climaquest.rag.chunk_overlap_tokens', 80) * 4));
            }

            $current .= ($current === '' ? '' : "\n\n").$block;
        }

        if (trim($current) !== '') {
            $chunks[] = trim($current);
        }

        return array_values(array_filter($chunks, fn (string $chunk) => $chunk !== ''));
    }

    private function embeddingsColumnExists(): bool
    {
        try {
            return Schema::hasColumn('knowledge_chunks', 'embedding');
        } catch (\Throwable) {
            return false;
        }
    }
}
