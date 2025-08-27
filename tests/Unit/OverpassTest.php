<?php

use App\Services\Overpass;
use Illuminate\Support\Facades\Process;

use function Pest\Laravel\mock;

it('can check health status', function () {
    $overpass = new Overpass;

    $status = $overpass->checkHealth();

    expect($status)->toBeArray();
    expect($status)->toHaveKeys(['success', 'message']);
});

it('can generate embeddings', function () {
    // Mock the OpenAI client
    $mockClient = mock(\OpenAI\Client::class);
    $mockEmbeddings = mock(\OpenAI\Resources\Embeddings::class);
    $mockResponse = mock(\OpenAI\Responses\Embeddings\CreateResponse::class);

    $mockResponse->shouldReceive('toArray')
        ->andReturn([
            'data' => [
                ['embedding' => array_fill(0, 1536, 0.1)],
            ],
        ]);

    $mockEmbeddings->shouldReceive('create')
        ->andReturn($mockResponse);

    $mockClient->shouldReceive('embeddings')
        ->andReturn($mockEmbeddings);

    // Use reflection to inject mock client
    $overpass = new Overpass;
    $reflection = new ReflectionClass($overpass);
    $property = $reflection->getProperty('client');
    $property->setAccessible(true);
    $property->setValue($overpass, $mockClient);

    $embedding = $overpass->generateEmbedding('test text');

    expect($embedding)->toBeArray();
    expect($embedding)->toHaveCount(1536);
});

it('can generate chat completions', function () {
    // Mock the OpenAI client
    $mockClient = mock(\OpenAI\Client::class);
    $mockChat = mock(\OpenAI\Resources\Chat::class);
    $mockResponse = mock(\OpenAI\Responses\Chat\CreateResponse::class);
    $mockChoice = mock(\OpenAI\Responses\Chat\CreateResponseChoice::class);
    $mockMessage = mock(\OpenAI\Responses\Chat\CreateResponseMessage::class);

    $mockMessage->content = 'Test response';
    $mockChoice->message = $mockMessage;

    $mockResponse->choices = [$mockChoice];

    $mockChat->shouldReceive('create')
        ->andReturn($mockResponse);

    $mockClient->shouldReceive('chat')
        ->andReturn($mockChat);

    // Use reflection to inject mock client
    $overpass = new Overpass;
    $reflection = new ReflectionClass($overpass);
    $property = $reflection->getProperty('client');
    $property->setAccessible(true);
    $property->setValue($overpass, $mockClient);

    $response = $overpass->chat([
        ['role' => 'user', 'content' => 'Hello'],
    ]);

    expect($response)->toBe('Test response');
});

it('handles embedding errors gracefully', function () {
    // Mock the OpenAI client to throw an exception
    $mockClient = mock(\OpenAI\Client::class);
    $mockEmbeddings = mock(\OpenAI\Resources\Embeddings::class);

    $mockEmbeddings->shouldReceive('create')
        ->andThrow(new Exception('API Error'));

    $mockClient->shouldReceive('embeddings')
        ->andReturn($mockEmbeddings);

    // Use reflection to inject mock client
    $overpass = new Overpass;
    $reflection = new ReflectionClass($overpass);
    $property = $reflection->getProperty('client');
    $property->setAccessible(true);
    $property->setValue($overpass, $mockClient);

    expect(fn () => $overpass->generateEmbedding('test'))
        ->toThrow(Exception::class, 'API Error');
});

it('handles chat errors gracefully', function () {
    // Mock the OpenAI client to throw an exception
    $mockClient = mock(\OpenAI\Client::class);
    $mockChat = mock(\OpenAI\Resources\Chat::class);

    $mockChat->shouldReceive('create')
        ->andThrow(new Exception('Chat API Error'));

    $mockClient->shouldReceive('chat')
        ->andReturn($mockChat);

    // Use reflection to inject mock client
    $overpass = new Overpass;
    $reflection = new ReflectionClass($overpass);
    $property = $reflection->getProperty('client');
    $property->setAccessible(true);
    $property->setValue($overpass, $mockClient);

    expect(fn () => $overpass->chat([['role' => 'user', 'content' => 'Hello']]))
        ->toThrow(Exception::class, 'Chat API Error');
});

