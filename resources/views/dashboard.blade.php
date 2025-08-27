<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Ask My Doc - Dashboard</title>
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
        <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            {{-- Status Card --}}
            <div class="mb-8">
                @livewire('overpass-status-card')
            </div>

            {{-- Quick Actions --}}
            <div class="grid md:grid-cols-2 gap-8">
                <div class="linear-card" style="background: var(--linear-bg-secondary); border: 1px solid var(--linear-border); border-radius: 12px; padding: 2rem;">
                    <h2 class="text-lg font-semibold mb-6" style="color: var(--linear-text-primary);">Quick Actions</h2>
                    <div class="space-y-4">
                        <a href="{{ route('ingest') }}" class="block w-full linear-button-primary text-center" style="padding: 0.75rem 1.5rem; text-decoration: none;">
                            <svg class="w-5 h-5 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                            </svg>
                            Ingest New Document
                        </a>
                        <a href="{{ route('ask') }}" class="block w-full text-center" style="background: var(--linear-accent-green); color: white; padding: 0.75rem 1.5rem; border-radius: 6px; font-weight: 500; font-size: 14px; transition: all 0.15s ease; text-decoration: none;" onmouseover="this.style.opacity='0.9'" onmouseout="this.style.opacity='1'">
                            <svg class="w-5 h-5 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-4l-4 4z"></path>
                            </svg>
                            Ask Questions
                        </a>
                    </div>
                </div>

                <div class="linear-card" style="background: var(--linear-bg-secondary); border: 1px solid var(--linear-border); border-radius: 12px; padding: 2rem;">
                    <h2 class="text-lg font-semibold mb-6" style="color: var(--linear-text-primary);">How It Works</h2>
                    <ol class="space-y-4" style="color: var(--linear-text-secondary);">
                        <li class="flex items-start">
                            <span class="flex-shrink-0 w-6 h-6 rounded-full flex items-center justify-center text-xs font-medium mr-3" style="background: var(--linear-accent-blue); color: white;">1</span>
                            <span class="text-sm">Paste or upload your document (text or markdown)</span>
                        </li>
                        <li class="flex items-start">
                            <span class="flex-shrink-0 w-6 h-6 rounded-full flex items-center justify-center text-xs font-medium mr-3" style="background: var(--linear-accent-blue); color: white;">2</span>
                            <span class="text-sm">Document is chunked and embeddings are generated</span>
                        </li>
                        <li class="flex items-start">
                            <span class="flex-shrink-0 w-6 h-6 rounded-full flex items-center justify-center text-xs font-medium mr-3" style="background: var(--linear-accent-blue); color: white;">3</span>
                            <span class="text-sm">Ask questions and get AI-powered answers with citations</span>
                        </li>
                    </ol>
                </div>
            </div>

            {{-- Recent Documents --}}
            @php
                $recentDocuments = \App\Models\Document::latest()->take(5)->get();
            @endphp
            @if($recentDocuments->count() > 0)
                <div class="mt-8 linear-card" style="background: var(--linear-bg-secondary); border: 1px solid var(--linear-border); border-radius: 12px; padding: 2rem;">
                    <h2 class="text-lg font-semibold mb-6" style="color: var(--linear-text-primary);">Recent Documents</h2>
                    <div class="space-y-2">
                        @foreach($recentDocuments as $document)
                            <div class="flex items-center justify-between py-3" style="border-bottom: 1px solid var(--linear-border);">
                                <div>
                                    <p class="font-medium" style="color: var(--linear-text-primary);">{{ $document->title }}</p>
                                    <p class="text-sm" style="color: var(--linear-text-tertiary);">
                                        {{ $document->chunks->count() }} chunks • {{ $document->created_at->diffForHumans() }}
                                    </p>
                                </div>
                                <a href="{{ route('ask') }}" class="text-sm font-medium" style="color: var(--linear-accent-blue); text-decoration: none;" onmouseover="this.style.textDecoration='underline'" onmouseout="this.style.textDecoration='none'">
                                    Ask →
                                </a>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </main>
    </div>

    @livewireScripts
</body>
</html>