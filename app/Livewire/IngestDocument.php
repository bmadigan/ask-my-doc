<?php

namespace App\Livewire;

use App\Actions\Document\IngestDocumentAction;
use Livewire\Component;
use Livewire\WithFileUploads;

class IngestDocument extends Component
{
    use WithFileUploads;

    public $title = '';

    public $content = '';

    public $file = null;

    public $chunkSize = 1000;

    public $overlapSize = 200;

    public $processing = false;

    public $chunkCount = 0;

    public $processingTime = 0;

    public $error = null;

    public $success = false;

    protected $rules = [
        'title' => 'required|min:3',
        'content' => 'required_without:file|min:10',
        'file' => 'nullable|file|mimes:txt,md|max:10240',
    ];

    public function mount()
    {
        $this->reset();
    }

    public function updatedFile()
    {
        if ($this->file) {
            $this->content = file_get_contents($this->file->getRealPath());
        }
    }

    public function process()
    {
        $this->validate();
        $this->processing = true;
        $this->error = null;
        $this->success = false;

        $startTime = microtime(true);

        try {
            $action = app(IngestDocumentAction::class);

            $document = $action->execute([
                'title' => $this->title,
                'content' => $this->content,
                'original_filename' => $this->file ? $this->file->getClientOriginalName() : null,
                'chunk_size' => $this->chunkSize,
                'overlap_size' => $this->overlapSize,
            ]);

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

    public function render()
    {
        return view('livewire.ingest-document');
    }
}
