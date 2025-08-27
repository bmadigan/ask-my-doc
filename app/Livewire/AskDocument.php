<?php

namespace App\Livewire;

use App\Actions\Query\AskQuestionAction;
use App\Models\Document;
use Livewire\Component;

class AskDocument extends Component
{
    public $documentId = null;

    public $question = '';

    public $topK = 5;

    public $minScore = 0.2;

    public $answer = '';

    public $sources = [];

    public $processing = false;

    public $error = null;

    public $latency = 0;

    public $showSources = false;

    protected $rules = [
        'documentId' => 'required|exists:documents,id',
        'question' => 'required|min:5',
        'topK' => 'required|integer|min:1|max:10',
        'minScore' => 'required|numeric|min:0|max:1',
    ];

    protected $listeners = ['document-ingested' => 'setDocument'];

    public function setDocument($documentId)
    {
        $this->documentId = $documentId;
    }

    public function ask()
    {
        $this->validate();
        $this->processing = true;
        $this->error = null;
        $this->answer = '';
        $this->sources = [];
        $this->showSources = false;

        try {
            $action = app(AskQuestionAction::class);

            $result = $action->execute([
                'document_id' => $this->documentId,
                'question' => $this->question,
                'top_k' => $this->topK,
                'min_score' => $this->minScore,
            ]);

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

    public function toggleSources()
    {
        $this->showSources = ! $this->showSources;
    }

    public function render()
    {
        $documents = Document::orderBy('created_at', 'desc')->get();

        return view('livewire.ask-document', [
            'documents' => $documents,
        ]);
    }
}
