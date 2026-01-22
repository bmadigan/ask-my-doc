<div class="max-w-4xl mx-auto p-8">
    <div class="linear-card" style="background: var(--linear-bg-secondary); border: 1px solid var(--linear-border); border-radius: 12px; padding: 2rem;">
        <h2 class="text-2xl font-semibold mb-8" style="color: var(--linear-text-primary); letter-spacing: -0.02em;">Ask Document</h2>

        <form wire:submit="ask" class="space-y-6">
            {{-- Document Selector --}}
            <div>
                <label for="documentId" class="block text-sm font-medium mb-2" style="color: var(--linear-text-secondary);">
                    Select Document
                </label>
                <select 
                    id="documentId" 
                    wire:model="documentId"
                    class="w-full linear-input" 
                    style="padding: 0.75rem 1rem; font-size: 14px; cursor: pointer;"
                    :disabled="$wire.processing"
                >
                    <option value="">Choose a document...</option>
                    @foreach($this->documents as $document)
                        <option value="{{ $document->id }}">
                            {{ $document->title }} ({{ $document->chunks_count ?? $document->chunks->count() }} chunks)
                        </option>
                    @endforeach
                </select>
                @error('documentId') 
                    <span class="text-red-500 text-sm mt-1">{{ $message }}</span>
                @enderror
            </div>

            {{-- Question Input --}}
            <div>
                <label for="question" class="block text-sm font-medium mb-2" style="color: var(--linear-text-secondary);">
                    Your Question
                </label>
                <textarea
                    id="question"
                    wire:model.blur="question"
                    rows="3"
                    class="w-full linear-input" 
                    style="padding: 0.75rem 1rem; font-size: 14px; min-height: 80px; resize: vertical;"
                    placeholder="Ask a question about the document..."
                    :disabled="$wire.processing"
                ></textarea>
                @error('question') 
                    <span class="text-red-500 text-sm mt-1">{{ $message }}</span>
                @enderror
            </div>

            {{-- Advanced Settings --}}
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="topK" class="block text-sm font-medium mb-2" style="color: var(--linear-text-secondary);">
                        Top K Results
                    </label>
                    <input 
                        type="range" 
                        id="topK" 
                        wire:model.live="topK"
                        min="1"
                        max="10"
                        class="w-full"
                        :disabled="$wire.processing"
                    >
                    <div class="text-center text-sm" style="color: var(--linear-text-tertiary); margin-top: 0.5rem;">{{ $topK }}</div>
                </div>
                <div>
                    <label for="minScore" class="block text-sm font-medium mb-2" style="color: var(--linear-text-secondary);">
                        Min Similarity Score
                    </label>
                    <input 
                        type="range" 
                        id="minScore" 
                        wire:model.live="minScore"
                        min="0"
                        max="1"
                        step="0.1"
                        class="w-full"
                        :disabled="$wire.processing"
                    >
                    <div class="text-center text-sm" style="color: var(--linear-text-tertiary); margin-top: 0.5rem;">{{ $minScore }}</div>
                </div>
            </div>

            {{-- Submit Button --}}
            <div>
                <button 
                    type="submit" 
                    class="linear-button-primary flex items-center gap-2" 
                    style="padding: 0.625rem 1.25rem; font-size: 14px; font-weight: 500; background: var(--linear-accent-green);"
                    :disabled="$wire.processing || !$wire.documentId"
                >
                    <span wire:loading.remove wire:target="ask">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-4l-4 4z"></path>
                        </svg>
                    </span>
                    <span wire:loading wire:target="ask">
                        <svg class="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </span>
                    <span wire:loading.remove wire:target="ask">Ask</span>
                    <span wire:loading wire:target="ask">Processing...</span>
                </button>
            </div>
        </form>

        {{-- Answer Section --}}
        @if ($answer)
            <div class="mt-8 space-y-4">
                <div class="rounded-lg p-6" style="background: var(--linear-bg-tertiary); border: 1px solid var(--linear-border);">
                    <h3 class="text-lg font-semibold mb-3" style="color: var(--linear-text-primary);">Answer</h3>
                    <div class="prose max-w-none" style="color: var(--linear-text-primary); line-height: 1.6;">
                        {!! nl2br(e($answer)) !!}
                    </div>
                    @if($latency > 0)
                        <div class="mt-3 text-sm" style="color: var(--linear-text-tertiary);">
                            Response time: {{ $latency }}ms
                        </div>
                    @endif
                </div>

                {{-- Sources Toggle --}}
                @if (count($sources) > 0)
                    <button 
                        wire:click="toggleSources"
                        class="text-sm font-medium flex items-center gap-1" 
                        style="color: var(--linear-accent-blue); cursor: pointer;"
                    >
                        <svg class="w-4 h-4 transition-transform duration-200 {{ $showSources ? 'rotate-90' : '' }}" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                        </svg>
                        View Sources ({{ count($sources) }})
                    </button>
                    
                    {{-- Sources List --}}
                    @if ($showSources)
                        <div class="space-y-3">
                            @foreach ($sources as $source)
                                <div wire:key="source-{{ $source['index'] }}" class="rounded-lg p-4" style="background: var(--linear-bg-primary); border: 1px solid var(--linear-border);">
                                    <div class="flex items-center justify-between mb-2">
                                        <span class="text-sm font-semibold" style="color: var(--linear-text-primary);">
                                            [{{ $source['index'] }}] Chunk
                                        </span>
                                        <span class="text-xs" style="color: var(--linear-text-tertiary);">
                                            Score: {{ $source['score'] }}
                                        </span>
                                    </div>
                                    <p class="text-sm" style="color: var(--linear-text-secondary); line-height: 1.5;">
                                        {{ $source['preview'] }}
                                    </p>
                                </div>
                            @endforeach
                        </div>
                    @endif
                @endif
            </div>
        @endif

        {{-- Error Message --}}
        @if ($error)
            <div class="mt-6 p-4 rounded-lg" style="background: rgba(242, 85, 90, 0.1); border: 1px solid rgba(242, 85, 90, 0.2);">
                <div class="flex items-center">
                    <svg class="w-5 h-5 text-red-400 mr-2" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                    </svg>
                    <p style="color: var(--linear-accent-red);">{{ $error }}</p>
                </div>
            </div>
        @endif
    </div>
</div>