<?php

namespace App\Services\Transcription;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenRouterTranscriber
{
    private string $apiKey;

    private string $baseUrl;

    private string $model;

    public function __construct()
    {
        $this->apiKey = $this->resolveSetting('openrouter_api_key') ?: (string) config('services.openrouter.api_key');
        $this->baseUrl = 'https://openrouter.ai/api/v1';
        $this->model = $this->resolveSetting('openrouter_stt_model') ?: config('services.openrouter.stt_model', 'nvidia/parakeet-tdt-0.6b-v3');
    }

    private function resolveSetting(string $key): ?string
    {
        try {
            $value = Setting::get($key);

            return $value !== '' && $value !== null ? (string) $value : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function transcribe(string $audioPath, string $language = 'da'): array
    {
        if (! file_exists($audioPath)) {
            return [
                'status' => 'error',
                'text' => '',
                'model' => $this->model,
                'language' => $language,
                'runtime_ms' => 0,
                'error' => "Audio file not found: {$audioPath}",
            ];
        }

        $audioData = base64_encode(file_get_contents($audioPath));
        $format = $this->detectFormat($audioPath);

        Log::info('OpenRouter STT request', [
            'model' => $this->model,
            'audio_path' => $audioPath,
            'format' => $format,
            'size_bytes' => strlen($audioData),
        ]);

        $startTime = microtime(true);

        try {
            $response = Http::timeout(90)
                ->withToken($this->apiKey)
                ->withHeader('Content-Type', 'application/json')
                ->post("{$this->baseUrl}/audio/transcriptions", [
                    'model' => $this->model,
                    'input_audio' => [
                        'data' => $audioData,
                        'format' => $format,
                    ],
                    'language' => $language,
                ]);

            $runtimeMs = (int) ((microtime(true) - $startTime) * 1000);

            if (! $response->successful()) {
                $error = $response->json('error.message', $response->body());

                Log::error('OpenRouter STT error', [
                    'status' => $response->status(),
                    'error' => $error,
                ]);

                return [
                    'status' => 'error',
                    'text' => '',
                    'model' => $this->model,
                    'language' => $language,
                    'runtime_ms' => $runtimeMs,
                    'error' => "OpenRouter STT error (status {$response->status()}): {$error}",
                ];
            }

            $text = $response->json('text', '');
            $audioSeconds = $response->json('usage.seconds', 0);

            Log::info('OpenRouter STT completed', [
                'runtime_ms' => $runtimeMs,
                'text_length' => mb_strlen($text),
                'audio_seconds' => $audioSeconds,
                'usage' => $response->json('usage'),
            ]);

            return [
                'status' => 'success',
                'text' => $text,
                'model' => $this->model,
                'language' => $language,
                'runtime_ms' => $runtimeMs,
                'audio_duration_seconds' => (int) $audioSeconds,
                'error' => null,
            ];

        } catch (\Throwable $e) {
            $runtimeMs = (int) ((microtime(true) - $startTime) * 1000);

            Log::error('OpenRouter STT exception', ['error' => $e->getMessage()]);

            return [
                'status' => 'error',
                'text' => '',
                'model' => $this->model,
                'language' => $language,
                'runtime_ms' => $runtimeMs,
                'error' => $e->getMessage(),
            ];
        }
    }

    private function detectFormat(string $path): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($ext) {
            'mp3' => 'mp3',
            'wav' => 'wav',
            'flac' => 'flac',
            'm4a' => 'm4a',
            'ogg' => 'ogg',
            'webm' => 'webm',
            'aac' => 'aac',
            default => 'mp3',
        };
    }
}
