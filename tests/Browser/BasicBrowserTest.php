<?php

it('can access and navigate all pages', function () {
    // Test dashboard
    $this->visit('/')
        ->assertSee('Ask My Doc')
        ->assertSee('Quick Actions');

    // Test ingest page
    $this->visit('/ingest')
        ->assertSee('Ingest Document')
        ->assertSee('Document Title');

    // Test ask page
    $this->visit('/ask')
        ->assertSee('Ask Document')
        ->assertSee('Select Document');
});

it('validates ingest form', function () {
    $this->visit('/ingest')
        ->press('Chunk & Embed')
        ->waitForText('required', 2)
        ->assertSee('required');
});

it('shows ask form elements', function () {
    $this->visit('/ask')
        ->assertSee('Ask Document')
        ->assertSee('Select Document')
        ->assertSee('Your Question')
        ->assertSee('Top K Results');
});
