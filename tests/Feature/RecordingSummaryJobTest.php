<?php

use App\Jobs\GenerateMeetingSummaryJob;
use App\Models\Recording;
use App\Services\Summarization\DeepSeekClient;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('summary job updates recording with structured summary', function () {
    $recording = Recording::factory()->withTranscript()->create();

    $summaryResult = [
        'summary' => 'Et kort møderesumé.',
        'decisions' => ['Beslutning 1', 'Beslutning 2'],
        'action_items' => ['Opgave 1', 'Opgave 2'],
        'open_questions' => ['Spørgsmål 1'],
        'follow_up' => 'Næste møde om en uge.',
    ];

    $mockClient = Mockery::mock(DeepSeekClient::class);
    $mockClient->shouldReceive('summarizeMeeting')
        ->once()
        ->andReturn($summaryResult);

    $job = new GenerateMeetingSummaryJob($recording);
    $job->handle($mockClient);

    $recording->refresh();

    expect($recording->status)->toBe('completed');
    expect($recording->summary_json)->toBe($summaryResult);
    expect($recording->summary_model)->toBe('deepseek-v4-flash');
    expect($recording->summary_completed_at)->not->toBeNull();
    expect($recording->processed_at)->not->toBeNull();
});

test('summary job generates markdown from structured data', function () {
    $recording = Recording::factory()->withTranscript()->create();

    $summaryResult = [
        'summary' => 'Test resumé.',
        'decisions' => ['Beslutning A'],
        'action_items' => ['Handling B'],
        'open_questions' => [],
        'follow_up' => 'Følg op på fredag.',
    ];

    $mockClient = Mockery::mock(DeepSeekClient::class);
    $mockClient->shouldReceive('summarizeMeeting')
        ->once()
        ->andReturn($summaryResult);

    $job = new GenerateMeetingSummaryJob($recording);
    $job->handle($mockClient);

    $recording->refresh();

    expect($recording->summary_text)->toContain('## Resumé');
    expect($recording->summary_text)->toContain('## Beslutninger');
    expect($recording->summary_text)->toContain('## Handlingspunkter');
    expect($recording->summary_text)->toContain('## Opfølgning');
    expect($recording->summary_text)->toContain('Test resumé.');
    expect($recording->summary_text)->toContain('Beslutning A');
});

test('summary job fails without transcript text', function () {
    $recording = Recording::factory()->create([
        'status' => 'transcribed',
        'transcript_text' => null,
    ]);

    $mockClient = Mockery::mock(DeepSeekClient::class);

    $job = new GenerateMeetingSummaryJob($recording);

    expect(fn () => $job->handle($mockClient))->toThrow(RuntimeException::class);

    $recording->refresh();

    expect($recording->status)->toBe('failed');
});

test('summary job handles client exception', function () {
    $recording = Recording::factory()->withTranscript()->create();

    $mockClient = Mockery::mock(DeepSeekClient::class);
    $mockClient->shouldReceive('summarizeMeeting')
        ->once()
        ->andThrow(new RuntimeException('API error'));

    $job = new GenerateMeetingSummaryJob($recording);

    expect(fn () => $job->handle($mockClient))->toThrow(RuntimeException::class);

    $recording->refresh();

    expect($recording->status)->toBe('failed');
    expect($recording->error_message)->toBe('API error');
});
