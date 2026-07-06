<?php

use App\Services\Transcription\PythonRunner;

test('python runner constructs correct command', function () {
    config([
        'services.transcription.python_path' => 'python3',
        'services.transcription.script_path' => '/app/python/transcribe.py',
    ]);

    $runner = new PythonRunner;

    $reflection = new ReflectionClass($runner);

    $pythonPath = $reflection->getProperty('pythonPath')->getValue($runner);
    $scriptPath = $reflection->getProperty('scriptPath')->getValue($runner);

    expect($pythonPath)->toBe('python3');
    expect($scriptPath)->toBe('/app/python/transcribe.py');
});

test('python runner uses default config', function () {
    config([
        'services.transcription.python_path' => null,
        'services.transcription.script_path' => null,
    ]);

    $runner = new PythonRunner;

    $reflection = new ReflectionClass($runner);

    $pythonPath = $reflection->getProperty('pythonPath')->getValue($runner);
    $scriptPath = $reflection->getProperty('scriptPath')->getValue($runner);

    $expectedPython = PHP_OS_FAMILY === 'Windows' ? 'python' : 'python3';

    expect($pythonPath)->toBe($expectedPython);
    expect($scriptPath)->toContain('python/transcribe.py');
});