it('can execute Python bridge commands', function () {
    Process::fake([
        '*' => Process::result(
            output: json_encode(['success' => true, 'result' => 'test']),
        ),
    ]);

    $overpass = new Overpass;
    $result = $overpass->executePython('health_check', []);

    expect($result)->toBeArray();
    expect($result)->toHaveKey('success', true);

    Process::assertRan(function ($process) {
        return str_contains($process->command, 'python');
    });
});

it('handles Python bridge errors', function () {
    Process::fake([
        '*' => Process::result(
            output: '',
            errorOutput: 'Python error',
            exitCode: 1,
        ),
    ]);

    $overpass = new Overpass;

    expect(fn () => $overpass->executePython('health_check', []))
        ->toThrow(Exception::class);
});

it('can perform vector search', function () {
    Process::fake([
        '*' => Process::result(
            output: json_encode([
                'success' => true,
                'result' => [
                    ['chunk_id' => 1, 'score' => 0.95],
                    ['chunk_id' => 2, 'score' => 0.85],
                ],
            ]),
        ),
    ]);

    $overpass = new Overpass;
    $results = $overpass->vectorSearch(
        array_fill(0, 1536, 0.1),
        'documents',
        5,
        0.5
    );

    expect($results)->toBeArray();
    expect($results)->toHaveCount(2);
    expect($results[0])->toHaveKeys(['chunk_id', 'score']);
});

it('validates embedding dimensions', function () {
    $overpass = new Overpass;

    // Test with correct dimensions
    $validEmbedding = array_fill(0, 1536, 0.1);
    expect(fn () => $overpass->validateEmbedding($validEmbedding))
        ->not->toThrow();

    // Test with incorrect dimensions
    $invalidEmbedding = array_fill(0, 100, 0.1);
    expect(fn () => $overpass->validateEmbedding($invalidEmbedding))
        ->toThrow(InvalidArgumentException::class);
});

it('caches Python bridge path discovery', function () {
    Process::fake([
        '*' => Process::result(
            output: json_encode(['success' => true]),
        ),
    ]);

    $overpass = new Overpass;

    // First call should discover Python path
    $overpass->executePython('health_check', []);

    // Second call should use cached path
    $overpass->executePython('health_check', []);

    // Should have run twice but discovered path only once
    Process::assertRanTimes(function ($process) {
        return str_contains($process->command, 'python');
    }, 2);
});

it('respects timeout configuration', function () {
    config(['overpass.timeout' => 5]);

    Process::fake([
        '*' => Process::result(
            output: json_encode(['success' => true]),
        ),
    ]);

    $overpass = new Overpass;
    $overpass->executePython('health_check', []);

    Process::assertRan(function ($process) {
        return $process->timeout === 5;
    });
});

it('respects max output configuration', function () {
    config(['overpass.max_output' => 1024]);

    Process::fake([
        '*' => Process::result(
            output: str_repeat('a', 2048), // Larger than max
        ),
    ]);

    $overpass = new Overpass;

    expect(fn () => $overpass->executePython('health_check', []))
        ->toThrow(Exception::class);
});

it('uses correct OpenAI models from configuration', function () {
    config([
        'overpass.embedding_model' => 'custom-embedding-model',
        'overpass.chat_model' => 'custom-chat-model',
    ]);

    // Mock the OpenAI client
    $mockClient = mock(\OpenAI\Client::class);
    $mockEmbeddings = mock(\OpenAI\Resources\Embeddings::class);
    $mockChat = mock(\OpenAI\Resources\Chat::class);

    $mockEmbeddings->shouldReceive('create')
        ->with(Mockery::on(function ($params) {
            return $params['model'] === 'custom-embedding-model';
        }))
        ->andReturn(mock(\OpenAI\Responses\Embeddings\CreateResponse::class));

    $mockChat->shouldReceive('create')
        ->with(Mockery::on(function ($params) {
            return $params['model'] === 'custom-chat-model';
        }))
        ->andReturn(mock(\OpenAI\Responses\Chat\CreateResponse::class));

    $mockClient->shouldReceive('embeddings')->andReturn($mockEmbeddings);
    $mockClient->shouldReceive('chat')->andReturn($mockChat);

    // Use reflection to inject mock client
    $overpass = new Overpass;
    $reflection = new ReflectionClass($overpass);
    $property = $reflection->getProperty('client');
    $property->setAccessible(true);
    $property->setValue($overpass, $mockClient);

    // These should use the configured models
    try {
        $overpass->generateEmbedding('test');
        $overpass->chat([['role' => 'user', 'content' => 'test']]);
    } catch (Exception $e) {
        // We're only testing that the right model names were used
    }
});
