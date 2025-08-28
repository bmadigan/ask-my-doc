<?php

use App\Actions\Query\AskQuestionAction;
use App\Models\Chunk;
use App\Models\Document;
use App\Models\Query;
use Bmadigan\Overpass\Services\PythonAiBridge;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\mock;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create test document with chunks
    $this->document = Document::create([
        'title' => 'Test Document',
        'bytes' => 1000,
    ]);

    $this->chunk1 = Chunk::create([
        'document_id' => $this->document->id,
        'chunk_index' => 0,
        'content' => 'Laravel is a PHP framework for web artisans',
        'embedding_json' => json_encode(array_fill(0, 1536, 0.1)),
        'token_count' => 10,
    ]);

    $this->chunk2 = Chunk::create([
        'document_id' => $this->document->id,
        'chunk_index' => 1,
        'content' => 'It provides elegant syntax and powerful features',
        'embedding_json' => json_encode(array_fill(0, 1536, 0.2)),
        'token_count' => 10,
    ]);
});

it('asks a question and returns an answer', function () {
    $mockOverpass = mock(PythonAiBridge::class);

    // Mock embedding generation
    $mockOverpass->shouldReceive('generateEmbedding')
        ->with('What is Laravel?')
        ->andReturn([
            'embedding' => array_fill(0, 1536, 0.15),
            'model' => 'text-embedding-3-small',
            'dimension' => 1536,
        ]);

    // Mock chat response
    $mockOverpass->shouldReceive('chat')
        ->andReturn([
            'response' => 'Laravel is a PHP framework that provides elegant syntax for web artisans.',
            'fallback' => false,
        ]);

    $result = AskQuestionAction::run([
        'document_id' => $this->document->id,
        'question' => 'What is Laravel?',
        'top_k' => 5,
        'min_score' => 0.5,
    ], $mockOverpass);

    expect($result)->toHaveKeys(['answer', 'relevant_chunks', 'query']);
    expect($result['answer'])->toContain('Laravel');
    expect($result['relevant_chunks'])->toHaveCount(2);
    expect($result['query'])->toBeInstanceOf(Query::class);
});

it('handles documents with no chunks', function () {
    $emptyDoc = Document::create([
        'title' => 'Empty Document',
        'bytes' => 0,
    ]);

    $mockOverpass = mock(PythonAiBridge::class);

    expect(fn () => AskQuestionAction::run([
        'document_id' => $emptyDoc->id,
        'question' => 'Test question',
        'top_k' => 5,
        'min_score' => 0.5,
    ], $mockOverpass))->toThrow(Exception::class, 'Document has no chunks available');
});

it('returns error when no relevant chunks found', function () {
    $mockOverpass = mock(PythonAiBridge::class);

    // Return an embedding that will have very low similarity with chunks
    $mockOverpass->shouldReceive('generateEmbedding')
        ->andReturn([
            'embedding' => array_fill(0, 1536, -1), // Negative values for low similarity
            'model' => 'text-embedding-3-small',
            'dimension' => 1536,
        ]);

    // No chat should be called when no relevant chunks
    $mockOverpass->shouldNotReceive('chat');

    $result = AskQuestionAction::run([
        'document_id' => $this->document->id,
        'question' => 'Unrelated question',
        'top_k' => 5,
        'min_score' => 0.5,
    ], $mockOverpass);

    expect($result['answer'])->toBeNull();
    expect($result['relevant_chunks'])->toBeEmpty();
    expect($result['error'])->toContain('No relevant chunks found');
    expect($result['query'])->toBeNull();
});

it('filters chunks by minimum score', function () {
    $mockOverpass = mock(PythonAiBridge::class);

    // The chunks have embeddings of 0.1 and 0.2
    // Using 0.15 will give moderate similarity
    $mockOverpass->shouldReceive('generateEmbedding')
        ->andReturn([
            'embedding' => array_fill(0, 1536, 0.15),
            'model' => 'text-embedding-3-small',
            'dimension' => 1536,
        ]);

    $mockOverpass->shouldReceive('chat')
        ->andReturn([
            'response' => 'Answer based on chunks.',
            'fallback' => false,
        ]);

    $result = AskQuestionAction::run([
        'document_id' => $this->document->id,
        'question' => 'Test question',
        'top_k' => 5,
        'min_score' => 0.99, // Very high threshold to filter out chunks
    ], $mockOverpass);

    // With such a high threshold, no chunks should pass
    if (empty($result['relevant_chunks'])) {
        expect($result['answer'])->toBeNull();
        expect($result['error'])->toContain('No relevant chunks found');
    } else {
        // If any chunks pass, they should meet the threshold
        foreach ($result['relevant_chunks'] as $chunk) {
            expect($chunk['score'])->toBeGreaterThanOrEqual(0.99);
        }
    }
});

it('respects top_k limit', function () {
    // Create more chunks
    for ($i = 3; $i <= 10; $i++) {
        Chunk::create([
            'document_id' => $this->document->id,
            'chunk_index' => $i - 1,
            'content' => "Chunk {$i} content",
            'embedding_json' => json_encode(array_fill(0, 1536, 0.1 * ($i / 10))),
            'token_count' => 10,
        ]);
    }

    $mockOverpass = mock(PythonAiBridge::class);

    $mockOverpass->shouldReceive('generateEmbedding')
        ->andReturn([
            'embedding' => array_fill(0, 1536, 0.15),
            'model' => 'text-embedding-3-small',
            'dimension' => 1536,
        ]);

    // Cosine similarity is calculated internally - no mocking needed

    $mockOverpass->shouldReceive('chat')
        ->andReturn([
            'response' => 'Answer based on top chunks.',
            'fallback' => false,
        ]);

    $result = AskQuestionAction::run([
        'document_id' => $this->document->id,
        'question' => 'Test question',
        'top_k' => 3,
        'min_score' => 0.5,
    ], $mockOverpass);

    expect($result['relevant_chunks'])->toHaveCount(3);
});
