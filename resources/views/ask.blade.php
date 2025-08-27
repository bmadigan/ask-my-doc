<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Ask My Doc - Ask Questions</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body style="background: var(--linear-bg-primary); color: var(--linear-text-primary);">
    <div class="min-h-screen">
        {{-- Header --}}
        <header style="background: var(--linear-bg-secondary); border-bottom: 1px solid var(--linear-border);">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex justify-between items-center py-4">
                    <h1 class="text-xl font-semibold" style="color: var(--linear-text-primary); letter-spacing: -0.02em;">Ask My Doc</h1>
                    <nav class="flex space-x-4">
                        <a href="{{ route('dashboard') }}" class="px-3 py-2 rounded-md text-sm font-medium transition-all" style="{{ request()->routeIs('dashboard') ? 'background: var(--linear-bg-hover); color: var(--linear-text-primary);' : 'color: var(--linear-text-secondary);' }}" onmouseover="this.style.background='var(--linear-bg-hover)'" onmouseout="{{ request()->routeIs('dashboard') ? '' : 'this.style.background=\'transparent\'' }}">
                            Dashboard
                        </a>
                        <a href="{{ route('ingest') }}" class="px-3 py-2 rounded-md text-sm font-medium transition-all" style="{{ request()->routeIs('ingest') ? 'background: var(--linear-bg-hover); color: var(--linear-text-primary);' : 'color: var(--linear-text-secondary);' }}" onmouseover="this.style.background='var(--linear-bg-hover)'" onmouseout="{{ request()->routeIs('ingest') ? '' : 'this.style.background=\'transparent\'' }}">
                            Ingest
                        </a>
                        <a href="{{ route('ask') }}" class="px-3 py-2 rounded-md text-sm font-medium transition-all" style="{{ request()->routeIs('ask') ? 'background: var(--linear-bg-hover); color: var(--linear-text-primary);' : 'color: var(--linear-text-secondary);' }}" onmouseover="this.style.background='var(--linear-bg-hover)'" onmouseout="{{ request()->routeIs('ask') ? '' : 'this.style.background=\'transparent\'' }}">
                            Ask
                        </a>
                    </nav>
                </div>
            </div>
        </header>

        {{-- Main Content --}}
        <main>
            @livewire('ask-document')
        </main>
    </div>

    @livewireScripts
</body>
</html>