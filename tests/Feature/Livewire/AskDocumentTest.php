<?php

use App\Livewire\AskDocument;
use App\Models\Chunk;
use App\Models\Document;
use App\Models\Query;
use App\Services\Overpass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\mock;

uses(RefreshDatabase::class);

beforeEach(function () {

    // Create test document with chunks
    $this->document = Document::create([
        'name' => 'Test Document',
        'content' => 'Full document content about Laravel framework',
        'metadata' => ['chunk_size' => 100, 'total_chunks' => 2],
    ]);

    $this->chunk1 = Chunk::create([
        'document_id' => $this->document->id,
        'content' => 'Laravel is a PHP framework',
        'embedding' => json_encode(array_fill(0, 1536, 0.1)),
        'metadata' => ['position' => 0],
    ]);

    $this->chunk2 = Chunk::create([
        'document_id' => $this->document->id,
        'content' => 'It provides elegant syntax',
        'embedding' => json_encode(array_fill(0, 1536, 0.2)),
        'metadata' => ['position' => 1],
    ]);
});

it('can render the ask document component', function () {
    Livewire::test(AskDocument::class)
        ->assertSet('selectedDocumentId', null)
        ->assertSet('question', '')
        ->assertSet('topK', 5)
        ->assertSet('minScore', 0.5)
        ->assertSee('Ask Questions')
        ->assertSee('Select Document')
        ->assertStatus(200);
});

it('can load documents', function () {
    Livewire::test(AskDocument::class)
        ->assertSee('Test Document')
        ->assertCount('documents', 1);
});

it('can select a document', function () {
    Livewire::test(AskDocument::class)
        ->set('selectedDocumentId', $this->document->id)
        ->assertSet('selectedDocumentId', $this->document->id);
});

it('can ask a question successfully', function () {
    // Mock Overpass service
    $mockOverpass = mock(Overpass::class);

    $mockOverpass->shouldReceive('generateEmbedding')
        ->with('What is Laravel?')
        ->andReturn(array_fill(0, 1536, 0.15));

    $mockOverpass->shouldReceive('vectorSearch')
        ->andReturn([
            ['chunk_id' => $this->chunk1->id, 'score' => 0.95],
            ['chunk_id' => $this->chunk2->id, 'score' => 0.85],
        ]);

    $mockOverpass->shouldReceive('chat')
        ->andReturn('Laravel is a PHP framework that provides elegant syntax for web development.');

    $this->app->instance(Overpass::class, $mockOverpass);

    Livewire::test(AskDocument::class)
        ->set('selectedDocumentId', $this->document->id)
        ->set('question', 'What is Laravel?')
        ->call('askQuestion')
        ->assertSet('answer', 'Laravel is a PHP framework that provides elegant syntax for web development.')
        ->assertNotEmpty('relevantChunks');

    // Verify query was logged
    $query = Query::where('question', 'What is Laravel?')->first();
    expect($query)->not->toBeNull();
    expect($query->document_id)->toBe($this->document->id);
});

it('validates required fields', function () {
    Livewire::test(AskDocument::class)
        ->call('askQuestion')
        ->assertHasErrors(['selectedDocumentId' => 'required', 'question' => 'required']);

    Livewire::test(AskDocument::class)
        ->set('selectedDocumentId', $this->document->id)
        ->call('askQuestion')
        ->assertHasErrors(['question' => 'required'])
        ->assertHasNoErrors('selectedDocumentId');

    Livewire::test(AskDocument::class)
        ->set('question', 'Test question')
        ->call('askQuestion')
        ->assertHasErrors(['selectedDocumentId' => 'required'])
        ->assertHasNoErrors('question');
});

it('validates topK range', function () {
    Livewire::test(AskDocument::class)
        ->set('selectedDocumentId', $this->document->id)
        ->set('question', 'Test')
        ->set('topK', 0)
        ->call('askQuestion')
        ->assertHasErrors(['topK' => 'min']);

    Livewire::test(AskDocument::class)
        ->set('selectedDocumentId', $this->document->id)
        ->set('question', 'Test')
        ->set('topK', 11)
        ->call('askQuestion')
        ->assertHasErrors(['topK' => 'max']);
});

