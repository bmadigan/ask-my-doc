<div class="linear-card" style="background: var(--linear-bg-secondary); border: 1px solid var(--linear-border); border-radius: 8px; padding: 1.5rem;">
    <div class="flex items-center justify-between mb-4">
        <h3 class="text-base font-medium flex items-center gap-2" style="color: var(--linear-text-primary);">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
            </svg>
            Overpass Status
        </h3>
        <button 
            wire:click="checkStatus"
            class="linear-button-secondary" 
            style="padding: 0.375rem 0.75rem; font-size: 13px;"
            :disabled="$wire.testing"
        >
            <span wire:loading.remove wire:target="checkStatus">Test Connection</span>
            <span wire:loading wire:target="checkStatus">Testing...</span>
        </button>
    </div>

    @if ($status)
        <div class="space-y-3">
            {{-- Overall Status --}}
            <div class="flex items-center gap-3">
                <div class="flex-shrink-0">
                    @if ($status['success'] ?? false)
                        <div class="w-3 h-3 bg-green-500 rounded-full animate-pulse"></div>
                    @else
                        <div class="w-3 h-3 bg-red-500 rounded-full"></div>
                    @endif
                </div>
                <div class="flex-1">
                    <p class="text-sm font-medium" style="color: {{ ($status['success'] ?? false) ? 'var(--linear-accent-green)' : 'var(--linear-accent-red)' }};">
                        {{ $status['message'] ?? 'Unknown status' }}
                    </p>
                </div>
            </div>

            {{-- Component Status --}}
            @if ($status['success'] ?? false)
                <div class="grid grid-cols-2 gap-2 text-xs">
                    <div class="flex items-center gap-2">
                        <span style="color: var(--linear-text-tertiary);">OpenAI:</span>
                        <span class="font-medium" style="color: {{ ($status['openai'] ?? '') === 'connected' ? 'var(--linear-accent-green)' : 'var(--linear-accent-red)' }};">
                            {{ ucfirst($status['openai'] ?? 'unknown') }}
                        </span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span style="color: var(--linear-text-tertiary);">Python:</span>
                        <span class="font-medium" style="color: {{ ($status['python_bridge'] ?? '') === 'connected' ? 'var(--linear-accent-green)' : 'var(--linear-accent-red)' }};">
                            {{ ucfirst($status['python_bridge'] ?? 'unknown') }}
                        </span>
                    </div>
                </div>
            @endif

            {{-- Last Checked --}}
            @if ($lastChecked)
                <div class="text-xs pt-2" style="color: var(--linear-text-tertiary); border-top: 1px solid var(--linear-border);">
                    Last checked: {{ $lastChecked }}
                </div>
            @endif
        </div>
    @else
        <div class="text-sm" style="color: var(--linear-text-tertiary);">
            Click "Test Connection" to check status
        </div>
    @endif
</div>