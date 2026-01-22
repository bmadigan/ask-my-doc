<?php

use App\Livewire\OverpassStatusCard;
use Bmadigan\Overpass\Services\PythonAiBridge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\mock;

uses(RefreshDatabase::class);

it('renders the overpass status card component', function () {
    $mockOverpass = mock(PythonAiBridge::class);
    $mockOverpass->shouldReceive('testConnection')
        ->andReturn([
            'status' => 'ok',
            'message' => 'All systems operational',
            'data' => ['openai_available' => true],
        ]);

    app()->instance(PythonAiBridge::class, $mockOverpass);

    Livewire::test(OverpassStatusCard::class)
        ->assertStatus(200);
});

it('shows healthy status when connection succeeds', function () {
    $mockOverpass = mock(PythonAiBridge::class);
    $mockOverpass->shouldReceive('testConnection')
        ->andReturn([
            'status' => 'ok',
            'message' => 'All systems operational',
            'data' => ['openai_available' => true],
        ]);

    app()->instance(PythonAiBridge::class, $mockOverpass);

    Livewire::test(OverpassStatusCard::class)
        ->assertSet('status.success', true)
        ->assertDispatched('overpass-status-checked');
});

it('shows error status when connection fails', function () {
    $mockOverpass = mock(PythonAiBridge::class);
    $mockOverpass->shouldReceive('testConnection')
        ->andReturn([
            'status' => 'error',
            'message' => 'Connection failed',
            'data' => ['openai_available' => false],
        ]);

    app()->instance(PythonAiBridge::class, $mockOverpass);

    Livewire::test(OverpassStatusCard::class)
        ->assertSet('status.success', false);
});

it('handles exceptions gracefully', function () {
    $mockOverpass = mock(PythonAiBridge::class);
    $mockOverpass->shouldReceive('testConnection')
        ->andThrow(new Exception('Network error'));

    app()->instance(PythonAiBridge::class, $mockOverpass);

    Livewire::test(OverpassStatusCard::class)
        ->assertSet('status.success', false)
        ->assertSet('testing', false);
});

it('can refresh status check', function () {
    $mockOverpass = mock(PythonAiBridge::class);
    $mockOverpass->shouldReceive('testConnection')
        ->twice()
        ->andReturn([
            'status' => 'ok',
            'message' => 'All systems operational',
            'data' => ['openai_available' => true],
        ]);

    app()->instance(PythonAiBridge::class, $mockOverpass);

    Livewire::test(OverpassStatusCard::class)
        ->call('checkStatus')
        ->assertSet('status.success', true)
        ->assertNotSet('lastChecked', null);
});
