<?php

use App\Livewire\IngestDocument;
use App\Models\Chunk;
use App\Models\Document;
use App\Services\Overpass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;

use function Pest\Laravel\mock;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Database is refreshed automatically
});

it('can render the ingest document component', function () {
    Livewire::test(IngestDocument::class)
        ->assertSet('title', '')
        ->assertSet('content', '')
        ->assertSet('chunkSize', 1000)
        ->assertSet('overlapSize', 200)
        ->assertSee('Ingest Document')
        ->assertSee('Document Title')
        ->assertSee('Document Content')
        ->assertStatus(200);
});

it('can ingest a document successfully', function () {
    // Mock Overpass service
    $mockOverpass = mock(Overpass::class);
    $mockOverpass->shouldReceive('generateEmbedding')
        ->andReturn(array_fill(0, 1536, 0.1));

    $this->app->instance(Overpass::class, $mockOverpass);

    Livewire::test(IngestDocument::class)
        ->set('title', 'Test Document')
        ->set('content', str_repeat('Test content for ingestion. ', 50))
        ->call('process')
        ->assertHasNoErrors()
        ->assertDispatched('document-ingested')
        ->assertSet('title', '')
        ->assertSet('content', '');

    // Verify document was created
    $document = Document::where('name', 'Test Document')->first();
    expect($document)->not->toBeNull();
    expect($document->chunks()->count())->toBeGreaterThan(0);
});

it('validates required fields', function () {
    Livewire::test(IngestDocument::class)
        ->call('process')
        ->assertHasErrors(['title' => 'required', 'content' => 'required']);

    Livewire::test(IngestDocument::class)
        ->set('title', 'Test')
        ->call('process')
        ->assertHasErrors(['content' => 'required'])
        ->assertHasNoErrors('title');

    Livewire::test(IngestDocument::class)
        ->set('content', 'Test content')
        ->call('process')
        ->assertHasErrors(['title' => 'required'])
        ->assertHasNoErrors('content');
});

it('validates document name length', function () {
    Livewire::test(IngestDocument::class)
        ->set('title', str_repeat('a', 256))
        ->set('content', 'Test content')
        ->call('process')
        ->assertHasErrors(['title' => 'max']);
});

it('can upload a file', function () {
    Storage::fake('local');

    $file = UploadedFile::fake()->create('document.txt', 'Test file content for upload');

    // Mock Overpass service
    $mockOverpass = mock(Overpass::class);
    $mockOverpass->shouldReceive('generateEmbedding')
        ->andReturn(array_fill(0, 1536, 0.1));

    $this->app->instance(Overpass::class, $mockOverpass);

    Livewire::test(IngestDocument::class)
        ->set('title', 'Uploaded Document')
        ->set('file', $file)
        ->call('process')
        ->assertHasNoErrors()
        ->assertDispatched('document-ingested');

    // Verify document was created
    expect(Document::where('name', 'Uploaded Document')->exists())->toBeTrue();
});

it('validates uploaded file type', function () {
    Storage::fake('local');

    $invalidFile = UploadedFile::fake()->create('document.exe', 100);

    Livewire::test(IngestDocument::class)
        ->set('title', 'Test')
        ->set('file', $invalidFile)
        ->call('process')
        ->assertHasErrors(['file']);
});

it('validates uploaded file size', function () {
    Storage::fake('local');

    // Create a file larger than 10MB
    $largeFile = UploadedFile::fake()->create('document.txt', 11000); // 11MB

    Livewire::test(IngestDocument::class)
        ->set('title', 'Test')
        ->set('file', $largeFile)
        ->call('process')
        ->assertHasErrors(['file' => 'max']);
});

it('respects chunk size settings', function () {
    // Mock Overpass service
    $mockOverpass = mock(Overpass::class);
    $mockOverpass->shouldReceive('generateEmbedding')
        ->andReturn(array_fill(0, 1536, 0.1));

    $this->app->instance(Overpass::class, $mockOverpass);

    $longText = str_repeat('Test content. ', 200); // About 2800 characters

    Livewire::test(IngestDocument::class)
        ->set('title', 'Chunk Test')
        ->set('content', $longText)
        ->set('chunkSize', 500)
        ->set('overlapSize', 100)
        ->call('process')
        ->assertHasNoErrors();

    $document = Document::where('name', 'Chunk Test')->first();
    $chunks = $document->chunks;

    // Should have multiple chunks
    expect($chunks->count())->toBeGreaterThan(1);

    // Each chunk should be around 500 characters
    foreach ($chunks as $chunk) {
        expect(strlen($chunk->content))->toBeLessThanOrEqual(500);
    }
});

