<?php

use App\Actions\Overpass\CheckHealthAction;
use App\Services\Overpass;

use function Pest\Laravel\mock;

it('returns success status when healthy', function () {
    $mockOverpass = mock(Overpass::class);
    $mockOverpass->shouldReceive('checkHealth')
        ->andReturn([
            'success' => true,
            'message' => 'All systems operational',
            'openai' => 'connected',
            'python_bridge' => 'connected',
        ]);

    $action = new CheckHealthAction($mockOverpass);
    $result = $action->execute();

    expect($result)->toBeArray();
    expect($result['success'])->toBeTrue();
    expect($result['message'])->toBe('All systems operational');
    expect($result['openai'])->toBe('connected');
    expect($result['python_bridge'])->toBe('connected');
});

it('returns error status when unhealthy', function () {
    $mockOverpass = mock(Overpass::class);
    $mockOverpass->shouldReceive('checkHealth')
        ->andReturn([
            'success' => false,
            'message' => 'Connection failed',
            'openai' => 'error',
            'python_bridge' => 'error',
        ]);

    $action = new CheckHealthAction($mockOverpass);
    $result = $action->execute();

    expect($result)->toBeArray();
    expect($result['success'])->toBeFalse();
    expect($result['message'])->toBe('Connection failed');
    expect($result['openai'])->toBe('error');
    expect($result['python_bridge'])->toBe('error');
});

it('handles partial failures', function () {
    $mockOverpass = mock(Overpass::class);
    $mockOverpass->shouldReceive('checkHealth')
        ->andReturn([
            'success' => true,
            'message' => 'Partial connectivity',
            'openai' => 'connected',
            'python_bridge' => 'error',
        ]);

    $action = new CheckHealthAction($mockOverpass);
    $result = $action->execute();

    expect($result['success'])->toBeTrue();
    expect($result['openai'])->toBe('connected');
    expect($result['python_bridge'])->toBe('error');
});

it('handles exceptions gracefully', function () {
    $mockOverpass = mock(Overpass::class);
    $mockOverpass->shouldReceive('checkHealth')
        ->andThrow(new Exception('Service unavailable'));

    $action = new CheckHealthAction($mockOverpass);
    $result = $action->execute();

    expect($result)->toBeArray();
    expect($result['success'])->toBeFalse();
    expect($result['message'])->toContain('Failed to check status');
    expect($result['message'])->toContain('Service unavailable');
    expect($result['openai'])->toBe('error');
    expect($result['python_bridge'])->toBe('error');
});

it('includes all required keys in response', function () {
    $mockOverpass = mock(Overpass::class);
    $mockOverpass->shouldReceive('checkHealth')
        ->andReturn([
            'success' => true,
            'message' => 'Test',
            'openai' => 'connected',
            'python_bridge' => 'connected',
        ]);

    $action = new CheckHealthAction($mockOverpass);
    $result = $action->execute();

    expect($result)->toHaveKeys(['success', 'message', 'openai', 'python_bridge']);
});

it('preserves additional status information', function () {
    $mockOverpass = mock(Overpass::class);
    $mockOverpass->shouldReceive('checkHealth')
        ->andReturn([
            'success' => true,
            'message' => 'All systems operational',
            'openai' => 'connected',
            'python_bridge' => 'connected',
            'additional_info' => 'Extra data',
            'version' => '1.0.0',
        ]);

    $action = new CheckHealthAction($mockOverpass);
    $result = $action->execute();

    expect($result)->toHaveKey('additional_info', 'Extra data');
    expect($result)->toHaveKey('version', '1.0.0');
});