it('validates minScore range', function () {
    Livewire::test(AskDocument::class)
        ->set('selectedDocumentId', $this->document->id)
        ->set('question', 'Test')
        ->set('minScore', -0.1)
        ->call('askQuestion')
        ->assertHasErrors(['minScore' => 'min']);

    Livewire::test(AskDocument::class)
        ->set('selectedDocumentId', $this->document->id)
        ->set('question', 'Test')
        ->set('minScore', 1.1)
        ->call('askQuestion')
        ->assertHasErrors(['minScore' => 'max']);
});

it('handles no matching chunks', function () {
    // Mock Overpass to return no results
    $mockOverpass = mock(Overpass::class);

    $mockOverpass->shouldReceive('generateEmbedding')
        ->andReturn(array_fill(0, 1536, 0.1));

    $mockOverpass->shouldReceive('vectorSearch')
        ->andReturn([]);

    $this->app->instance(Overpass::class, $mockOverpass);

    Livewire::test(AskDocument::class)
        ->set('selectedDocumentId', $this->document->id)
        ->set('question', 'Unrelated question')
        ->call('askQuestion')
        ->assertSee('No relevant chunks found');
});

it('filters chunks by minimum score', function () {
    // Mock Overpass service
    $mockOverpass = mock(Overpass::class);

    $mockOverpass->shouldReceive('generateEmbedding')
        ->andReturn(array_fill(0, 1536, 0.15));

    $mockOverpass->shouldReceive('vectorSearch')
        ->andReturn([
            ['chunk_id' => $this->chunk1->id, 'score' => 0.95],
            ['chunk_id' => $this->chunk2->id, 'score' => 0.45], // Below threshold
        ]);

    $mockOverpass->shouldReceive('chat')
        ->andReturn('Answer based on high-scoring chunk only.');

    $this->app->instance(Overpass::class, $mockOverpass);

    Livewire::test(AskDocument::class)
        ->set('selectedDocumentId', $this->document->id)
        ->set('question', 'Test question')
        ->set('minScore', 0.5)
        ->call('askQuestion')
        ->assertSet('relevantChunks', function ($chunks) {
            // Should only have one chunk (score >= 0.5)
            return count($chunks) === 1;
        });
});

it('respects topK limit', function () {
    // Create more chunks
    for ($i = 3; $i <= 10; $i++) {
        Chunk::create([
            'document_id' => $this->document->id,
            'content' => "Chunk {$i} content",
            'embedding' => json_encode(array_fill(0, 1536, 0.1 * $i)),
            'metadata' => ['position' => $i - 1],
        ]);
    }

    // Mock Overpass to return many results
    $mockOverpass = mock(Overpass::class);

    $mockOverpass->shouldReceive('generateEmbedding')
        ->andReturn(array_fill(0, 1536, 0.15));

    $results = [];
    foreach (range(1, 10) as $i) {
        $results[] = ['chunk_id' => $i, 'score' => 1 - ($i * 0.05)];
    }

    $mockOverpass->shouldReceive('vectorSearch')
        ->andReturn($results);

    $mockOverpass->shouldReceive('chat')
        ->andReturn('Answer based on top chunks.');

    $this->app->instance(Overpass::class, $mockOverpass);

    Livewire::test(AskDocument::class)
        ->set('selectedDocumentId', $this->document->id)
        ->set('question', 'Test question')
        ->set('topK', 3)
        ->call('askQuestion')
        ->assertSet('relevantChunks', function ($chunks) {
            // Should only have 3 chunks
            return count($chunks) === 3;
        });
});

