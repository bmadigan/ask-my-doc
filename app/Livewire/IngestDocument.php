<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Actions\Document\IngestDocumentAction;
use Bmadigan\Overpass\Services\PythonAiBridge;
use Livewire\Component;
use Livewire\WithFileUploads;

class IngestDocument extends Component
{
    use WithFileUploads;

    public string $title = '';

    public string $content = '';

    public mixed $file = null;

    public int $chunkSize = 1000;

    public int $overlapSize = 200;

    public bool $processing = false;

    public int $chunkCount = 0;

    public float $processingTime = 0;

    public ?string $error = null;

    public bool $success = false;

    protected $rules = [
        'title' => 'required|min:3',
        'content' => 'required_without:file|min:10',
        'file' => 'nullable|file|mimes:txt,md|max:10240',
    ];

    public function mount(): void
    {
        $this->reset();
    }

    public function updatedFile(): void
    {
        if ($this->file) {
            $this->content = file_get_contents($this->file->getRealPath());
        }
    }

    public function process(): void
    {
        $this->validate();
        $this->processing = true;
        $this->error = null;
        $this->success = false;

        $startTime = microtime(true);

        try {
            $overpass = app(PythonAiBridge::class);

            $document = IngestDocumentAction::run([
                'title' => $this->title,
                'content' => $this->content,
                'original_filename' => $this->file ? $this->file->getClientOriginalName() : null,
                'chunk_size' => $this->chunkSize,
                'overlap_size' => $this->overlapSize,
            ], $overpass);

            $this->chunkCount = $document->chunks()->count();
            $this->processingTime = round(microtime(true) - $startTime, 2);
            $this->success = true;
            $this->dispatch('document-ingested', documentId: $document->id);

            // Reset form
            $this->reset(['title', 'content', 'file']);

        } catch (\Exception $e) {
            $this->error = 'Processing failed: '.$e->getMessage();
        } finally {
            $this->processing = false;
        }
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        return view('livewire.ingest-document');
    }
}
