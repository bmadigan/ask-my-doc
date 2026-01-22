<?php

use App\Livewire\IngestDocument;
use Bmadigan\Overpass\Services\PythonAiBridge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\mock;

uses(RefreshDatabase::class);

it('renders the ingest document component', function () {
    Livewire::test(IngestDocument::class)
        ->assertStatus(200)
        ->assertSee('Title')
        ->assertSee('Content');
});

it('validates required title field', function () {
    Livewire::test(IngestDocument::class)
        ->set('title', '')
        ->set('content', 'Some content that is long enough')
        ->call('process')
        ->assertHasErrors(['title' => 'required']);
});

it('validates title minimum length', function () {
    Livewire::test(IngestDocument::class)
        ->set('title', 'ab')
        ->set('content', 'Some content that is long enough')
        ->call('process')
        ->assertHasErrors(['title' => 'min']);
});

it('validates content is required without file', function () {
    Livewire::test(IngestDocument::class)
        ->set('title', 'Test Title')
        ->set('content', '')
        ->call('process')
        ->assertHasErrors(['content']);
});

it('successfully processes document and dispatches event', function () {
    $mockOverpass = mock(PythonAiBridge::class);
    $mockOverpass->shouldReceive('generateEmbedding')
        ->andReturn([
            'embedding' => array_fill(0, 1536, 0.1),
            'model' => 'text-embedding-3-small',
            'dimension' => 1536,
        ]);

    app()->instance(PythonAiBridge::class, $mockOverpass);

    Livewire::test(IngestDocument::class)
        ->set('title', 'Test Document')
        ->set('content', str_repeat('This is test content for the document. ', 10))
        ->call('process')
        ->assertHasNoErrors()
        ->assertSet('success', true)
        ->assertSet('processing', false)
        ->assertDispatched('document-ingested');
});

it('shows error message when processing fails', function () {
    $mockOverpass = mock(PythonAiBridge::class);
    $mockOverpass->shouldReceive('generateEmbedding')
        ->andThrow(new Exception('API connection failed'));

    app()->instance(PythonAiBridge::class, $mockOverpass);

    Livewire::test(IngestDocument::class)
        ->set('title', 'Test Document')
        ->set('content', str_repeat('This is test content. ', 10))
        ->call('process')
        ->assertSet('success', false)
        ->assertSet('processing', false)
        ->assertNotSet('error', null);
});

it('resets form after successful processing', function () {
    $mockOverpass = mock(PythonAiBridge::class);
    $mockOverpass->shouldReceive('generateEmbedding')
        ->andReturn([
            'embedding' => array_fill(0, 1536, 0.1),
            'model' => 'text-embedding-3-small',
            'dimension' => 1536,
        ]);

    app()->instance(PythonAiBridge::class, $mockOverpass);

    Livewire::test(IngestDocument::class)
        ->set('title', 'Test Document')
        ->set('content', str_repeat('This is test content for the document. ', 10))
        ->call('process')
        ->assertSet('title', '')
        ->assertSet('content', '');
});