it('handles API errors gracefully', function () {
    // Mock Overpass to throw an error
    $mockOverpass = mock(Overpass::class);

    $mockOverpass->shouldReceive('generateEmbedding')
        ->andThrow(new Exception('API Error'));

    $this->app->instance(Overpass::class, $mockOverpass);

    Livewire::test(AskDocument::class)
        ->set('selectedDocumentId', $this->document->id)
        ->set('question', 'Test question')
        ->call('askQuestion')
        ->assertSee('Failed to process question');
});

it('clears previous results when asking new question', function () {
    // Mock Overpass service
    $mockOverpass = mock(Overpass::class);

    $mockOverpass->shouldReceive('generateEmbedding')
        ->andReturn(array_fill(0, 1536, 0.15));

    $mockOverpass->shouldReceive('vectorSearch')
        ->andReturn([
            ['chunk_id' => $this->chunk1->id, 'score' => 0.95],
        ]);

    $mockOverpass->shouldReceive('chat')
        ->andReturnUsing(function ($messages) {
            return 'Answer: '.$messages[0]['content'];
        });

    $this->app->instance(Overpass::class, $mockOverpass);

    $component = Livewire::test(AskDocument::class)
        ->set('selectedDocumentId', $this->document->id)
        ->set('question', 'First question')
        ->call('askQuestion')
        ->assertNotEmpty('answer')
        ->assertNotEmpty('relevantChunks');

    // Ask second question
    $component->set('question', 'Second question')
        ->call('askQuestion')
        ->assertSet('answer', 'Answer: Second question');
});

it('shows settings panel', function () {
    Livewire::test(AskDocument::class)
        ->assertSet('showSettings', false)
        ->call('toggleSettings')
        ->assertSet('showSettings', true)
        ->call('toggleSettings')
        ->assertSet('showSettings', false);
});

it('formats relevance scores correctly', function () {
    // Mock Overpass service
    $mockOverpass = mock(Overpass::class);

    $mockOverpass->shouldReceive('generateEmbedding')
        ->andReturn(array_fill(0, 1536, 0.15));

    $mockOverpass->shouldReceive('vectorSearch')
        ->andReturn([
            ['chunk_id' => $this->chunk1->id, 'score' => 0.956789],
        ]);

    $mockOverpass->shouldReceive('chat')
        ->andReturn('Test answer');

    $this->app->instance(Overpass::class, $mockOverpass);

    Livewire::test(AskDocument::class)
        ->set('selectedDocumentId', $this->document->id)
        ->set('question', 'Test')
        ->call('askQuestion')
        ->assertSee('95.68%'); // Score should be formatted as percentage
});

it('logs complete query metadata', function () {
    // Mock Overpass service
    $mockOverpass = mock(Overpass::class);

    $mockOverpass->shouldReceive('generateEmbedding')
        ->andReturn(array_fill(0, 1536, 0.15));

    $mockOverpass->shouldReceive('vectorSearch')
        ->andReturn([
            ['chunk_id' => $this->chunk1->id, 'score' => 0.95],
        ]);

    $mockOverpass->shouldReceive('chat')
        ->andReturn('Test answer');

    $this->app->instance(Overpass::class, $mockOverpass);

    Livewire::test(AskDocument::class)
        ->set('selectedDocumentId', $this->document->id)
        ->set('question', 'Test question')
        ->set('topK', 3)
        ->set('minScore', 0.7)
        ->call('askQuestion');

    $query = Query::where('question', 'Test question')->first();

    expect($query->metadata)->toHaveKey('top_k', 3);
    expect($query->metadata)->toHaveKey('min_score', 0.7);
    expect($query->metadata)->toHaveKey('chunks_retrieved', 1);
});

it('handles document with no chunks', function () {
    // Create document without chunks
    $emptyDoc = Document::create([
        'name' => 'Empty Document',
        'content' => 'No chunks',
        'metadata' => ['total_chunks' => 0],
    ]);

    Livewire::test(AskDocument::class)
        ->set('selectedDocumentId', $emptyDoc->id)
        ->set('question', 'Test question')
        ->call('askQuestion')
        ->assertSee('No chunks available');
});
