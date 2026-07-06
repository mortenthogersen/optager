<?php

namespace App\Models;

use Database\Factories\RecordingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Recording extends Model
{
    /** @use HasFactory<RecordingFactory> */
    use HasFactory;

    protected $fillable = [
        'uuid',
        'title',
        'source_type',
        'status',
        'audio_disk',
        'audio_path',
        'audio_original_name',
        'audio_mime',
        'audio_size_bytes',
        'audio_checksum',
        'duration_seconds',
        'language',
        'transcript_text',
        'transcript_json',
        'summary_text',
        'summary_json',
        'transcription_model',
        'summary_model',
        'error_message',
        'transcription_started_at',
        'transcription_completed_at',
        'summary_started_at',
        'summary_completed_at',
        'processed_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'transcript_json' => 'array',
            'summary_json' => 'array',
            'transcription_started_at' => 'datetime',
            'transcription_completed_at' => 'datetime',
            'summary_started_at' => 'datetime',
            'summary_completed_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Recording $recording) {
            $recording->uuid ??= (string) Str::uuid();
        });
    }

    public function recordingJobs(): HasMany
    {
        return $this->hasMany(RecordingJob::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function startTranscriptionJob(): RecordingJob
    {
        return $this->recordingJobs()->create([
            'job_type' => 'transcription',
            'status' => 'queued',
        ]);
    }

    public function startSummaryJob(): RecordingJob
    {
        return $this->recordingJobs()->create([
            'job_type' => 'summary',
            'status' => 'queued',
        ]);
    }
}
