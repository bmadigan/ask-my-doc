<?php

use App\Actions\Query\AskQuestionAction;
use App\Models\Chunk;
use App\Models\Document;
use App\Models\Query;
use App\Services\Overpass;
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
    $mockOverpass = mock(Overpass::class);

    // Mock embedding generation
    $mockOverpass->shouldReceive('generateEmbedding')
        ->with('What is Laravel?')
        ->andReturn(array_fill(0, 1536, 0.15));

    // Mock vector search
    $mockOverpass->shouldReceive('vectorSearch')
        ->andReturn([
            ['chunk_id' => $this->chunk1->id, 'score' => 0.95],
            ['chunk_id' => $this->chunk2->id, 'score' => 0.85],
        ]);

    // Mock chat response
    $mockOverpass->shouldReceive('chat')
        ->andReturn('Laravel is a PHP framework that provides elegant syntax for web artisans.');

    $action = new AskQuestionAction($mockOverpass);

    $result = $action->execute([
        'document_id' => $this->document->id,
        'question' => 'What is Laravel?',
        'top_k' => 5,
        'min_score' => 0.5,
    ]);

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

    $mockOverpass = mock(Overpass::class);
    $action = new AskQuestionAction($mockOverpass);

    expect(fn () => $action->execute([
        'document_id' => $emptyDoc->id,
        'question' => 'Test question',
        'top_k' => 5,
        'min_score' => 0.5,
    ]))->toThrow(Exception::class, 'Document has no chunks available');
});

it('returns error when no relevant chunks found', function () {
    $mockOverpass = mock(Overpass::class);

    $mockOverpass->shouldReceive('generateEmbedding')
        ->andReturn(array_fill(0, 1536, 0.1));

    $mockOverpass->shouldReceive('vectorSearch')
        ->andReturn([]); // No results

    $action = new AskQuestionAction($mockOverpass);

    $result = $action->execute([
        'document_id' => $this->document->id,
        'question' => 'Unrelated question',
        'top_k' => 5,
        'min_score' => 0.5,
    ]);

    expect($result['answer'])->toBeNull();
    expect($result['relevant_chunks'])->toBeEmpty();
    expect($result['error'])->toContain('No relevant chunks found');
    expect($result['query'])->toBeNull();
});

it('filters chunks by minimum score', function () {
    $mockOverpass = mock(Overpass::class);

    $mockOverpass->shouldReceive('generateEmbedding')
        ->andReturn(array_fill(0, 1536, 0.15));

    $mockOverpass->shouldReceive('vectorSearch')
        ->andReturn([
            ['chunk_id' => $this->chunk1->id, 'score' => 0.95],
            ['chunk_id' => $this->chunk2->id, 'score' => 0.45], // Below threshold
        ]);

    $mockOverpass->shouldReceive('chat')
        ->andReturn('Answer based on one chunk.');

    $action = new AskQuestionAction($mockOverpass);

    $result = $action->execute([
        'document_id' => $this->document->id,
        'question' => 'Test question',
        'top_k' => 5,
        'min_score' => 0.5,
    ]);

    expect($result['relevant_chunks'])->toHaveCount(1);
    expect($result['relevant_chunks'][0]['score'])->toBeGreaterThanOrEqual(0.5);
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

    $mockOverpass = mock(Overpass::class);

    $mockOverpass->shouldReceive('generateEmbedding')
        ->andReturn(array_fill(0, 1536, 0.15));

    // Return many results
    $results = [];
    for ($i = 1; $i <= 10; $i++) {
        $results[] = ['chunk_id' => $i, 'score' => 1 - ($i * 0.05)];
    }

    $mockOverpass->shouldReceive('vectorSearch')
        ->with(anything(), 'chunks', 3, 0.5) // Expecting top_k = 3
        ->andReturn(array_slice($results, 0, 3));

    $mockOverpass->shouldReceive('chat')
        ->andReturn('Answer based on top chunks.');

    $action = new AskQuestionAction($mockOverpass);

    $result = $action->execute([
        'document_id' => $this->document->id,
        'question' => 'Test question',
        'top_k' => 3,
        'min_score' => 0.5,
    ]);

    expect($result['relevant_chunks'])->toHaveCount(3);
});

