<?php

use App\Livewire\AskDocument;
use App\Models\Chunk;
use App\Models\Document;
use Bmadigan\Overpass\Services\PythonAiBridge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

use function Pest\Laravel\mock;

uses(RefreshDatabase::class);

it('renders the ask document component', function () {
    Livewire::test(AskDocument::class)
        ->assertStatus(200);
});

it('shows documents in dropdown', function () {
    Document::create([
        'title' => 'Test Document',
        'bytes' => 100,
    ]);

    Livewire::test(AskDocument::class)
        ->assertSee('Test Document');
});

it('validates required document selection', function () {
    Livewire::test(AskDocument::class)
        ->set('documentId', null)
        ->set('question', 'What is the meaning of life?')
        ->call('ask')
        ->assertHasErrors(['documentId' => 'required']);
});

it('validates required question', function () {
    $document = Document::create([
        'title' => 'Test Document',
        'bytes' => 100,
    ]);

    Livewire::test(AskDocument::class)
        ->set('documentId', $document->id)
        ->set('question', '')
        ->call('ask')
        ->assertHasErrors(['question' => 'required']);
});

it('validates question minimum length', function () {
    $document = Document::create([
        'title' => 'Test Document',
        'bytes' => 100,
    ]);

    Livewire::test(AskDocument::class)
        ->set('documentId', $document->id)
        ->set('question', 'Hi')
        ->call('ask')
        ->assertHasErrors(['question' => 'min']);
});

it('sets document id when document-ingested event is received', function () {
    $document = Document::create([
        'title' => 'Test Document',
        'bytes' => 100,
    ]);

    Livewire::test(AskDocument::class)
        ->dispatch('document-ingested', documentId: $document->id)
        ->assertSet('documentId', $document->id);
});

it('toggles source visibility', function () {
    Livewire::test(AskDocument::class)
        ->assertSet('showSources', false)
        ->call('toggleSources')
        ->assertSet('showSources', true)
        ->call('toggleSources')
        ->assertSet('showSources', false);
});

it('successfully asks a question and displays answer', function () {
    $document = Document::create([
        'title' => 'Test Document',
        'bytes' => 100,
    ]);

    Chunk::create([
        'document_id' => $document->id,
        'content' => 'The answer is 42.',
        'chunk_index' => 0,
        'embedding_json' => json_encode(array_fill(0, 1536, 0.1)),
    ]);

    $mockOverpass = mock(PythonAiBridge::class);
    $mockOverpass->shouldReceive('generateEmbedding')
        ->andReturn([
            'embedding' => array_fill(0, 1536, 0.1),
            'model' => 'text-embedding-3-small',
            'dimension' => 1536,
        ]);
    $mockOverpass->shouldReceive('chat')
        ->andReturn([
            'response' => 'The answer to your question is 42.',
            'fallback' => false,
        ]);

    app()->instance(PythonAiBridge::class, $mockOverpass);

    Livewire::test(AskDocument::class)
        ->set('documentId', $document->id)
        ->set('question', 'What is the answer?')
        ->call('ask')
        ->assertHasNoErrors()
        ->assertSet('processing', false)
        ->assertNotSet('answer', '');
});

it('shows error when query fails', function () {
    $document = Document::create([
        'title' => 'Test Document',
        'bytes' => 100,
    ]);

    Chunk::create([
        'document_id' => $document->id,
        'content' => 'Some content',
        'chunk_index' => 0,
        'embedding_json' => json_encode(array_fill(0, 1536, 0.1)),
    ]);

    $mockOverpass = mock(PythonAiBridge::class);
    $mockOverpass->shouldReceive('generateEmbedding')
        ->andThrow(new Exception('API Error'));

    app()->instance(PythonAiBridge::class, $mockOverpass);

    Livewire::test(AskDocument::class)
        ->set('documentId', $document->id)
        ->set('question', 'What is the answer?')
        ->call('ask')
        ->assertSet('processing', false)
        ->assertNotSet('error', null);
});
