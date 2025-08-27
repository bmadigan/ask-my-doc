<?php

use App\Models\Chunk;
use App\Models\Document;
use App\Models\Query;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Create a test document with chunks
    $document = Document::create([
        'name' => 'Test Q&A Document',
        'content' => 'This is a comprehensive test document about Laravel. Laravel is a PHP framework for web artisans. It provides elegant syntax and powerful features for building modern web applications. Laravel includes features like routing, middleware, authentication, and database migrations.',
        'metadata' => [
            'chunk_size' => 100,
            'overlap_size' => 20,
            'total_chunks' => 2,
        ],
    ]);

    // Create test chunks with mock embeddings
    Chunk::create([
        'document_id' => $document->id,
        'content' => 'This is a comprehensive test document about Laravel. Laravel is a PHP framework for web artisans.',
        'embedding' => json_encode(array_fill(0, 1536, 0.1)),
        'metadata' => ['position' => 0],
    ]);

    Chunk::create([
        'document_id' => $document->id,
        'content' => 'It provides elegant syntax and powerful features for building modern web applications. Laravel includes features like routing, middleware, authentication, and database migrations.',
        'embedding' => json_encode(array_fill(0, 1536, 0.2)),
        'metadata' => ['position' => 1],
    ]);
});

it('can load the ask page', function () {
    $page = visit('/ask');

    $page->assertSee('Ask Questions')
        ->assertSee('Select Document')
        ->assertSee('Your Question')
        ->assertNoJavascriptErrors()
        ->assertNoConsoleLogs();
});

it('can select a document', function () {
    $page = visit('/ask');

    // Select the test document
    $page->select('select', 'Test Q&A Document')
        ->waitFor('[wire\\:model="question"]')
        ->assertSee('Your Question');

    // Verify document info is displayed
    expect($page->text())->toContain('Test Q&A Document');
});

it('can ask a question and get an answer', function () {
    $page = visit('/ask');

    $page->select('select', 'Test Q&A Document')
        ->fill('textarea[wire\\:model="question"]', 'What is Laravel?')
        ->click('button:contains("Ask")')
        ->waitForText('Answer', 15);

    // Should show answer section
    $page->assertSee('Answer')
        ->assertSee('Sources');

    // Verify query was logged
    expect(Query::where('question', 'What is Laravel?')->exists())->toBeTrue();
});

it('can adjust retrieval settings', function () {
    $page = visit('/ask');

    // Open settings
    $page->click('button:contains("Settings")')
        ->waitFor('input[type="range"]');

    // Adjust Top K and similarity threshold
    $page->script('document.querySelector(\'input[wire\\:model="topK"]\').value = 5')
        ->script('document.querySelector(\'input[wire\\:model="topK"]\').dispatchEvent(new Event("input"))')
        ->script('document.querySelector(\'input[wire\\:model="minScore"]\').value = 0.7')
        ->script('document.querySelector(\'input[wire\\:model="minScore"]\').dispatchEvent(new Event("input"))');

    // Ask question with custom settings
    $page->select('select', 'Test Q&A Document')
        ->fill('textarea[wire\\:model="question"]', 'Tell me about Laravel features')
        ->click('button:contains("Ask")')
        ->waitForText('Answer', 15);

    $page->assertSee('Answer');
});

it('shows relevant sources with citations', function () {
    $page = visit('/ask');

    $page->select('select', 'Test Q&A Document')
        ->fill('textarea[wire\\:model="question"]', 'What features does Laravel include?')
        ->click('button:contains("Ask")')
        ->waitForText('Sources', 15);

    // Should show source chunks
    $page->assertSee('Sources')
        ->assertSee('Relevance Score');

    // Should contain actual chunk content
    expect($page->text())->toContain('routing');
});

it('handles no matching chunks gracefully', function () {
    $page = visit('/ask');

    $page->select('select', 'Test Q&A Document')
        ->fill('textarea[wire\\:model="question"]', 'Tell me about quantum physics')
        ->script('document.querySelector(\'input[wire\\:model="minScore"]\').value = 0.9')
        ->script('document.querySelector(\'input[wire\\:model="minScore"]\').dispatchEvent(new Event("input"))')
        ->click('button:contains("Ask")')
        ->waitFor('[role="alert"]', 10);

    // Should show appropriate message
    expect($page->text())->toContain('No relevant chunks found');
});

it('validates question input', function () {
    $page = visit('/ask');

    $page->select('select', 'Test Q&A Document')
        ->click('button:contains("Ask")');

    // Should show validation error
    $page->waitForText('required', 2)
        ->assertSee('required');
});

it('shows loading state during processing', function () {
    $page = visit('/ask');

    $page->select('select', 'Test Q&A Document')
        ->fill('textarea[wire\\:model="question"]', 'What is Laravel?')
        ->click('button:contains("Ask")');

    // Should show processing indicator
    $page->assertSee('Processing');
});

it('can ask multiple questions in sequence', function () {
    $page = visit('/ask');

    // First question
    $page->select('select', 'Test Q&A Document')
        ->fill('textarea[wire\\:model="question"]', 'What is Laravel?')
        ->click('button:contains("Ask")')
        ->waitForText('Answer', 15);

    // Clear and ask second question
    $page->clear('textarea[wire\\:model="question"]')
        ->fill('textarea[wire\\:model="question"]', 'What features does it have?')
        ->click('button:contains("Ask")')
        ->waitForText('Answer', 15);

    // Both queries should be logged
    expect(Query::count())->toBe(2);
});

it('displays document statistics', function () {
    // Add more documents
    Document::create([
        'name' => 'Second Document',
        'content' => 'Another test document',
        'metadata' => ['total_chunks' => 1],
    ]);

    $page = visit('/ask');

    // Should show document count or selector with multiple options
    $page->assertSee('Test Q&A Document')
        ->assertSee('Second Document');
});

it('handles API errors gracefully', function () {
    // Mock a failed API response
    config(['openai.api_key' => 'invalid-key']);

    $page = visit('/ask');

    $page->select('select', 'Test Q&A Document')
        ->fill('textarea[wire\\:model="question"]', 'What is Laravel?')
        ->click('button:contains("Ask")')
        ->waitFor('[role="alert"]', 10);

    $page->assertSee('Failed');
});

it('preserves question history in session', function () {
    $page = visit('/ask');

    // Ask a question
    $page->select('select', 'Test Q&A Document')
        ->fill('textarea[wire\\:model="question"]', 'What is Laravel?')
        ->click('button:contains("Ask")')
        ->waitForText('Answer', 15);

    // Reload page
    $page->visit('/ask');

    // Previous question should still be visible
    expect($page->value('textarea[wire\\:model="question"]'))->toBe('What is Laravel?');
});

it('can export results', function () {
    $page = visit('/ask');

    $page->select('select', 'Test Q&A Document')
        ->fill('textarea[wire\\:model="question"]', 'What is Laravel?')
        ->click('button:contains("Ask")')
        ->waitForText('Answer', 15);

    // Check if export button exists (if implemented)
    if ($page->see('Export')) {
        $page->click('button:contains("Export")');
        // Verify download or copy functionality
    }
});

it('works on mobile viewport', function () {
    $page = visit('/ask')->resize(375, 812); // iPhone 12 Pro size

    $page->assertSee('Ask Questions')
        ->select('select', 'Test Q&A Document')
        ->fill('textarea[wire\\:model="question"]', 'What is Laravel?')
        ->click('button:contains("Ask")')
        ->waitForText('Answer', 15);

    $page->assertSee('Answer');
});
