<?php

namespace Database\Factories;

use App\Models\Recording;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class RecordingFactory extends Factory
{
    protected $model = Recording::class;

    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'title' => fake()->optional()->sentence(3),
            'source_type' => 'manual_upload',
            'status' => 'uploaded',
            'audio_disk' => 'recordings',
            'audio_path' => 'recordings/'.fake()->uuid().'.mp3',
            'audio_original_name' => fake()->word().'.mp3',
            'audio_mime' => 'audio/mpeg',
            'audio_size_bytes' => fake()->numberBetween(1000, 10000000),
            'audio_checksum' => fake()->sha256(),
            'duration_seconds' => fake()->optional()->numberBetween(60, 3600),
            'language' => 'da',
        ];
    }

    public function withTranscript(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'transcribed',
            'transcript_text' => fake()->paragraphs(3, true),
            'transcript_json' => [
                'text' => fake()->paragraphs(3, true),
                'model' => 'CoRal-project/roest-v3-whisper-1.5b',
                'runtime_ms' => fake()->numberBetween(5000, 60000),
            ],
            'transcription_model' => 'CoRal-project/roest-v3-whisper-1.5b',
            'transcription_started_at' => now()->subMinutes(5),
            'transcription_completed_at' => now()->subMinutes(2),
        ]);
    }

    public function withSummary(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'summary_text' => "## Resumé\n\n".fake()->paragraph()."\n\n## Beslutninger\n\n- ".fake()->sentence(),
            'summary_json' => [
                'summary' => fake()->paragraph(),
                'decisions' => [fake()->sentence(), fake()->sentence()],
                'action_items' => [fake()->sentence(), fake()->sentence()],
                'open_questions' => [fake()->sentence()],
                'follow_up' => fake()->sentence(),
            ],
            'summary_model' => 'deepseek-v4-flash',
            'summary_started_at' => now()->subMinutes(2),
            'summary_completed_at' => now()->subMinute(),
            'processed_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'error_message' => fake()->sentence(),
        ]);
    }
}
