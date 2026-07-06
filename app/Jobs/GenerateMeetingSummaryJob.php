<?php

namespace App\Jobs;

use App\Models\Recording;
use App\Services\Summarization\DeepSeekClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Support\Facades\Log;

#[Tries(1)]
class GenerateMeetingSummaryJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Recording $recording,
    ) {}

    public function handle(DeepSeekClient $deepSeekClient): void
    {
        Log::info('GenerateMeetingSummaryJob started', [
            'recording_id' => $this->recording->id,
            'uuid' => $this->recording->uuid,
        ]);

        $recordingJob = $this->recording->startSummaryJob();

        try {
            $recordingJob->update([
                'status' => 'processing',
                'started_at' => now(),
            ]);

            $this->recording->update([
                'status' => 'summarizing',
                'summary_started_at' => now(),
            ]);

            $transcriptText = $this->recording->transcript_text;

            if (! $transcriptText) {
                throw new \RuntimeException('No transcript text available for summarization');
            }

            $model = config('services.deepseek.model', 'deepseek-v4-flash');

            $result = $deepSeekClient->summarizeMeeting(
                transcript: $transcriptText,
                model: $model,
            );

            $summaryText = $this->formatSummaryMarkdown($result);

            $this->recording->update([
                'status' => 'completed',
                'summary_text' => $summaryText,
                'summary_json' => $result,
                'summary_model' => $model,
                'summary_completed_at' => now(),
                'processed_at' => now(),
            ]);

            $recordingJob->update([
                'status' => 'completed',
                'finished_at' => now(),
                'result' => [
                    'model' => $model,
                ],
            ]);

            Log::info('GenerateMeetingSummaryJob completed', [
                'recording_id' => $this->recording->id,
            ]);

        } catch (\Throwable $e) {
            Log::error('GenerateMeetingSummaryJob exception', [
                'recording_id' => $this->recording->id,
                'error' => $e->getMessage(),
            ]);

            $this->recording->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'summary_completed_at' => now(),
            ]);

            $recordingJob->update([
                'status' => 'failed',
                'finished_at' => now(),
                'error_message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function formatSummaryMarkdown(array $result): string
    {
        $lines = [];

        $lines[] = '## Resumé';
        $lines[] = '';
        $lines[] = $this->stringify($result['summary'] ?? 'Intet resumé tilgængeligt');
        $lines[] = '';

        if (! empty($result['decisions'])) {
            $lines[] = '## Beslutninger';
            $lines[] = '';
            foreach ((array) $result['decisions'] as $decision) {
                $lines[] = '- '.$this->stringify($decision);
            }
            $lines[] = '';
        }

        if (! empty($result['action_items'])) {
            $lines[] = '## Handlingspunkter';
            $lines[] = '';
            foreach ((array) $result['action_items'] as $item) {
                $lines[] = '- '.$this->stringify($item);
            }
            $lines[] = '';
        }

        if (! empty($result['open_questions'])) {
            $lines[] = '## Åbne Spørgsmål';
            $lines[] = '';
            foreach ((array) $result['open_questions'] as $question) {
                $lines[] = '- '.$this->stringify($question);
            }
            $lines[] = '';
        }

        if (! empty($result['follow_up'])) {
            $lines[] = '## Opfølgning';
            $lines[] = '';
            $lines[] = $this->stringify($result['follow_up']);
            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    private function stringify(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }

        return (string) $value;
    }
}
