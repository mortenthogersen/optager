<?php

namespace App\Jobs;

use App\Models\Recording;
use App\Services\Transcription\PythonRunner;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

#[Tries(1)]
class ProcessRecordingTranscriptionJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Recording $recording,
    ) {}

    public function handle(PythonRunner $pythonRunner): void
    {
        Log::info('ProcessRecordingTranscriptionJob started', [
            'recording_id' => $this->recording->id,
            'uuid' => $this->recording->uuid,
        ]);

        $recordingJob = $this->recording->startTranscriptionJob();

        try {
            $recordingJob->update([
                'status' => 'processing',
                'started_at' => now(),
            ]);

            $this->recording->update([
                'status' => 'transcribing',
                'transcription_started_at' => now(),
            ]);

            $audioFullPath = Storage::disk($this->recording->audio_disk)->path($this->recording->audio_path);

            $result = $pythonRunner->transcribe(
                audioPath: $audioFullPath,
                language: $this->recording->language ?? 'da',
            );

            if (($result['status'] ?? '') === 'error') {
                $errorMessage = $result['error'] ?? 'Unknown transcription error';

                Log::error('Transcription failed', [
                    'recording_id' => $this->recording->id,
                    'error' => $errorMessage,
                ]);

                $this->recording->update([
                    'status' => 'failed',
                    'error_message' => $errorMessage,
                    'transcription_completed_at' => now(),
                ]);

                $recordingJob->update([
                    'status' => 'failed',
                    'finished_at' => now(),
                    'error_message' => $errorMessage,
                ]);

                return;
            }

            $transcriptText = $result['text'] ?? '';
            $modelName = $result['model'] ?? 'CoRal-project/roest-v3-whisper-1.5b';

            $this->recording->update([
                'status' => 'transcribed',
                'transcript_text' => $transcriptText,
                'transcript_json' => $result,
                'transcription_model' => $modelName,
                'transcription_completed_at' => now(),
            ]);

            $recordingJob->update([
                'status' => 'completed',
                'finished_at' => now(),
                'result' => [
                    'model' => $modelName,
                    'runtime_ms' => $result['runtime_ms'] ?? 0,
                    'text_length' => mb_strlen($transcriptText),
                ],
            ]);

            Log::info('Transcription completed, dispatching summary job', [
                'recording_id' => $this->recording->id,
            ]);

            GenerateMeetingSummaryJob::dispatch($this->recording);

        } catch (\Throwable $e) {
            Log::error('ProcessRecordingTranscriptionJob exception', [
                'recording_id' => $this->recording->id,
                'error' => $e->getMessage(),
            ]);

            $this->recording->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'transcription_completed_at' => now(),
            ]);

            $recordingJob->update([
                'status' => 'failed',
                'finished_at' => now(),
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