it('handles chunking errors gracefully', function () {
    // Mock Overpass to throw an error
    $mockOverpass = mock(Overpass::class);
    $mockOverpass->shouldReceive('generateEmbedding')
        ->andThrow(new Exception('Embedding generation failed'));

    $this->app->instance(Overpass::class, $mockOverpass);

    Livewire::test(IngestDocument::class)
        ->set('title', 'Error Test')
        ->set('content', 'Test content')
        ->call('process')
        ->assertNotDispatched('document-ingested')
        ->assertSee('Failed to');
});

it('shows and hides advanced settings', function () {
    Livewire::test(IngestDocument::class)
        ->assertSet('showAdvanced', false)
        ->call('toggleAdvanced')
        ->assertSet('showAdvanced', true)
        ->call('toggleAdvanced')
        ->assertSet('showAdvanced', false);
});

it('updates chunk size from input', function () {
    Livewire::test(IngestDocument::class)
        ->set('chunkSize', 1500)
        ->assertSet('chunkSize', 1500);
});

it('updates overlap size from input', function () {
    Livewire::test(IngestDocument::class)
        ->set('overlapSize', 300)
        ->assertSet('overlapSize', 300);
});

it('clears form after successful ingestion', function () {
    // Mock Overpass service
    $mockOverpass = mock(Overpass::class);
    $mockOverpass->shouldReceive('generateEmbedding')
        ->andReturn(array_fill(0, 1536, 0.1));

    $this->app->instance(Overpass::class, $mockOverpass);

    Livewire::test(IngestDocument::class)
        ->set('title', 'Test Document')
        ->set('content', 'Test content')
        ->set('chunkSize', 1500)
        ->set('overlapSize', 300)
        ->call('process')
        ->assertSet('title', '')
        ->assertSet('content', '')
        ->assertSet('file', null);
});

it('stores metadata correctly', function () {
    // Mock Overpass service
    $mockOverpass = mock(Overpass::class);
    $mockOverpass->shouldReceive('generateEmbedding')
        ->andReturn(array_fill(0, 1536, 0.1));

    $this->app->instance(Overpass::class, $mockOverpass);

    Livewire::test(IngestDocument::class)
        ->set('title', 'Metadata Test')
        ->set('content', str_repeat('Test content. ', 100))
        ->set('chunkSize', 800)
        ->set('overlapSize', 150)
        ->call('process');

    $document = Document::where('name', 'Metadata Test')->first();

    expect($document->metadata)->toHaveKey('chunk_size', 800);
    expect($document->metadata)->toHaveKey('overlap_size', 150);
    expect($document->metadata)->toHaveKey('total_chunks');
});

it('prevents duplicate document names', function () {
    // Create existing document
    Document::create([
        'name' => 'Existing Document',
        'content' => 'Original content',
        'metadata' => ['chunk_size' => 1000],
    ]);

    Livewire::test(IngestDocument::class)
        ->set('title', 'Existing Document')
        ->set('content', 'New content')
        ->call('process')
        ->assertHasErrors(['title' => 'unique']);
});

it('handles very large documents', function () {
    // Mock Overpass service
    $mockOverpass = mock(Overpass::class);
    $mockOverpass->shouldReceive('generateEmbedding')
        ->andReturn(array_fill(0, 1536, 0.1));

    $this->app->instance(Overpass::class, $mockOverpass);

    // Create a 50,000 character document
    $largeText = str_repeat('Lorem ipsum dolor sit amet. ', 1700);

    Livewire::test(IngestDocument::class)
        ->set('title', 'Large Document')
        ->set('content', $largeText)
        ->set('chunkSize', 2000)
        ->set('overlapSize', 400)
        ->call('process')
        ->assertHasNoErrors()
        ->assertDispatched('document-ingested');

    $document = Document::where('name', 'Large Document')->first();
    expect($document->chunks()->count())->toBeGreaterThan(20);
});
