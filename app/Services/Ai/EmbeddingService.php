<?php

namespace App\Services\Ai;

use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Log;

class EmbeddingService
{
    public function __construct(
        private Factory $http,
        private array $config,
    ) {}

    public function enabled(): bool
    {
        return filled($this->config['provider'] ?? null)
            && filled($this->config['api_key'] ?? null)
            && filled($this->config['base_url'] ?? null)
            && filled($this->config['model'] ?? null);
    }

    public function model(): ?string
    {
        return $this->enabled() ? (string) $this->config['model'] : null;
    }

    /**
     * Genera el embedding de un texto. Devuelve null si no hay proveedor
     * configurado o si la solicitud falla (la RAG cae a palabras clave).
     *
     * @return array<int, float>|null
     */
    public function embed(string $text): ?array
    {
        if (! $this->enabled()) {
            return null;
        }

        try {
            $response = $this->http
                ->timeout(15)
                ->withToken((string) $this->config['api_key'])
                ->acceptJson()
                ->post(rtrim((string) $this->config['base_url'], '/').'/embeddings', [
                    'model' => $this->config['model'],
                    'input' => $text,
                ]);

            if ($response->failed()) {
                Log::warning('Embedding request failed', ['status' => $response->status()]);

                return null;
            }

            $vector = $response->json('data.0.embedding');

            return is_array($vector) ? $vector : null;
        } catch (\Throwable $exception) {
            Log::warning('Embedding request threw', ['message' => $exception->getMessage()]);

            return null;
        }
    }
}
