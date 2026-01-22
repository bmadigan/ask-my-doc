<div class="max-w-4xl mx-auto p-8">
    <div class="linear-card" style="background: var(--linear-bg-secondary); border: 1px solid var(--linear-border); border-radius: 12px; padding: 2rem;">
        <h2 class="text-2xl font-semibold mb-8" style="color: var(--linear-text-primary); letter-spacing: -0.02em;">Ingest Document</h2>

        <form wire:submit="process" class="space-y-6">
            {{-- Title Input --}}
            <div>
                <label for="title" class="block text-sm font-medium mb-2" style="color: var(--linear-text-secondary);">
                    Document Title
                </label>
                <input
                    type="text"
                    id="title"
                    wire:model.blur="title"
                    class="w-full linear-input" 
                    style="padding: 0.75rem 1rem; font-size: 14px;"
                    placeholder="Enter document title"
                    :disabled="$wire.processing"
                >
                @error('title') 
                    <span class="text-red-500 text-sm mt-1">{{ $message }}</span>
                @enderror
            </div>

            {{-- Content Input --}}
            <div>
                <label for="content" class="block text-sm font-medium mb-2" style="color: var(--linear-text-secondary);">
                    Document Content
                </label>
                <textarea
                    id="content"
                    wire:model.blur="content"
                    rows="10"
                    class="w-full linear-input" 
                    style="padding: 0.75rem 1rem; font-size: 14px; min-height: 240px; resize: vertical;"
                    placeholder="Paste your document content here..."
                    :disabled="$wire.processing"
                ></textarea>
                @error('content') 
                    <span class="text-red-500 text-sm mt-1">{{ $message }}</span>
                @enderror
            </div>

            {{-- File Upload (Optional) --}}
            <div>
                <label for="file" class="block text-sm font-medium mb-2" style="color: var(--linear-text-secondary);">
                    Or Upload File (Optional)
                </label>
                <input 
                    type="file" 
                    id="file" 
                    wire:model="file"
                    accept=".txt,.md"
                    class="w-full linear-input file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-medium" 
                    style="padding: 0.75rem 1rem; file:background: var(--linear-accent-blue); file:color: white;"
                    :disabled="$wire.processing"
                >
                @error('file') 
                    <span class="text-red-500 text-sm mt-1">{{ $message }}</span>
                @enderror
                <p class="text-xs mt-2" style="color: var(--linear-text-tertiary);">Accepts .txt and .md files up to 10MB</p>
            </div>

            {{-- Processing Settings --}}
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label for="chunkSize" class="block text-sm font-medium mb-2" style="color: var(--linear-text-secondary);">
                        Chunk Size (characters)
                    </label>
                    <input
                        type="number"
                        id="chunkSize"
                        wire:model.blur="chunkSize"
                        min="500"
                        max="2000"
                        class="w-full linear-input" 
                        style="padding: 0.75rem 1rem; font-size: 14px;"
                        :disabled="$wire.processing"
                    >
                </div>
                <div>
                    <label for="overlapSize" class="block text-sm font-medium mb-2" style="color: var(--linear-text-secondary);">
                        Overlap Size (characters)
                    </label>
                    <input
                        type="number"
                        id="overlapSize"
                        wire:model.blur="overlapSize"
                        min="0"
                        max="500"
                        class="w-full linear-input" 
                        style="padding: 0.75rem 1rem; font-size: 14px;"
                        :disabled="$wire.processing"
                    >
                </div>
            </div>

            {{-- Submit Button --}}
            <div class="flex items-center justify-between">
                <button 
                    type="submit" 
                    class="linear-button-primary flex items-center gap-2" 
                    style="padding: 0.625rem 1.25rem; font-size: 14px; font-weight: 500;"
                    :disabled="$wire.processing"
                >
                    <span wire:loading.remove wire:target="process">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                        </svg>
                    </span>
                    <span wire:loading wire:target="process">
                        <svg class="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </span>
                    <span wire:loading.remove wire:target="process">Chunk & Embed</span>
                    <span wire:loading wire:target="process">Processing...</span>
                </button>

                @if ($processing)
                    <div class="text-sm text-gray-500 dark:text-gray-400">
                        Processing document...
                    </div>
                @endif
            </div>
        </form>

        {{-- Success Message --}}
        @if ($success)
            <div class="mt-6 p-4 rounded-lg" style="background: rgba(38, 184, 134, 0.1); border: 1px solid rgba(38, 184, 134, 0.2);">
                <div class="flex items-center">
                    <svg class="w-5 h-5 text-green-400 mr-2" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                    </svg>
                    <div>
                        <p class="font-medium" style="color: var(--linear-accent-green);">Document ingested successfully!</p>
                        <p class="text-sm mt-1" style="color: var(--linear-text-secondary);">
                            Created {{ $chunkCount }} chunks in {{ $processingTime }} seconds
                        </p>
                    </div>
                </div>
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