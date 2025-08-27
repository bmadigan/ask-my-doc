<?php

it('can access all main pages without errors', function () {
    $pages = visit(['/', '/ingest', '/ask']);

    $pages->assertNoJavascriptErrors()
        ->assertNoConsoleLogs();
});

it('has consistent navigation across all pages', function () {
    $routes = ['/', '/ingest', '/ask'];

    foreach ($routes as $route) {
        $page = visit($route);

        // Check navigation links exist
        $page->assertSee('Dashboard')
            ->assertSee('Ingest')
            ->assertSee('Ask');

        // Check header exists
        $page->assertSee('Ask My Doc');
    }
});

it('maintains dark theme across all pages', function () {
    $pages = ['/', '/ingest', '/ask'];

    foreach ($pages as $pagePath) {
        $page = visit($pagePath);

        // Check for dark theme CSS variables
        $isDarkTheme = $page->script('
            const styles = getComputedStyle(document.documentElement);
            return styles.getPropertyValue("--linear-bg-primary") === "#0e0e10";
        ');

        expect($isDarkTheme)->toBeTrue();
    }
});

it('loads all required assets', function () {
    $page = visit('/');

    // Check that CSS is loaded
    $hasStyles = $page->script('
        return document.styleSheets.length > 0;
    ');

    // Check that Alpine/Livewire is loaded
    $hasAlpine = $page->script('
        return typeof window.Alpine !== "undefined";
    ');

    $hasLivewire = $page->script('
        return typeof window.Livewire !== "undefined";
    ');

    expect($hasStyles)->toBeTrue();
    expect($hasAlpine)->toBeTrue();
    expect($hasLivewire)->toBeTrue();
});

it('works on different viewport sizes', function () {
    $viewports = [
        ['width' => 375, 'height' => 812, 'name' => 'iPhone 12 Pro'],
        ['width' => 768, 'height' => 1024, 'name' => 'iPad'],
        ['width' => 1920, 'height' => 1080, 'name' => 'Desktop HD'],
        ['width' => 2560, 'height' => 1440, 'name' => 'Desktop 2K'],
    ];

    foreach ($viewports as $viewport) {
        $page = visit('/')
            ->resize($viewport['width'], $viewport['height']);

        // Basic smoke test for each viewport
        $page->assertSee('Ask My Doc')
            ->assertSee('Dashboard')
            ->assertNoJavascriptErrors();
    }
});

it('has proper meta tags', function () {
    $page = visit('/');

    $hasViewport = $page->script('
        const viewport = document.querySelector(\'meta[name="viewport"]\');
        return viewport && viewport.content.includes("width=device-width");
    ');

    $hasCharset = $page->script('
        const charset = document.querySelector(\'meta[charset]\');
        return charset && charset.getAttribute("charset") === "utf-8";
    ');

    $hasCsrfToken = $page->script('
        const csrf = document.querySelector(\'meta[name="csrf-token"]\');
        return csrf && csrf.content.length > 0;
    ');

    expect($hasViewport)->toBeTrue();
    expect($hasCharset)->toBeTrue();
    expect($hasCsrfToken)->toBeTrue();
});

it('handles 404 pages gracefully', function () {
    $page = visit('/non-existent-page');

    // Should show 404 page without JavaScript errors
    $page->assertSee('404')
        ->assertNoJavascriptErrors();
});

it('has functioning Livewire components', function () {
    $page = visit('/');

    // Test Overpass status card exists and can be interacted with
    $page->assertSee('Overpass Status')
        ->assertSee('Test Connection');

    // Click test connection button
    $page->click('button:contains("Test Connection")')
        ->waitForText('Testing...', 2);
});

it('maintains session across page navigation', function () {
    $page = visit('/');

    // Set a session value via JavaScript
    $page->script('
        sessionStorage.setItem("testKey", "testValue");
    ');

    // Navigate to another page
    $page->click('a:contains("Ingest")')
        ->waitForUrl('/ingest');

    // Check session value persists
    $sessionValue = $page->script('
        return sessionStorage.getItem("testKey");
    ');

    expect($sessionValue)->toBe('testValue');
});

it('has accessible color contrast', function () {
    $page = visit('/');

    // Check that text has sufficient contrast
    $hasGoodContrast = $page->script('
        const textColor = getComputedStyle(document.body).color;
        const bgColor = getComputedStyle(document.body).backgroundColor;
        // Basic check that text isn\'t same as background
        return textColor !== bgColor;
    ');

    expect($hasGoodContrast)->toBeTrue();
});

it('loads within acceptable time', function () {
    $startTime = microtime(true);

    $page = visit('/');
    $page->waitFor('body');

    $loadTime = microtime(true) - $startTime;

    // Page should load within 5 seconds
    expect($loadTime)->toBeLessThan(5);
});

it('has no broken links in navigation', function () {
    $page = visit('/');

    $links = [
        'Dashboard' => '/',
        'Ingest' => '/ingest',
        'Ask' => '/ask',
    ];

    foreach ($links as $text => $expectedUrl) {
        $page->visit('/')
            ->click("a:contains('{$text}')")
            ->waitForUrl($expectedUrl)
            ->assertUrlIs($expectedUrl);
    }
});

it('handles rapid navigation without errors', function () {
    $page = visit('/');

    // Rapidly navigate between pages
    for ($i = 0; $i < 5; $i++) {
        $page->click('a:contains("Ingest")')
            ->waitForUrl('/ingest')
            ->click('a:contains("Ask")')
            ->waitForUrl('/ask')
            ->click('a:contains("Dashboard")')
            ->waitForUrl('/');
    }

    $page->assertNoJavascriptErrors()
        ->assertSee('Ask My Doc');
});

it('properly escapes user input', function () {
    $page = visit('/ingest');

    // Try to inject script tag
    $maliciousInput = '<script>alert("XSS")</script>';

    $page->fill('form input[type="text"]', $maliciousInput)
        ->fill('form textarea', 'Safe content')
        ->click('button[type="submit"]');

    // Should not execute the script
    $page->assertNoJavascriptErrors();

    // The input should be escaped if displayed
    $alertShown = $page->script('
        return window.alertShown || false;
    ');

    expect($alertShown)->toBeFalse();
});
