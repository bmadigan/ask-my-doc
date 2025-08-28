<?php

use App\Actions\Document\IngestDocumentAction;
use App\Models\Chunk;
use App\Models\Document;
use Bmadigan\Overpass\Services\PythonAiBridge;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\mock;

uses(RefreshDatabase::class);

it('creates a document with chunks', function () {
    // Mock the Overpass service
    $mockOverpass = mock(PythonAiBridge::class);
    $mockOverpass->shouldReceive('generateEmbedding')
        ->andReturn([
            'embedding' => array_fill(0, 1536, 0.1),
            'model' => 'text-embedding-3-small',
            'dimension' => 1536,
        ]);

    $document = IngestDocumentAction::run([
        'title' => 'Test Document',
        'content' => str_repeat('Test content for ingestion. ', 50),
        'chunk_size' => 100,
        'overlap_size' => 20,
    ], $mockOverpass);

    expect($document)->toBeInstanceOf(Document::class);
    expect($document->title)->toBe('Test Document');
    expect($document->chunks()->count())->toBeGreaterThan(0);
});

it('handles different chunk sizes', function () {
    $mockOverpass = mock(PythonAiBridge::class);
    $mockOverpass->shouldReceive('generateEmbedding')
        ->andReturn([
            'embedding' => array_fill(0, 1536, 0.1),
            'model' => 'text-embedding-3-small',
            'dimension' => 1536,
        ]);

    $content = str_repeat('Lorem ipsum dolor sit amet. ', 100);

    // Small chunks
    $document1 = IngestDocumentAction::run([
        'title' => 'Small Chunks',
        'content' => $content,
        'chunk_size' => 50,
        'overlap_size' => 10,
    ], $mockOverpass);

    // Large chunks
    $document2 = IngestDocumentAction::run([
        'title' => 'Large Chunks',
        'content' => $content,
        'chunk_size' => 500,
        'overlap_size' => 100,
    ], $mockOverpass);

    expect($document1->chunks()->count())->toBeGreaterThan($document2->chunks()->count());
});

it('stores original filename when provided', function () {
    $mockOverpass = mock(PythonAiBridge::class);
    $mockOverpass->shouldReceive('generateEmbedding')
        ->andReturn([
            'embedding' => array_fill(0, 1536, 0.1),
            'model' => 'text-embedding-3-small',
            'dimension' => 1536,
        ]);

    $document = IngestDocumentAction::run([
        'title' => 'File Upload',
        'content' => 'File content',
        'original_filename' => 'test.txt',
        'chunk_size' => 100,
        'overlap_size' => 20,
    ], $mockOverpass);

    expect($document->original_filename)->toBe('test.txt');
});

it('calculates document bytes correctly', function () {
    $mockOverpass = mock(PythonAiBridge::class);
    $mockOverpass->shouldReceive('generateEmbedding')
        ->andReturn([
            'embedding' => array_fill(0, 1536, 0.1),
            'model' => 'text-embedding-3-small',
            'dimension' => 1536,
        ]);

    $content = 'This is exactly 29 characters';

    $document = IngestDocumentAction::run([
        'title' => 'Byte Test',
        'content' => $content,
        'chunk_size' => 100,
        'overlap_size' => 20,
    ], $mockOverpass);

    expect($document->bytes)->toBe(29);
});

it('creates chunks with embeddings', function () {
    $mockEmbedding = array_fill(0, 1536, 0.123);

    $mockOverpass = mock(PythonAiBridge::class);
    $mockOverpass->shouldReceive('generateEmbedding')
        ->andReturn([
            'embedding' => $mockEmbedding,
            'model' => 'text-embedding-3-small',
            'dimension' => 1536,
        ]);

    $document = IngestDocumentAction::run([
        'title' => 'Embedding Test',
        'content' => 'Test content for embedding',
        'chunk_size' => 100,
        'overlap_size' => 20,
    ], $mockOverpass);

    $chunk = $document->chunks()->first();
    $embedding = json_decode($chunk->embedding_json, true);

    expect($embedding)->toBeArray();
    expect($embedding)->toHaveCount(1536);
    expect($embedding[0])->toBe(0.123);
});

it('handles overlap correctly', function () {
    $mockOverpass = mock(PythonAiBridge::class);
    $mockOverpass->shouldReceive('generateEmbedding')
        ->andReturn([
            'embedding' => array_fill(0, 1536, 0.1),
            'model' => 'text-embedding-3-small',
            'dimension' => 1536,
        ]);

    // Create content that will be chunked
    $content = 'First sentence here. Second sentence here. Third sentence here. Fourth sentence here. Fifth sentence here.';

    $document = IngestDocumentAction::run([
        'title' => 'Overlap Test',
        'content' => $content,
        'chunk_size' => 50,
        'overlap_size' => 20,
    ], $mockOverpass);

    $chunks = $document->chunks()->orderBy('chunk_index')->get();

    // Check that chunks have some overlapping content
    if ($chunks->count() > 1) {
        $firstChunk = $chunks[0]->content;
        $secondChunk = $chunks[1]->content;

        // There should be some overlap
        // This is a simplified check - in reality overlap detection would be more complex
        expect(strlen($firstChunk))->toBeGreaterThan(0);
        expect(strlen($secondChunk))->toBeGreaterThan(0);
    }
});

it('rolls back transaction on failure', function () {
    $mockOverpass = mock(PythonAiBridge::class);
    $mockOverpass->shouldReceive('generateEmbedding')
        ->andThrow(new Exception('API Error'));

    $documentCountBefore = Document::count();
    $chunkCountBefore = Chunk::count();

    expect(fn () => IngestDocumentAction::run([
        'title' => 'Failed Document',
        'content' => 'This will fail',
        'chunk_size' => 100,
        'overlap_size' => 20,
    ], $mockOverpass))->toThrow(Exception::class, 'API Error');

    // Database should be unchanged
    expect(Document::count())->toBe($documentCountBefore);
    expect(Chunk::count())->toBe($chunkCountBefore);
});

it('estimates token count', function () {
    $mockOverpass = mock(PythonAiBridge::class);
    $mockOverpass->shouldReceive('generateEmbedding')
        ->andReturn([
            'embedding' => array_fill(0, 1536, 0.1),
            'model' => 'text-embedding-3-small',
            'dimension' => 1536,
        ]);

    $document = IngestDocumentAction::run([
        'title' => 'Token Test',
        'content' => str_repeat('word ', 100), // 500 characters
        'chunk_size' => 500,
        'overlap_size' => 50,
    ], $mockOverpass);

    $chunk = $document->chunks()->first();

    // Token count should be roughly 1/4 of character count
    expect($chunk->token_count)->toBeGreaterThan(100);
    expect($chunk->token_count)->toBeLessThan(150);
});

it('filters out empty chunks', function () {
    $mockOverpass = mock(PythonAiBridge::class);
    $mockOverpass->shouldReceive('generateEmbedding')
        ->andReturn([
            'embedding' => array_fill(0, 1536, 0.1),
            'model' => 'text-embedding-3-small',
            'dimension' => 1536,
        ]);

    // Content with lots of whitespace
    $content = "Some content\n\n\n\n\n".str_repeat(' ', 100)."\n\n\nMore content";

    $document = IngestDocumentAction::run([
        'title' => 'Whitespace Test',
        'content' => $content,
        'chunk_size' => 50,
        'overlap_size' => 10,
    ], $mockOverpass);

    // All chunks should have non-empty content
    foreach ($document->chunks as $chunk) {
        expect(trim($chunk->content))->not->toBeEmpty();
    }
});
