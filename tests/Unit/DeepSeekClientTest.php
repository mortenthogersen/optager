<?php

use App\Services\Summarization\DeepSeekClient;
use Illuminate\Support\Facades\Http;

test('deepseek client uses correct configuration', function () {
    config([
        'services.deepseek.api_key' => 'test-key',
        'services.deepseek.model' => 'deepseek-v4-flash',
        'services.deepseek.base_url' => 'https://api.deepseek.com',
    ]);

    $client = new DeepSeekClient;

    $reflection = new ReflectionClass($client);

    $apiKey = $reflection->getProperty('apiKey')->getValue($client);
    $model = $reflection->getProperty('defaultModel')->getValue($client);
    $baseUrl = $reflection->getProperty('baseUrl')->getValue($client);

    expect($apiKey)->toBe('test-key');
    expect($model)->toBe('deepseek-v4-flash');
    expect($baseUrl)->toBe('https://api.deepseek.com');
});

test('deepseek client uses fallback defaults', function () {
    config([
        'services.deepseek.api_key' => null,
        'services.deepseek.model' => null,
        'services.deepseek.base_url' => null,
    ]);

    $client = new DeepSeekClient;

    $reflection = new ReflectionClass($client);

    $model = $reflection->getProperty('defaultModel')->getValue($client);
    $baseUrl = $reflection->getProperty('baseUrl')->getValue($client);

    expect($model)->toBe('deepseek-v4-flash');
    expect($baseUrl)->toBe('https://api.deepseek.com');
});

test('deepseek client sends correct request format', function () {
    Http::fake([
        'api.deepseek.com/*' => Http::response([
            'choices' => [
                [
                    'message' => [
                        'content' => json_encode([
                            'summary' => 'Test summary.',
                            'decisions' => [],
                            'action_items' => [],
                            'open_questions' => [],
                            'follow_up' => 'Follow up.',
                        ]),
                    ],
                ],
            ],
            'usage' => ['total_tokens' => 100],
        ], 200),
    ]);

    config([
        'services.deepseek.api_key' => 'test-key',
        'services.deepseek.model' => 'deepseek-v4-flash',
        'services.deepseek.base_url' => 'https://api.deepseek.com',
    ]);

    $client = new DeepSeekClient;
    $result = $client->summarizeMeeting('Test transcript');

    expect($result)->toBeArray();
    expect($result['summary'])->toBe('Test summary.');
    expect($result['follow_up'])->toBe('Follow up.');

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.deepseek.com/chat/completions'
            && $request['model'] === 'deepseek-v4-flash'
            && $request['response_format'] === ['type' => 'json_object'];
    });
});

test('deepseek client handles api error', function () {
    Http::fake([
        'api.deepseek.com/*' => Http::response(['error' => 'Invalid API key'], 401),
    ]);

    config([
        'services.deepseek.api_key' => 'bad-key',
        'services.deepseek.model' => 'deepseek-v4-flash',
        'services.deepseek.base_url' => 'https://api.deepseek.com',
    ]);

    $client = new DeepSeekClient;

    expect(fn () => $client->summarizeMeeting('Test transcript'))
        ->toThrow(RuntimeException::class);
});
