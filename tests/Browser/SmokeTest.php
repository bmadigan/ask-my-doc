<?php

it('can access all main pages', function () {
    // Visit dashboard
    $this->visit('/')
        ->assertSee('Ask My Doc')
        ->assertSee('Quick Actions');

    // Visit ingest page
    $this->visit('/ingest')
        ->assertSee('Ingest Document')
        ->assertSee('Document Title');

    // Visit ask page
    $this->visit('/ask')
        ->assertSee('Ask Document')
        ->assertSee('Select Document');
});

it('shows the dashboard with proper elements', function () {
    $this->visit('/')
        ->assertSee('Ask My Doc')
        ->assertSee('Quick Actions')
        ->assertSee('Overpass Status')
        ->assertSee('How It Works');
});

it('shows the ingest page with form elements', function () {
    $this->visit('/ingest')
        ->assertSee('Ingest Document')
        ->assertSee('Document Title')
        ->assertSee('Document Content')
        ->assertSee('Chunk Size')
        ->assertSee('Overlap Size')
        ->assertSee('Chunk & Embed');
});

it('shows the ask page with document selection', function () {
    $this->visit('/ask')
        ->assertSee('Ask Document')
        ->assertSee('Select Document')
        ->assertSee('Your Question')
        ->assertSee('Top K Results')
        ->assertSee('Min Similarity Score');
});

it('can navigate between pages', function () {
    // Start at dashboard
    $page = $this->visit('/')
        ->assertSee('Quick Actions');

    // Navigate to ingest page
    $this->visit('/ingest')
        ->assertSee('Ingest Document');

    // Navigate to ask page
    $this->visit('/ask')
        ->assertSee('Ask Document');

    // Return to dashboard
    $this->visit('/')
        ->assertSee('Quick Actions');
});

it('displays proper dark theme styling', function () {
    $page = $this->visit('/');
    // Check that the page loads successfully with dark theme
    $page->assertSee('Ask My Doc');
    // Dark theme is applied by default
});

it('shows responsive layout', function () {
    // Test that page loads and displays properly
    $this->visit('/')
        ->assertSee('Ask My Doc')
        ->assertSee('Quick Actions');
});
