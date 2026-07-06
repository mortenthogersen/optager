<?php

namespace App\Services\Summarization;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DeepSeekClient
{
    private string $apiKey;

    private string $baseUrl;

    private string $defaultModel;

    public function __construct()
    {
        $this->apiKey = (string) config('services.deepseek.api_key');
        $this->baseUrl = config('services.deepseek.base_url') ?: 'https://api.deepseek.com';
        $this->defaultModel = config('services.deepseek.model') ?: 'deepseek-v4-flash';
    }

    public function summarizeMeeting(string $transcript, ?string $model = null): array
    {
        $model ??= $this->defaultModel;

        $messages = [
            [
                'role' => 'system',
                'content' => $this->getSystemPrompt(),
            ],
            [
                'role' => 'user',
                'content' => "Her er en dansk mødetransskription. Generer et struktureret møderesumé som JSON.\n\nTransskription:\n{$transcript}",
            ],
        ];

        Log::info('DeepSeek API request', [
            'model' => $model,
            'transcript_length' => mb_strlen($transcript),
        ]);

        try {
            $response = Http::timeout(120)
                ->withToken($this->apiKey)
                ->withHeader('Content-Type', 'application/json')
                ->post("{$this->baseUrl}/chat/completions", [
                    'model' => $model,
                    'messages' => $messages,
                    'response_format' => ['type' => 'json_object'],
                    'temperature' => 0.3,
                    'max_tokens' => 4096,
                ]);

            if (! $response->successful()) {
                Log::error('DeepSeek API error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                throw new \RuntimeException(
                    "DeepSeek API returned status {$response->status()}: {$response->body()}"
                );
            }

            $data = $response->json();
            $content = $data['choices'][0]['message']['content'] ?? null;

            if (! $content) {
                throw new \RuntimeException('DeepSeek API returned empty content');
            }

            $parsed = json_decode($content, true);

            if (! is_array($parsed)) {
                throw new \RuntimeException('DeepSeek response is not valid JSON: '.$content);
            }

            Log::info('DeepSeek API success', [
                'usage' => $data['usage'] ?? null,
            ]);

            return $parsed;

        } catch (ConnectionException $e) {
            Log::error('DeepSeek connection error', ['error' => $e->getMessage()]);

            throw new \RuntimeException("DeepSeek connection failed: {$e->getMessage()}", 0, $e);
        }
    }

    private function getSystemPrompt(): string
    {
        return <<<'PROMPT'
Du er en dansk mødeassistent. Din opgave er at analysere en dansk mødetransskription
og generere et struktureret møderesumé.

Returner KUN et JSON-objekt med følgende felter (brug dansk):

- "summary": Et kort, sammenfattende resumé af mødet (2-4 sætninger)
- "decisions": En liste over de vigtigste beslutninger truffet på mødet
- "action_items": En liste over konkrete handlingspunkter med opgaver og ansvarlige hvis nævnt
- "open_questions": En liste over uafklarede spørgsmål eller emner der kræver opfølgning
- "follow_up": Forslag til næste skridt eller dato for opfølgning

Alle felter skal være på dansk. Hvis et felt ikke har relevant indhold, brug en tom liste eller "Ikke nævnt".
PROMPT;
    }
}
