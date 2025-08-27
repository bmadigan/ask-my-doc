<?php

use App\Livewire\OverpassStatusCard;
use App\Services\Overpass;
use Livewire\Livewire;

use function Pest\Laravel\mock;

it('can render the status card component', function () {
    Livewire::test(OverpassStatusCard::class)
        ->assertSee('Overpass Status')
        ->assertSee('Test Connection')
        ->assertStatus(200);
});

it('shows initial state without status', function () {
    Livewire::test(OverpassStatusCard::class)
        ->assertSet('status', null)
        ->assertSet('lastChecked', null)
        ->assertSet('testing', false)
        ->assertSee('Click "Test Connection" to check status');
});

it('can check status successfully', function () {
    // Mock Overpass service
    $mockOverpass = mock(Overpass::class);
    $mockOverpass->shouldReceive('checkHealth')
        ->andReturn([
            'success' => true,
            'message' => 'All systems operational',
            'openai' => 'connected',
            'python_bridge' => 'connected',
        ]);

    $this->app->instance(Overpass::class, $mockOverpass);

    Livewire::test(OverpassStatusCard::class)
        ->call('checkStatus')
        ->assertSet('status', [
            'success' => true,
            'message' => 'All systems operational',
            'openai' => 'connected',
            'python_bridge' => 'connected',
        ])
        ->assertSee('All systems operational')
        ->assertSee('OpenAI')
        ->assertSee('Python')
        ->assertSee('Connected');
});

it('handles connection failure', function () {
    // Mock Overpass service with failure
    $mockOverpass = mock(Overpass::class);
    $mockOverpass->shouldReceive('checkHealth')
        ->andReturn([
            'success' => false,
            'message' => 'Connection failed',
            'openai' => 'error',
            'python_bridge' => 'error',
        ]);

    $this->app->instance(Overpass::class, $mockOverpass);

    Livewire::test(OverpassStatusCard::class)
        ->call('checkStatus')
        ->assertSet('status.success', false)
        ->assertSet('status.message', 'Connection failed')
        ->assertSee('Connection failed');
});

it('shows partial failure correctly', function () {
    // Mock Overpass with partial failure
    $mockOverpass = mock(Overpass::class);
    $mockOverpass->shouldReceive('checkHealth')
        ->andReturn([
            'success' => true,
            'message' => 'Partial connectivity',
            'openai' => 'connected',
            'python_bridge' => 'error',
        ]);

    $this->app->instance(Overpass::class, $mockOverpass);

    Livewire::test(OverpassStatusCard::class)
        ->call('checkStatus')
        ->assertSee('OpenAI')
        ->assertSee('Python')
        ->assertSee('Error');
});

it('handles check status errors', function () {
    // Mock Overpass to throw exception
    $mockOverpass = mock(Overpass::class);
    $mockOverpass->shouldReceive('checkHealth')
        ->andThrow(new Exception('Service unavailable'));

    $this->app->instance(Overpass::class, $mockOverpass);

    Livewire::test(OverpassStatusCard::class)
        ->call('checkStatus')
        ->assertSet('status', [
            'success' => false,
            'message' => 'Failed to check status: Service unavailable',
        ])
        ->assertSee('Failed to check status');
});

it('shows loading state during check', function () {
    Livewire::test(OverpassStatusCard::class)
        ->assertSet('testing', false)
        ->call('checkStatus')
        ->assertSet('testing', true);
});

it('updates last checked timestamp', function () {
    // Mock Overpass service
    $mockOverpass = mock(Overpass::class);
    $mockOverpass->shouldReceive('checkHealth')
        ->andReturn([
            'success' => true,
            'message' => 'All systems operational',
            'openai' => 'connected',
            'python_bridge' => 'connected',
        ]);

    $this->app->instance(Overpass::class, $mockOverpass);

    $component = Livewire::test(OverpassStatusCard::class)
        ->assertSet('lastChecked', null)
        ->call('checkStatus');

    // Last checked should be set
    expect($component->get('lastChecked'))->not->toBeNull();

    // Should show last checked time
    $component->assertSee('Last checked');
});

it('formats last checked time correctly', function () {
    // Mock Overpass service
    $mockOverpass = mock(Overpass::class);
    $mockOverpass->shouldReceive('checkHealth')
        ->andReturn([
            'success' => true,
            'message' => 'All systems operational',
            'openai' => 'connected',
            'python_bridge' => 'connected',
        ]);

    $this->app->instance(Overpass::class, $mockOverpass);

    // Travel to a specific time
    $this->travelTo('2024-01-15 14:30:00');

    Livewire::test(OverpassStatusCard::class)
        ->call('checkStatus')
        ->assertSee('14:30:00');

    // Travel back
    $this->travelBack();
});

it('shows animated pulse for successful connection', function () {
    // Mock Overpass service
    $mockOverpass = mock(Overpass::class);
    $mockOverpass->shouldReceive('checkHealth')
        ->andReturn([
            'success' => true,
            'message' => 'All systems operational',
            'openai' => 'connected',
            'python_bridge' => 'connected',
        ]);

    $this->app->instance(Overpass::class, $mockOverpass);

    Livewire::test(OverpassStatusCard::class)
        ->call('checkStatus')
        ->assertSeeHtml('animate-pulse');
});

it('shows red indicator for failed connection', function () {
    // Mock Overpass service with failure
    $mockOverpass = mock(Overpass::class);
    $mockOverpass->shouldReceive('checkHealth')
        ->andReturn([
            'success' => false,
            'message' => 'Connection failed',
            'openai' => 'error',
            'python_bridge' => 'error',
        ]);

    $this->app->instance(Overpass::class, $mockOverpass);

    Livewire::test(OverpassStatusCard::class)
        ->call('checkStatus')
        ->assertSeeHtml('bg-red-500');
});

it('disables button during testing', function () {
    Livewire::test(OverpassStatusCard::class)
        ->assertSeeHtml(':disabled="$wire.testing"');
});

it('emits event on successful check', function () {
    // Mock Overpass service
    $mockOverpass = mock(Overpass::class);
    $mockOverpass->shouldReceive('checkHealth')
        ->andReturn([
            'success' => true,
            'message' => 'All systems operational',
            'openai' => 'connected',
            'python_bridge' => 'connected',
        ]);

    $this->app->instance(Overpass::class, $mockOverpass);

    Livewire::test(OverpassStatusCard::class)
        ->call('checkStatus')
        ->assertDispatched('overpass-status-checked');
});

it('shows appropriate colors for component status', function () {
    // Mock Overpass service
    $mockOverpass = mock(Overpass::class);
    $mockOverpass->shouldReceive('checkHealth')
        ->andReturn([
            'success' => true,
            'message' => 'All systems operational',
            'openai' => 'connected',
            'python_bridge' => 'error',
        ]);

    $this->app->instance(Overpass::class, $mockOverpass);

    Livewire::test(OverpassStatusCard::class)
        ->call('checkStatus')
        ->assertSeeHtml('var(--linear-accent-green)')  // For connected OpenAI
        ->assertSeeHtml('var(--linear-accent-red)');   // For error Python
});
