<?php

use App\Jobs\ProcessRecordingTranscriptionJob;
use App\Models\Recording;
use App\Services\Transcription\PythonRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('transcription job updates recording status on success', function () {
    Storage::fake('recordings');
    Queue::fake();

    $recording = Recording::factory()->create([
        'status' => 'uploaded',
        'audio_path' => 'uploads/test.mp3',
    ]);

    $mockRunner = Mockery::mock(PythonRunner::class);
    $mockRunner->shouldReceive('transcribe')
        ->once()
        ->andReturn([
            'status' => 'success',
            'text' => 'Dette er en test transskription.',
            'model' => 'CoRal-project/roest-v3-whisper-1.5b',
            'language' => 'da',
            'runtime_ms' => 5000,
            'error' => null,
        ]);

    $job = new ProcessRecordingTranscriptionJob($recording);
    $job->handle($mockRunner);

    $recording->refresh();

    expect($recording->status)->toBe('transcribed');
    expect($recording->transcript_text)->toBe('Dette er en test transskription.');
    expect($recording->transcription_model)->toBe('CoRal-project/roest-v3-whisper-1.5b');
    expect($recording->transcription_completed_at)->not->toBeNull();
});

test('transcription job creates recording job record', function () {
    Storage::fake('recordings');
    Queue::fake();

    $recording = Recording::factory()->create([
        'status' => 'uploaded',
        'audio_path' => 'uploads/test.mp3',
    ]);

    $mockRunner = Mockery::mock(PythonRunner::class);
    $mockRunner->shouldReceive('transcribe')
        ->once()
        ->andReturn([
            'status' => 'success',
            'text' => 'Test.',
            'model' => 'CoRal-project/roest-v3-whisper-1.5b',
            'language' => 'da',
            'runtime_ms' => 1000,
            'error' => null,
        ]);

    $job = new ProcessRecordingTranscriptionJob($recording);
    $job->handle($mockRunner);

    $recording->refresh();

    expect($recording->recordingJobs)->toHaveCount(1);
    expect($recording->recordingJobs->first()->job_type)->toBe('transcription');
    expect($recording->recordingJobs->first()->status)->toBe('completed');
});

test('transcription job handles failure gracefully', function () {
    Storage::fake('recordings');
    Queue::fake();

    $recording = Recording::factory()->create([
        'status' => 'uploaded',
        'audio_path' => 'uploads/test.mp3',
    ]);

    $mockRunner = Mockery::mock(PythonRunner::class);
    $mockRunner->shouldReceive('transcribe')
        ->once()
        ->andReturn([
            'status' => 'error',
            'text' => '',
            'model' => '',
            'language' => 'da',
            'runtime_ms' => 100,
            'error' => 'Python process failed',
        ]);

    $job = new ProcessRecordingTranscriptionJob($recording);
    $job->handle($mockRunner);

    $recording->refresh();

    expect($recording->status)->toBe('failed');
    expect($recording->error_message)->toBe('Python process failed');
});

test('transcription job handles exception', function () {
    Storage::fake('recordings');
    Queue::fake();

    $recording = Recording::factory()->create([
        'status' => 'uploaded',
        'audio_path' => 'uploads/test.mp3',
    ]);

    $mockRunner = Mockery::mock(PythonRunner::class);
    $mockRunner->shouldReceive('transcribe')
        ->once()
        ->andThrow(new RuntimeException('File not found'));

    $job = new ProcessRecordingTranscriptionJob($recording);

    expect(fn () => $job->handle($mockRunner))->toThrow(RuntimeException::class);

    $recording->refresh();

    expect($recording->status)->toBe('failed');
    expect($recording->error_message)->toBe('File not found');
});
