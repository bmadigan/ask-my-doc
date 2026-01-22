<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Actions\Query\AskQuestionAction;
use App\Models\Document;
use Bmadigan\Overpass\Services\PythonAiBridge;
use Livewire\Attributes\On;
use Livewire\Component;

class AskDocument extends Component
{
    public ?int $documentId = null;

    public string $question = '';

    public int $topK = 5;

    public float $minScore = 0.2;

    public string $answer = '';

    public array $sources = [];

    public bool $processing = false;

    public ?string $error = null;

    public int $latency = 0;

    public bool $showSources = false;

    protected $rules = [
        'documentId' => 'required|exists:documents,id',
        'question' => 'required|min:5',
        'topK' => 'required|integer|min:1|max:10',
        'minScore' => 'required|numeric|min:0|max:1',
    ];

    #[On('document-ingested')]
    public function setDocument(int $documentId): void
    {
        $this->documentId = $documentId;
    }

    public function ask(): void
    {
        $this->validate();
        $this->processing = true;
        $this->error = null;
        $this->answer = '';
        $this->sources = [];
        $this->showSources = false;

        try {
            $overpass = app(PythonAiBridge::class);

            $result = AskQuestionAction::run([
                'document_id' => $this->documentId,
                'question' => $this->question,
                'top_k' => $this->topK,
                'min_score' => $this->minScore,
            ], $overpass);

            if (isset($result['error'])) {
                $this->error = $result['error'];

                return;
            }

            $this->answer = $result['answer'];
            $this->sources = collect($result['relevant_chunks'])->map(function ($chunk, $index) {
                return [
                    'index' => $index + 1,
                    'content' => $chunk['content'],
                    'score' => $chunk['score'],
                    'preview' => substr($chunk['content'], 0, 200).(strlen($chunk['content']) > 200 ? '...' : ''),
                ];
            })->toArray();

            if ($result['query']) {
                $this->latency = $result['query']->latency_ms;
            }

        } catch (\Exception $e) {
            $this->error = 'Failed to process question: '.$e->getMessage();
        } finally {
            $this->processing = false;
        }
    }

    public function toggleSources(): void
    {
        $this->showSources = ! $this->showSources;
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        $documents = Document::orderBy('created_at', 'desc')->get();

        return view('livewire.ask-document', [
            'documents' => $documents,
        ]);
    }
}
