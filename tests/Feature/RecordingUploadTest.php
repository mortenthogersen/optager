<?php

use App\Jobs\ProcessRecordingTranscriptionJob;
use App\Models\Recording;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('user can create a recording record', function () {
    Storage::fake('recordings');
    $user = User::factory()->create();

    $recording = Recording::factory()->create([
        'created_by' => $user->id,
        'title' => 'Testmøde',
        'source_type' => 'manual_upload',
        'status' => 'uploaded',
    ]);

    expect($recording->title)->toBe('Testmøde');
    expect($recording->status)->toBe('uploaded');
    expect($recording->source_type)->toBe('manual_upload');
    expect($recording->uuid)->not->toBeEmpty();
});

test('creating a recording dispatches transcription job', function () {
    Queue::fake();

    $recording = Recording::factory()->create(['status' => 'uploaded']);

    ProcessRecordingTranscriptionJob::dispatch($recording);

    Queue::assertPushed(ProcessRecordingTranscriptionJob::class, function ($job) use ($recording) {
        return $job->recording->id === $recording->id;
    });
});

test('recording factory creates valid model', function () {
    $recording = Recording::factory()->create();

    expect($recording)->toBeInstanceOf(Recording::class);
    expect($recording->language)->toBe('da');
    expect($recording->audio_disk)->toBe('recordings');
});

test('recording with transcript state has correct data', function () {
    $recording = Recording::factory()->withTranscript()->create();

    expect($recording->status)->toBe('transcribed');
    expect($recording->transcript_text)->not->toBeEmpty();
    expect($recording->transcription_model)->toBe('CoRal-project/roest-v3-whisper-1.5b');
});

test('recording with summary state has correct data', function () {
    $recording = Recording::factory()->withTranscript()->withSummary()->create();

    expect($recording->status)->toBe('completed');
    expect($recording->summary_text)->not->toBeEmpty();
    expect($recording->summary_model)->toBe('deepseek-v4-flash');
    expect($recording->processed_at)->not->toBeNull();
});

test('recording failed state has error message', function () {
    $recording = Recording::factory()->failed()->create();

    expect($recording->status)->toBe('failed');
    expect($recording->error_message)->not->toBeEmpty();
});
