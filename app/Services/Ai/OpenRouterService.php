<?php

namespace App\Services\Ai;

use App\Exceptions\AiUnavailableException;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Log;

class OpenRouterService
{
    public function __construct(
        private Factory $http,
        private array $config,
    ) {}

    public function enabled(): bool
    {
        return filled($this->config['api_key'] ?? null);
    }

    public function model(): string
    {
        return (string) ($this->config['model'] ?? 'openrouter/free');
    }

    /**
     * Envía una conversación al endpoint compatible con OpenAI de OpenRouter.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     * @param  array<string, mixed>  $options
     * @return array{content: string, model: string, usage: array<string, mixed>|null}
     */
    public function chat(array $messages, array $options = []): array
    {
        if (! $this->enabled()) {
            throw new AiUnavailableException('El asistente de IA no está configurado.');
        }

        $payload = array_merge([
            'model' => $this->model(),
            'messages' => $messages,
            'max_tokens' => (int) ($this->config['max_tokens'] ?? 800),
            'temperature' => 0.2,
        ], $options);

        $response = $this->http
            ->timeout((int) ($this->config['timeout'] ?? 20))
            ->retry(2, 300, throw: false)
            ->withHeaders([
                'Authorization' => 'Bearer '.$this->config['api_key'],
                'HTTP-Referer' => (string) config('app.url'),
                'X-Title' => (string) config('app.name', 'ClimaQuest'),
            ])
            ->acceptJson()
            ->post(rtrim((string) $this->config['base_url'], '/').'/chat/completions', $payload);

        if ($response->failed()) {
            Log::warning('OpenRouter request failed', [
                'status' => $response->status(),
            ]);

            throw new AiUnavailableException('El asistente no está disponible en este momento.');
        }

        $content = $response->json('choices.0.message.content');

        if (! is_string($content) || trim($content) === '') {
            throw new AiUnavailableException('El asistente devolvió una respuesta vacía.');
        }

        return [
            'content' => $content,
            'model' => (string) ($response->json('model') ?? $payload['model']),
            'usage' => $response->json('usage'),
        ];
    }
}
