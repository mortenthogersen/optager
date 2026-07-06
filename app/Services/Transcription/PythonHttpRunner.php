<?php

namespace App\Services\Transcription;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PythonHttpRunner
{
    private string $serviceUrl;

    private int $timeout;

    public function __construct()
    {
        $this->serviceUrl = rtrim((string) config('services.transcription.service_url', 'http://127.0.0.1:9137'), '/');
        $this->timeout = (int) config('services.transcription.timeout', 3600);
    }

    public function transcribe(string $audioPath, string $language = 'da'): array
    {
        Log::info('Calling transcription HTTP service', [
            'url' => $this->serviceUrl,
            'audio_path' => $audioPath,
        ]);

        try {
            $response = Http::timeout($this->timeout)
                ->post("{$this->serviceUrl}/transcribe", [
                    'audio_path' => $audioPath,
                    'language' => $language,
                ]);

            if (! $response->successful()) {
                $error = $response->json('error', $response->body());

                Log::error('Transcription HTTP service returned error', [
                    'status' => $response->status(),
                    'error' => $error,
                ]);

                return [
                    'status' => 'error',
                    'text' => '',
                    'model' => '',
                    'language' => $language,
                    'runtime_ms' => 0,
                    'error' => "Transcription service error (status {$response->status()}): {$error}",
                ];
            }

            $result = $response->json();

            if (! is_array($result)) {
                throw new \RuntimeException('Invalid response from transcription service');
            }

            Log::info('Transcription HTTP service completed', [
                'runtime_ms' => $result['runtime_ms'] ?? 0,
                'text_length' => mb_strlen($result['text'] ?? ''),
            ]);

            return $result;

        } catch (\Throwable $e) {
            Log::error('Transcription HTTP service exception', ['error' => $e->getMessage()]);

            return [
                'status' => 'error',
                'text' => '',
                'model' => '',
                'language' => $language,
                'runtime_ms' => 0,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function healthCheck(): bool
    {
        try {
            $response = Http::timeout(5)->get("{$this->serviceUrl}/health");

            return $response->successful();
        } catch (\Throwable) {
            return false;
        }
    }
}
