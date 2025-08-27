<?php

use App\Models\Chunk;
use App\Models\Document;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Database is refreshed automatically
});

it('can load the ingest page', function () {
    $page = visit('/ingest');

    $page->assertSee('Ingest Document')
        ->assertSee('Document Name')
        ->assertSee('Document Text')
        ->assertNoJavascriptErrors()
        ->assertNoConsoleLogs();
});

it('can ingest a document with default settings', function () {
    $page = visit('/ingest');

    $page->fill('form input[type="text"]', 'Test Document')
        ->fill('form textarea', 'This is a test document with enough content to be chunked properly. Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.')
        ->click('button[type="submit"]')
        ->waitForText('Document chunked and embedded successfully!', 10);

    // Verify document was created
    expect(Document::where('name', 'Test Document')->exists())->toBeTrue();

    // Verify chunks were created
    $document = Document::where('name', 'Test Document')->first();
    expect($document->chunks()->count())->toBeGreaterThan(0);
});

it('can upload a text file', function () {
    // Create a temporary text file
    $tempFile = tempnam(sys_get_temp_dir(), 'test_').'.txt';
    file_put_contents($tempFile, str_repeat('This is test content for file upload. ', 50));

    $page = visit('/ingest');

    $page->fill('form input[type="text"]', 'Uploaded Document')
        ->attach('input[type="file"]', $tempFile)
        ->click('button[type="submit"]')
        ->waitForText('Document chunked and embedded successfully!', 10);

    // Cleanup
    unlink($tempFile);

    // Verify document was created
    expect(Document::where('name', 'Uploaded Document')->exists())->toBeTrue();
});

it('can configure chunk settings', function () {
    $page = visit('/ingest');

    // Open advanced settings
    $page->click('button:contains("Advanced Settings")')
        ->waitFor('input[type="range"]');

    // Adjust chunk size and overlap
    $page->script('document.querySelector(\'input[type="range"][max="2000"]\').value = 500')
        ->script('document.querySelector(\'input[type="range"][max="2000"]\').dispatchEvent(new Event("input"))')
        ->script('document.querySelector(\'input[type="range"][max="500"]\').value = 100')
        ->script('document.querySelector(\'input[type="range"][max="500"]\').dispatchEvent(new Event("input"))');

    // Fill in document details
    $page->fill('form input[type="text"]', 'Custom Chunk Document')
        ->fill('form textarea', str_repeat('Test content for custom chunking. ', 100))
        ->click('button[type="submit"]')
        ->waitForText('Document chunked and embedded successfully!', 10);

    // Verify chunks were created with expected sizes
    $document = Document::where('name', 'Custom Chunk Document')->first();
    $chunks = $document->chunks;

    expect($chunks->count())->toBeGreaterThan(0);
    expect($chunks->first()->content)->toHaveLength(lessThanOrEqual: 500);
});

it('validates required fields', function () {
    $page = visit('/ingest');

    // Try to submit without filling required fields
    $page->click('button[type="submit"]')
        ->waitForText('required', 2);

    $page->assertSee('required');
});

it('handles API errors gracefully', function () {
    // Mock a failed API response by using invalid API key
    config(['openai.api_key' => 'invalid-key']);

    $page = visit('/ingest');

    $page->fill('form input[type="text"]', 'Error Test Document')
        ->fill('form textarea', 'This should fail due to invalid API key')
        ->click('button[type="submit"]')
        ->waitFor('[role="alert"]', 10);

    $page->assertSee('Failed to');
});

it('displays processing status during ingestion', function () {
    $page = visit('/ingest');

    $page->fill('form input[type="text"]', 'Status Test Document')
        ->fill('form textarea', str_repeat('Content for status testing. ', 50))
        ->click('button[type="submit"]');

    // Should show processing indicator
    $page->waitForText('Processing', 2)
        ->assertSee('Processing');

    // Wait for completion
    $page->waitForText('successfully', 10);
});

it('can handle large documents', function () {
    $page = visit('/ingest');

    // Create a large document (10,000 characters)
    $largeContent = str_repeat('Large document content. ', 400);

    $page->fill('form input[type="text"]', 'Large Document')
        ->fill('form textarea', $largeContent)
        ->click('button[type="submit"]')
        ->waitForText('Document chunked and embedded successfully!', 30);

    // Verify multiple chunks were created
    $document = Document::where('name', 'Large Document')->first();
    expect($document->chunks()->count())->toBeGreaterThan(5);
});

it('preserves document metadata', function () {
    $page = visit('/ingest');

    $documentContent = 'Document with metadata tracking for testing purposes.';

    $page->fill('form input[type="text"]', 'Metadata Test')
        ->fill('form textarea', $documentContent)
        ->click('button[type="submit"]')
        ->waitForText('successfully', 10);

    // Verify metadata
    $document = Document::where('name', 'Metadata Test')->first();
    expect($document->metadata)->toHaveKey('chunk_size');
    expect($document->metadata)->toHaveKey('overlap_size');
    expect($document->metadata)->toHaveKey('total_chunks');
});

it('can navigate between pages', function () {
    $page = visit('/ingest');

    // Navigate to Ask page
    $page->click('a:contains("Ask")')
        ->waitForUrl('/ask')
        ->assertUrlIs('/ask')
        ->assertSee('Ask Questions');

    // Navigate back to Ingest
    $page->click('a:contains("Ingest")')
        ->waitForUrl('/ingest')
        ->assertUrlIs('/ingest')
        ->assertSee('Ingest Document');

    // Navigate to Dashboard
    $page->click('a:contains("Dashboard")')
        ->waitForUrl('/')
        ->assertUrlIs('/')
        ->assertSee('Overpass Status');
});
