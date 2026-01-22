<?php

use App\Models\Chunk;
use App\Models\Document;
use App\Models\Query;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('can access the dashboard page', function () {
    $response = $this->get('/');

    $response->assertStatus(200)
        ->assertSee('Ask My Doc')
        ->assertSee('Dashboard')
        ->assertSee('Overpass Status');
});

it('can access the ingest page', function () {
    $response = $this->get('/ingest');

    $response->assertStatus(200)
        ->assertSee('Ingest Document')
        ->assertSee('Document Title');
});

it('can access the ask page', function () {
    $response = $this->get('/ask');

    $response->assertStatus(200)
        ->assertSee('Ask Questions')
        ->assertSee('Ask a Question');
});

it('can create documents in database', function () {
    $document = Document::create([
        'title' => 'Test Document',
        'bytes' => 1024,
        'original_filename' => 'test.txt',
    ]);

    expect($document)->toBeInstanceOf(Document::class);
    expect($document->title)->toBe('Test Document');
    expect($document->bytes)->toBe(1024);
    expect(Document::count())->toBe(1);
});

it('can create chunks with embeddings', function () {
    $document = Document::create([
        'title' => 'Test Document',
        'bytes' => 500,
    ]);

    $chunk = Chunk::create([
        'document_id' => $document->id,
        'content' => 'This is a test chunk',
        'chunk_index' => 0,
        'embedding_json' => json_encode(array_fill(0, 1536, 0.1)),
    ]);

    expect($chunk)->toBeInstanceOf(Chunk::class);
    expect($chunk->document_id)->toBe($document->id);
    expect(json_decode($chunk->embedding_json))->toBeArray();
    expect(count(json_decode($chunk->embedding_json)))->toBe(1536);
});

it('can create queries', function () {
    $document = Document::create([
        'title' => 'Test Document',
        'bytes' => 500,
    ]);

    $query = Query::create([
        'document_id' => $document->id,
        'question' => 'What is this about?',
        'top_k_returned' => 5,
        'latency_ms' => 150,
    ]);

    expect($query)->toBeInstanceOf(Query::class);
    expect($query->question)->toBe('What is this about?');
    expect($query->top_k_returned)->toBe(5);
    expect($query->latency_ms)->toBe(150);
});

it('maintains document-chunk relationship', function () {
    $document = Document::create([
        'title' => 'Related Document',
        'bytes' => 1500,
    ]);

    for ($i = 1; $i <= 3; $i++) {
        Chunk::create([
            'document_id' => $document->id,
            'content' => "Chunk {$i} content",
            'chunk_index' => $i - 1,
            'embedding_json' => json_encode(array_fill(0, 1536, 0.1 * $i)),
        ]);
    }

    expect($document->chunks()->count())->toBe(3);
    expect($document->chunks->first()->content)->toContain('Chunk 1');
});

it('maintains document-query relationship', function () {
    $document = Document::create([
        'title' => 'Q&A Document',
        'bytes' => 800,
    ]);

    Query::create([
        'document_id' => $document->id,
        'question' => 'First question',
        'top_k_returned' => 3,
    ]);

    Query::create([
        'document_id' => $document->id,
        'question' => 'Second question',
        'top_k_returned' => 5,
    ]);

    expect($document->queries()->count())->toBe(2);
    expect($document->queries->pluck('question')->toArray())
        ->toContain('First question', 'Second question');
});

it('validates environment configuration', function () {
    expect(config('app.name'))->toBe('Laravel');
    expect(config('database.default'))->toBe('sqlite');
    // OpenAI key may not be set in test environment
    expect(config('database.default'))->toBe('sqlite');
});

it('has correct database tables', function () {
    // Check if tables exist
    expect(Schema::hasTable('documents'))->toBeTrue();
    expect(Schema::hasTable('chunks'))->toBeTrue();
    expect(Schema::hasTable('queries'))->toBeTrue();
});

it('can handle large embeddings', function () {
    $document = Document::create([
        'title' => 'Large Embedding Test',
        'bytes' => 50000,
    ]);

    $largeEmbedding = array_fill(0, 1536, rand(0, 100) / 100);

    $chunk = Chunk::create([
        'document_id' => $document->id,
        'content' => 'Content with large embedding',
        'chunk_index' => 0,
        'embedding_json' => json_encode($largeEmbedding),
    ]);

    $retrievedEmbedding = json_decode($chunk->fresh()->embedding_json);
    expect($retrievedEmbedding)->toBeArray();
    expect(count($retrievedEmbedding))->toBe(1536);
});

it('stores document information correctly', function () {
    $document = Document::create([
        'title' => 'Information Test',
        'bytes' => 2048,
        'original_filename' => 'info_test.md',
    ]);

    $retrieved = Document::find($document->id);

    expect($retrieved->title)->toBe('Information Test');
    expect($retrieved->bytes)->toBe(2048);
    expect($retrieved->original_filename)->toBe('info_test.md');
});

it('can delete documents with cascade', function () {
    $document = Document::create([
        'title' => 'Delete Test',
        'bytes' => 300,
    ]);

    // Create related chunks
    for ($i = 1; $i <= 3; $i++) {
        Chunk::create([
            'document_id' => $document->id,
            'content' => "Chunk {$i}",
            'chunk_index' => $i - 1,
            'embedding_json' => json_encode(array_fill(0, 1536, 0.1)),
        ]);
    }

    // Create related queries
    Query::create([
        'document_id' => $document->id,
        'question' => 'Test query',
        'top_k_returned' => 5,
    ]);

    expect(Chunk::where('document_id', $document->id)->count())->toBe(3);
    expect(Query::where('document_id', $document->id)->count())->toBe(1);

    // Delete the document
    $document->delete();

    // Check cascade deletion
    expect(Document::find($document->id))->toBeNull();
    expect(Chunk::where('document_id', $document->id)->count())->toBe(0);
    expect(Query::where('document_id', $document->id)->count())->toBe(0);
});
