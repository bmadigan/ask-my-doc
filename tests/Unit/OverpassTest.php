<?php

use Bmadigan\Overpass\Services\PythonAiBridge;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Process;

uses(TestCase::class);

it('can check health status', function () {
    Process::fake([
        '*' => Process::result(
            output: json_encode(['status' => 'success', 'message' => 'All systems operational']),
        ),
    ]);

    $overpass = new PythonAiBridge;

    $status = $overpass->testConnection();

    expect($status)->toBeArray();
    expect($status)->toHaveKeys(['status', 'message']);
});

it('can generate embeddings', function () {
    Process::fake([
        '*' => Process::result(
            output: json_encode([
                'data' => [
                    'embeddings' => [array_fill(0, 1536, 0.1)],
                    'model' => 'text-embedding-3-small',
                ],
            ]),
        ),
    ]);

    $overpass = new PythonAiBridge;
    $result = $overpass->generateEmbedding('test text');

    expect($result)->toBeArray();
    expect($result)->toHaveKey('embedding');
    expect($result['embedding'])->toHaveCount(1536);
    expect($result)->toHaveKey('model');
    expect($result)->toHaveKey('dimension');
});

it('can analyze data', function () {
    Process::fake([
        '*' => Process::result(
            output: json_encode([
                'success' => true,
                'analysis' => 'Data analysis result',
            ]),
        ),
    ]);

    $overpass = new PythonAiBridge;
    $result = $overpass->analyzeData(['data' => 'test data']);

    expect($result)->toBeArray();
    expect($result)->toHaveKey('success', true);
});

it('respects max output configuration', function () {
    config(['overpass.max_output_length' => 100]);

    Process::fake([
        '*' => Process::result(
            output: str_repeat('a', 200), // Larger than max
        ),
    ]);

    $overpass = new PythonAiBridge;

    expect(fn () => $overpass->execute('health_check', []))
        ->toThrow(Exception::class);
});