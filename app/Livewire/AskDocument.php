<?php

namespace App\Livewire;

use App\Facades\Overpass;
use App\Models\Chunk;
use App\Models\Document;
use App\Models\Query as QueryModel;
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

        $startTime = microtime(true);

        try {
            // Search for relevant chunks
            $searchResult = Overpass::execute('sqlite_search', [
                'db_path' => database_path('database.sqlite'),
                'document_id' => $this->documentId,
                'query' => $this->question,
                'k' => $this->topK,
                'min_score' => $this->minScore,
            ]);

            if (! $searchResult['success']) {
                throw new \Exception($searchResult['error'] ?? 'Search failed');
            }

            $topChunks = $searchResult['data']['results'] ?? [];

            if (empty($topChunks)) {
                $this->answer = "I don't know based on the document.";
                $this->sources = [];
            } else {
                // Get full chunk content
                $chunkIds = array_column($topChunks, 'chunk_id');
                $chunks = Chunk::whereIn('id', $chunkIds)->get();
                
                // Sort chunks to match the order of topChunks
                $chunksById = $chunks->keyBy('id');
                $orderedChunks = collect();
                foreach ($chunkIds as $id) {
                    if ($chunksById->has($id)) {
                        $orderedChunks->push($chunksById->get($id));
                    }
                }
                $chunks = $orderedChunks;

                // Prepare context for chat
                $context = $this->formatContext($chunks, $topChunks);

                // Generate answer
                $messages = [
                    [
                        'role' => 'system',
                        'content' => 'You are a helpful assistant answering ONLY from the provided context. If the context does not contain the answer, say "I don\'t know based on the document." Keep answers concise (3-6 sentences) and cite chunks like [1], [2], etc.',
                    ],
                    [
                        'role' => 'user',
                        'content' => "Context:\n{$context}\n\nQuestion: {$this->question}",
                    ],
                ];

                $this->answer = Overpass::chat($messages);

                // Prepare sources
                $this->sources = [];
                foreach ($chunks as $index => $chunk) {
                    $score = $topChunks[$index]['score'] ?? 0;
                    $this->sources[] = [
                        'index' => $index + 1,
                        'content' => $chunk->content,
                        'score' => round($score, 3),
                        'preview' => substr($chunk->content, 0, 200).(strlen($chunk->content) > 200 ? '...' : ''),
                    ];
                }
            }

            $this->latency = round((microtime(true) - $startTime) * 1000, 2);

            // Log query
            QueryModel::create([
                'document_id' => $this->documentId,
                'question' => $this->question,
                'top_k_returned' => count($this->sources),
                'latency_ms' => $this->latency,
            ]);

        } catch (\Exception $e) {
            $this->error = 'Failed to process question: '.$e->getMessage();
        } finally {
            $this->processing = false;
        }
    }

    protected function formatContext($chunks, $scores)
    {
        $context = '';
        foreach ($chunks as $index => $chunk) {
            $number = $index + 1;
            $context .= "[{$number}] {$chunk->content}\n";
            if ($index < count($chunks) - 1) {
                $context .= "---\n";
            }
        }

        return $context;
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
