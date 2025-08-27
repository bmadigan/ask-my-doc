<?php

namespace App\Livewire;

use App\Facades\Overpass;
use App\Models\Chunk;
use App\Models\Document;
use Illuminate\Support\Facades\DB;
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
            DB::beginTransaction();

            // Create document
            $document = Document::create([
                'title' => $this->title,
                'bytes' => strlen($this->content),
                'original_filename' => $this->file ? $this->file->getClientOriginalName() : null,
            ]);

            // Chunk the content
            $chunks = $this->createChunks($this->content);
            $this->chunkCount = count($chunks);

            // Process each chunk
            foreach ($chunks as $index => $chunkText) {
                // Generate embedding
                $embedding = Overpass::generateEmbedding($chunkText);

                // Store chunk
                Chunk::create([
                    'document_id' => $document->id,
                    'chunk_index' => $index,
                    'content' => $chunkText,
                    'embedding_json' => json_encode($embedding),
                    'token_count' => $this->estimateTokens($chunkText),
                ]);
            }

            DB::commit();

            $this->processingTime = round(microtime(true) - $startTime, 2);
            $this->success = true;
            $this->dispatch('document-ingested', documentId: $document->id);

            // Reset form
            $this->reset(['title', 'content', 'file']);

        } catch (\Exception $e) {
            DB::rollBack();
            $this->error = 'Processing failed: '.$e->getMessage();
        } finally {
            $this->processing = false;
        }
    }

    protected function createChunks($text)
    {
        $chunks = [];
        $words = preg_split('/\s+/', $text);
        $currentChunk = [];
        $currentLength = 0;

        foreach ($words as $word) {
            $wordLength = strlen($word) + 1; // +1 for space

            if ($currentLength + $wordLength > $this->chunkSize && ! empty($currentChunk)) {
                // Save current chunk
                $chunks[] = implode(' ', $currentChunk);

                // Start new chunk with overlap
                $overlapWords = [];
                $overlapLength = 0;
                for ($i = count($currentChunk) - 1; $i >= 0; $i--) {
                    $overlapWordLength = strlen($currentChunk[$i]) + 1;
                    if ($overlapLength + $overlapWordLength > $this->overlapSize) {
                        break;
                    }
                    array_unshift($overlapWords, $currentChunk[$i]);
                    $overlapLength += $overlapWordLength;
                }

                $currentChunk = $overlapWords;
                $currentLength = $overlapLength;
            }

            $currentChunk[] = $word;
            $currentLength += $wordLength;
        }

        if (! empty($currentChunk)) {
            $chunks[] = implode(' ', $currentChunk);
        }

        return $chunks;
    }

    protected function estimateTokens($text)
    {
        // Rough estimation: 1 token per 4 characters
        return (int) ceil(strlen($text) / 4);
    }

    public function render()
    {
        return view('livewire.ingest-document');
    }
}
