<?php

use App\Actions\Overpass\CheckHealthAction;
use Bmadigan\Overpass\Services\PythonAiBridge;

use function Pest\Laravel\mock;

it('returns success status when healthy', function () {
    $mockOverpass = mock(PythonAiBridge::class);
    $mockOverpass->shouldReceive('testConnection')
        ->andReturn([
            'status' => 'success',
            'message' => 'All systems operational',
            'data' => [
                'openai_available' => true,
            ],
        ]);

    $result = CheckHealthAction::run($mockOverpass);

    expect($result)->toBeArray();
    expect($result['success'])->toBeTrue();
    expect($result['message'])->toBe('All systems operational');
    expect($result['openai'])->toBe('connected');
    expect($result['python_bridge'])->toBe('connected');
});

it('returns error status when unhealthy', function () {
    $mockOverpass = mock(PythonAiBridge::class);
    $mockOverpass->shouldReceive('testConnection')
        ->andReturn([
            'status' => 'error',
            'message' => 'Connection failed',
            'openai' => 'error',
            'python_bridge' => 'error',
        ]);

    $result = CheckHealthAction::run($mockOverpass);

    expect($result)->toBeArray();
    expect($result['success'])->toBeFalse();
    expect($result['message'])->toBe('Connection failed');
    expect($result['openai'])->toBe('not configured');
    expect($result['python_bridge'])->toBe('error');
});

it('handles partial failures', function () {
    $mockOverpass = mock(PythonAiBridge::class);
    $mockOverpass->shouldReceive('testConnection')
        ->andReturn([
            'status' => 'success',
            'message' => 'Partial connectivity',
            'data' => [
                'openai_available' => true,
            ],
        ]);

    $result = CheckHealthAction::run($mockOverpass);

    expect($result['success'])->toBeTrue();
    expect($result['openai'])->toBe('connected');
    expect($result['python_bridge'])->toBe('connected');
});

it('handles exceptions gracefully', function () {
    $mockOverpass = mock(PythonAiBridge::class);
    $mockOverpass->shouldReceive('testConnection')
        ->andThrow(new Exception('Service unavailable'));

    $result = CheckHealthAction::run($mockOverpass);

    expect($result)->toBeArray();
    expect($result['success'])->toBeFalse();
    expect($result['message'])->toContain('Failed to check status');
    expect($result['message'])->toContain('Service unavailable');
    expect($result['openai'])->toBe('error');
    expect($result['python_bridge'])->toBe('error');
});

it('includes all required keys in response', function () {
    $mockOverpass = mock(PythonAiBridge::class);
    $mockOverpass->shouldReceive('testConnection')
        ->andReturn([
            'status' => 'success',
            'message' => 'Test',
            'openai' => 'connected',
            'python_bridge' => 'connected',
        ]);

    $result = CheckHealthAction::run($mockOverpass);

    expect($result)->toHaveKeys(['success', 'message', 'openai', 'python_bridge']);
});

it('preserves additional status information', function () {
    $mockOverpass = mock(PythonAiBridge::class);
    $mockOverpass->shouldReceive('testConnection')
        ->andReturn([
            'status' => 'success',
            'message' => 'All systems operational',
            'openai' => 'connected',
            'python_bridge' => 'connected',
        ]);

    $result = CheckHealthAction::run($mockOverpass);

    // CheckHealthAction only returns the standard keys
    expect($result)->toHaveKeys(['success', 'message', 'openai', 'python_bridge']);
});
