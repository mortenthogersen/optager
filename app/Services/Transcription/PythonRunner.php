<?php

namespace App\Services\Transcription;

use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class PythonRunner
{
    private string $pythonPath;

    private string $scriptPath;

    public function __construct()
    {
        $this->pythonPath = (string) (config('services.transcription.python_path') ?: (PHP_OS_FAMILY === 'Windows' ? 'python' : 'python3'));
        $this->scriptPath = (string) (config('services.transcription.script_path') ?: base_path('python/transcribe.py'));
    }

    public function transcribe(string $audioPath, string $language = 'da', ?int $timeout = null): array
    {
        $timeout ??= 3600;

        $outputPath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'transcript_'.uniqid().'.json';

        $command = [
            $this->pythonPath,
            $this->scriptPath,
            '--input', $audioPath,
            '--output-json', $outputPath,
            '--language', $language,
            '--device', config('services.transcription.device', 'auto'),
        ];

        Log::info('Starting Python transcription', [
            'command' => implode(' ', $command),
            'audio_path' => $audioPath,
        ]);

        $process = new Process($command);
        $process->setTimeout($timeout);

        try {
            $process->run();

            if (! $process->isSuccessful()) {
                $error = $process->getErrorOutput();

                Log::error('Python transcription failed', [
                    'exit_code' => $process->getExitCode(),
                    'error' => $error,
                    'stdout' => $process->getOutput(),
                ]);

                return [
                    'status' => 'error',
                    'text' => '',
                    'model' => '',
                    'language' => $language,
                    'runtime_ms' => 0,
                    'error' => "Python process failed (exit code {$process->getExitCode()}): {$error}",
                ];
            }

            if (! file_exists($outputPath)) {
                throw new \RuntimeException("Python script did not produce output file: {$outputPath}");
            }

            $jsonContent = file_get_contents($outputPath);
            $result = json_decode($jsonContent, true);

            if (! is_array($result)) {
                throw new \RuntimeException("Python output is not valid JSON: {$jsonContent}");
            }

            Log::info('Python transcription completed', [
                'runtime_ms' => $result['runtime_ms'] ?? 0,
                'text_length' => mb_strlen($result['text'] ?? ''),
            ]);

            @unlink($outputPath);

            return $result;

        } catch (\Throwable $e) {
            Log::error('Python transcription exception', ['error' => $e->getMessage()]);

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
}
