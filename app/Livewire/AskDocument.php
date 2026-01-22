<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Actions\Query\AskQuestionAction;
use App\Models\Document;
use Bmadigan\Overpass\Services\PythonAiBridge;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

class AskDocument extends Component
{
    public ?int $documentId = null;

    public string $question = '';

    // Hidden settings with sensible defaults
    protected int $topK = 5;

    protected float $minScore = 0.3;

    public string $answer = '';

    public array $sources = [];

    #[Locked]
    public bool $processing = false;

    public ?string $error = null;

    public int $latency = 0;

    public bool $showSources = false;

    public function rules(): array
    {
        return [
            'question' => 'required|min:5',
        ];
    }

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

            // Search across all documents (no document_id = cross-document search)
            $result = AskQuestionAction::run([
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
                    'document_title' => $chunk['document_title'] ?? 'Unknown',
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

    #[Computed]
    public function documents(): Collection
    {
        return Document::orderBy('created_at', 'desc')->get();
    }

    public function askSampleQuestion(string $question): void
    {
        $this->question = $question;
        $this->ask();
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        return view('livewire.ask-document');
    }
}
