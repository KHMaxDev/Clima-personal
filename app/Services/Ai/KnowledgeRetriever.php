<?php

namespace App\Services\Ai;

use App\Models\KnowledgeDocument;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class KnowledgeRetriever
{
    private const STOPWORDS = [
        'para', 'como', 'sobre', 'entre', 'cuando', 'donde', 'porque', 'este', 'esta',
        'estos', 'estas', 'con', 'los', 'las', 'del', 'una', 'uno', 'unos', 'unas',
        'que', 'por', 'mas', 'más', 'sin', 'sus', 'pero', 'muy', 'hay', 'ser', 'son',
    ];

    public function __construct(
        private EmbeddingService $embeddings,
        private array $config,
    ) {}

    /**
     * Recupera fragmentos de documentos aprobados relevantes para la consulta.
     *
     * @return array<int, array{id: int|string, document_id: int, title: string, content: string, source: ?string}>
     */
    public function retrieve(string $query, ?int $categoryId = null, ?int $limit = null): array
    {
        if (! ($this->config['enabled'] ?? false)) {
            return [];
        }

        $limit ??= (int) ($this->config['top_k'] ?? 4);

        if ($this->embeddings->enabled() && $this->vectorAvailable()) {
            $results = $this->vectorSearch($query, $categoryId, $limit);

            if ($results !== []) {
                return $results;
            }
        }

        return $this->keywordSearch($query, $categoryId, $limit);
    }

    private function vectorAvailable(): bool
    {
        try {
            return Schema::hasColumn('knowledge_chunks', 'embedding');
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return array<int, array{id: int|string, document_id: int, title: string, content: string, source: ?string}>
     */
    private function vectorSearch(string $query, ?int $categoryId, int $limit): array
    {
        $embedding = $this->embeddings->embed($query);

        if ($embedding === null) {
            return [];
        }

        $vector = '['.implode(',', array_map(fn ($value) => (float) $value, $embedding)).']';

        $sql = <<<'SQL'
            SELECT kc.id, kc.document_id, kc.content, kd.title, kd.source
            FROM knowledge_chunks kc
            JOIN knowledge_documents kd ON kd.id = kc.document_id
            WHERE kd.status = 'approved'
              AND (kd.review_date IS NULL OR kd.review_date >= CURRENT_DATE)
              AND kc.embedding IS NOT NULL
        SQL;

        $bindings = [];

        if ($categoryId !== null) {
            $sql .= ' AND (kd.category_id = ? OR kd.category_id IS NULL)';
            $bindings[] = $categoryId;
        }

        $sql .= ' ORDER BY kc.embedding <=> ?::vector LIMIT ?';
        $bindings[] = $vector;
        $bindings[] = $limit;

        try {
            return array_map(fn ($row) => (array) $row, DB::select($sql, $bindings));
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return array<int, array{id: int|string, document_id: int, title: string, content: string, source: ?string}>
     */
    private function keywordSearch(string $query, ?int $categoryId, int $limit): array
    {
        $terms = $this->terms($query);

        if ($terms === []) {
            return [];
        }

        $documents = KnowledgeDocument::query()
            ->where('status', 'approved')
            ->where(function ($builder) {
                $builder->whereNull('review_date')
                    ->orWhere('review_date', '>=', now()->toDateString());
            })
            ->when($categoryId !== null, function ($builder) use ($categoryId) {
                $builder->where(function ($nested) use ($categoryId) {
                    $nested->where('category_id', $categoryId)->orWhereNull('category_id');
                });
            })
            ->limit(50)
            ->get();

        $ranked = $documents
            ->map(function (KnowledgeDocument $document) use ($terms) {
                $haystack = mb_strtolower(
                    $document->title.' '.$document->content.' '.implode(' ', $document->tags ?? []),
                );

                $score = 0;
                foreach ($terms as $term) {
                    $score += mb_substr_count($haystack, $term);
                }

                return ['document' => $document, 'score' => $score];
            })
            ->filter(fn (array $item) => $item['score'] > 0)
            ->sortByDesc('score')
            ->take($limit);

        return $ranked->map(fn (array $item) => [
            'id' => $item['document']->id,
            'document_id' => $item['document']->id,
            'title' => $item['document']->title,
            'content' => mb_substr($item['document']->content, 0, 1200),
            'source' => $item['document']->source,
        ])->values()->all();
    }

    /**
     * @return string[]
     */
    private function terms(string $query): array
    {
        $tokens = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($query)) ?: [];

        return array_values(array_unique(array_filter(
            $tokens,
            fn (string $token) => mb_strlen($token) > 3 && ! in_array($token, self::STOPWORDS, true),
        )));
    }
}