it('logs query with metrics', function () {
    $mockOverpass = mock(Overpass::class);

    $mockOverpass->shouldReceive('generateEmbedding')
        ->andReturn(array_fill(0, 1536, 0.15));

    $mockOverpass->shouldReceive('vectorSearch')
        ->andReturn([
            ['chunk_id' => $this->chunk1->id, 'score' => 0.95],
        ]);

    $mockOverpass->shouldReceive('chat')
        ->andReturn('Test answer');

    $action = new AskQuestionAction($mockOverpass);

    $queryCountBefore = Query::count();

    $result = $action->execute([
        'document_id' => $this->document->id,
        'question' => 'What is Laravel?',
        'top_k' => 5,
        'min_score' => 0.5,
    ]);

    expect(Query::count())->toBe($queryCountBefore + 1);

    $query = $result['query'];
    expect($query->document_id)->toBe($this->document->id);
    expect($query->question)->toBe('What is Laravel?');
    expect($query->top_k_returned)->toBe(1);
    expect($query->latency_ms)->toBeGreaterThan(0);
});

it('formats chunks with score percentages', function () {
    $mockOverpass = mock(Overpass::class);

    $mockOverpass->shouldReceive('generateEmbedding')
        ->andReturn(array_fill(0, 1536, 0.15));

    $mockOverpass->shouldReceive('vectorSearch')
        ->andReturn([
            ['chunk_id' => $this->chunk1->id, 'score' => 0.956789],
        ]);

    $mockOverpass->shouldReceive('chat')
        ->andReturn('Test answer');

    $action = new AskQuestionAction($mockOverpass);

    $result = $action->execute([
        'document_id' => $this->document->id,
        'question' => 'Test',
        'top_k' => 5,
        'min_score' => 0.5,
    ]);

    expect($result['relevant_chunks'][0]['score_percentage'])->toBe(95.68);
});

it('sorts chunks by score descending', function () {
    $mockOverpass = mock(Overpass::class);

    $mockOverpass->shouldReceive('generateEmbedding')
        ->andReturn(array_fill(0, 1536, 0.15));

    $mockOverpass->shouldReceive('vectorSearch')
        ->andReturn([
            ['chunk_id' => $this->chunk2->id, 'score' => 0.75],
            ['chunk_id' => $this->chunk1->id, 'score' => 0.95],
        ]);

    $mockOverpass->shouldReceive('chat')
        ->andReturn('Test answer');

    $action = new AskQuestionAction($mockOverpass);

    $result = $action->execute([
        'document_id' => $this->document->id,
        'question' => 'Test',
        'top_k' => 5,
        'min_score' => 0.5,
    ]);

    expect($result['relevant_chunks'][0]['score'])->toBe(0.95);
    expect($result['relevant_chunks'][1]['score'])->toBe(0.75);
});

it('includes chunk content in context for GPT', function () {
    $mockOverpass = mock(Overpass::class);

    $mockOverpass->shouldReceive('generateEmbedding')
        ->andReturn(array_fill(0, 1536, 0.15));

    $mockOverpass->shouldReceive('vectorSearch')
        ->andReturn([
            ['chunk_id' => $this->chunk1->id, 'score' => 0.95],
            ['chunk_id' => $this->chunk2->id, 'score' => 0.85],
        ]);

    // Capture the messages sent to chat
    $capturedMessages = null;
    $mockOverpass->shouldReceive('chat')
        ->withArgs(function ($messages) use (&$capturedMessages) {
            $capturedMessages = $messages;

            return true;
        })
        ->andReturn('Test answer');

    $action = new AskQuestionAction($mockOverpass);

    $result = $action->execute([
        'document_id' => $this->document->id,
        'question' => 'What is Laravel?',
        'top_k' => 5,
        'min_score' => 0.5,
    ]);

    // Verify context contains chunk content
    expect($capturedMessages[1]['content'])->toContain('[1]');
    expect($capturedMessages[1]['content'])->toContain('[2]');
    expect($capturedMessages[1]['content'])->toContain('Laravel is a PHP framework');
    expect($capturedMessages[1]['content'])->toContain('elegant syntax');
});
