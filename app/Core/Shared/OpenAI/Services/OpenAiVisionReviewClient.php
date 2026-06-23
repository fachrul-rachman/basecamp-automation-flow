<?php

namespace App\Core\Shared\OpenAI\Services;

use App\Core\Shared\OpenAI\Contracts\VisionReviewClient;
use App\Core\Shared\OpenAI\Data\VisionReviewRequest;
use App\Core\Shared\OpenAI\Data\VisionReviewResponse;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\RequestException;
use Throwable;

class OpenAiVisionReviewClient implements VisionReviewClient
{
    public function __construct(private readonly HttpFactory $http) {}

    public function review(VisionReviewRequest $request): VisionReviewResponse
    {
        $attempts = max(1, (int) config('services.openai.vision_max_attempts', 2));

        $lastFailure = 'AI gagal membaca foto';

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            try {
                $response = $this->http
                    ->acceptJson()
                    ->withToken((string) config('services.openai.api_key'))
                    ->post('https://api.openai.com/v1/responses', $this->payload($request))
                    ->throw()
                    ->json();

                $json = $this->extractJson($response);

                if ($json !== null && $this->matchesSchema($json, $request->schema)) {
                    return VisionReviewResponse::success($json);
                }

                $lastFailure = 'AI mengembalikan JSON tidak valid';
            } catch (RequestException $exception) {
                $lastFailure = $this->safeHttpFailure($exception);
            } catch (Throwable) {
                $lastFailure = 'AI gagal membaca foto';
            }
        }

        return VisionReviewResponse::failure($lastFailure);
    }

    /** @return array<string, mixed> */
    private function payload(VisionReviewRequest $request): array
    {
        return [
            'model' => (string) config('services.openai.vision_model', 'gpt-4.1-mini'),
            'input' => [
                [
                    'role' => 'developer',
                    'content' => [
                        [
                            'type' => 'input_text',
                            'text' => $request->developerPrompt,
                        ],
                    ],
                ],
                [
                    'role' => 'user',
                    'content' => $this->userContent($request),
                ],
            ],
            'text' => [
                'format' => [
                    'type' => 'json_schema',
                    'name' => $request->schemaName,
                    'strict' => true,
                    'schema' => $request->schema,
                ],
            ],
        ];
    }

    /** @return list<array<string, string>> */
    private function userContent(VisionReviewRequest $request): array
    {
        $content = [
            [
                'type' => 'input_text',
                'text' => $request->userPrompt,
            ],
        ];

        foreach ($request->imageUrls as $imageUrl) {
            $content[] = [
                'type' => 'input_image',
                'image_url' => $imageUrl,
                'detail' => 'low',
            ];
        }

        return $content;
    }

    /**
     * @param  array<string, mixed>|null  $response
     * @return array<string, mixed>|null
     */
    private function extractJson(?array $response): ?array
    {
        if ($response === null) {
            return null;
        }

        $text = $response['output_text'] ?? null;

        if (is_string($text)) {
            return $this->decodeJson($text);
        }

        foreach (($response['output'] ?? []) as $outputItem) {
            if (! is_array($outputItem)) {
                continue;
            }

            foreach (($outputItem['content'] ?? []) as $contentItem) {
                if (! is_array($contentItem)) {
                    continue;
                }

                $text = $contentItem['text'] ?? null;

                if (is_string($text)) {
                    return $this->decodeJson($text);
                }
            }
        }

        return null;
    }

    /** @return array<string, mixed>|null */
    private function decodeJson(string $text): ?array
    {
        $decoded = json_decode($text, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param  array<string, mixed>  $json
     * @param  array<string, mixed>  $schema
     */
    private function matchesSchema(array $json, array $schema): bool
    {
        foreach (($schema['required'] ?? []) as $requiredKey) {
            if (! is_string($requiredKey) || ! array_key_exists($requiredKey, $json)) {
                return false;
            }
        }

        foreach (($schema['properties'] ?? []) as $key => $definition) {
            if (! is_string($key) || ! is_array($definition) || ! array_key_exists($key, $json)) {
                continue;
            }

            $value = $json[$key];

            if (($definition['type'] ?? null) === 'string') {
                if (! is_string($value)) {
                    return false;
                }

                if (isset($definition['enum']) && is_array($definition['enum']) && ! in_array($value, $definition['enum'], true)) {
                    return false;
                }
            }

            if (($definition['type'] ?? null) === 'array' && ! is_array($value)) {
                return false;
            }
        }

        return true;
    }

    private function safeHttpFailure(RequestException $exception): string
    {
        $status = $exception->response->status();
        $message = $exception->response->json('error.message');

        if (is_string($message) && $message !== '') {
            return "AI request gagal ({$status}): ".$this->sanitize($message);
        }

        return "AI request gagal ({$status})";
    }

    private function sanitize(string $message): string
    {
        $message = preg_replace('/Bearer\s+[A-Za-z0-9._\-]+/i', 'Bearer [redacted]', $message) ?? $message;
        $message = preg_replace('/sk-[A-Za-z0-9_\-]+/i', 'sk-[redacted]', $message) ?? $message;

        return mb_substr($message, 0, 240);
    }
}
