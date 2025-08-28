<?php

declare(strict_types=1);

namespace App\Actions\Query;

use App\Models\Chunk;
use App\Models\Document;
use App\Models\Query;
use Bmadigan\Overpass\Services\PythonAiBridge;
use Illuminate\Support\Collection;

class AskQuestionAction
{
    public static function run(array $data, PythonAiBridge $overpass): array
    {
        $startTime = microtime(true);

        // Validate document exists and has chunks
        $document = Document::findOrFail($data['document_id']);
        if ($document->chunks()->count() === 0) {
            throw new \Exception('Document has no chunks available for searching');
        }

        // For simplicity, we'll do a direct database query with scoring
        // In production, this would use the Python bridge for vector similarity
        $chunks = Chunk::where('document_id', $data['document_id'])->get();

        if ($chunks->isEmpty()) {
            $searchResults = [];
        } else {
            // Generate embedding for the question
            $embeddingResult = $overpass->generateEmbedding($data['question']);
            $questionEmbedding = $embeddingResult['embedding'];

            // Calculate similarity scores for each chunk
            $searchResults = [];
            foreach ($chunks as $chunk) {
                $chunkEmbedding = json_decode($chunk->embedding_json, true);
                if ($chunkEmbedding) {
                    // Simple dot product similarity
                    $score = self::cosineSimilarity($questionEmbedding, $chunkEmbedding);
                    if ($score >= ($data['min_score'] ?? 0.5)) {
                        $searchResults[] = [
                            'chunk_id' => $chunk->id,
                            'score' => $score,
                        ];
                    }
                }
            }

            // Sort by score and limit to top_k
            usort($searchResults, fn ($a, $b) => $b['score'] <=> $a['score']);
            $searchResults = array_slice($searchResults, 0, $data['top_k'] ?? 5);
        }

        // Get chunks from search results
        $relevantChunks = self::getRelevantChunks($searchResults, $data['min_score'] ?? 0.5);

        if ($relevantChunks->isEmpty()) {
            return [
                'answer' => null,
                'relevant_chunks' => [],
                'error' => 'No relevant chunks found for your question.',
                'query' => null,
            ];
        }

        // Generate answer using GPT
        $answer = self::generateAnswer($data['question'], $relevantChunks, $overpass);

        // Log the query
        $latencyMs = (int) ((microtime(true) - $startTime) * 1000);
        $query = Query::create([
            'document_id' => $document->id,
            'question' => $data['question'],
            'top_k_returned' => $relevantChunks->count(),
            'latency_ms' => $latencyMs,
        ]);

        return [
            'answer' => $answer,
            'relevant_chunks' => $relevantChunks->map(function ($chunk) {
                return [
                    'id' => $chunk->id,
                    'content' => $chunk->content,
                    'score' => $chunk->score,
                    'score_percentage' => round($chunk->score * 100, 2),
                ];
            })->toArray(),
            'query' => $query,
        ];
    }

    protected static function getRelevantChunks(array $searchResults, float $minScore): Collection
    {
        if (empty($searchResults)) {
            return collect();
        }

        // Get chunk IDs and scores
        $chunkData = collect($searchResults)
            ->filter(fn ($result) => $result['score'] >= $minScore)
            ->keyBy('chunk_id');

        if ($chunkData->isEmpty()) {
            return collect();
        }

        // Fetch chunks from database
        $chunks = Chunk::whereIn('id', $chunkData->keys())->get();

        // Attach scores to chunks
        return $chunks->map(function ($chunk) use ($chunkData) {
            $chunk->score = $chunkData[$chunk->id]['score'];

            return $chunk;
        })->sortByDesc('score');
    }

    protected static function generateAnswer(string $question, Collection $chunks, PythonAiBridge $overpass): string
    {
        // Prepare context from chunks
        $context = $chunks->map(function ($chunk, $index) {
            return sprintf('[%d] %s', $index + 1, $chunk->content);
        })->implode("\n\n");

        // Create messages for GPT
        $messages = [
            [
                'role' => 'system',
                'content' => 'You are a helpful assistant that answers questions based on the provided context. Always cite the source numbers [1], [2], etc. when referencing information from the context. If the context doesn\'t contain enough information to answer the question, say so.',
            ],
            [
                'role' => 'user',
                'content' => sprintf(
                    "Context:\n%s\n\nQuestion: %s\n\nPlease provide a comprehensive answer based on the context above, citing sources.",
                    $context,
                    $question
                ),
            ],
        ];

        $chatData = [
            'message' => $question,
            'messages' => $messages,
            'session_id' => uniqid('query_'),
        ];
        $result = $overpass->chat($chatData);

        return $result['response'];
    }

    protected static function cosineSimilarity(array $vec1, array $vec2): float
    {
        $dotProduct = 0.0;
        $norm1 = 0.0;
        $norm2 = 0.0;

        for ($i = 0; $i < count($vec1); $i++) {
            $dotProduct += $vec1[$i] * $vec2[$i];
            $norm1 += $vec1[$i] * $vec1[$i];
            $norm2 += $vec2[$i] * $vec2[$i];
        }

        $norm1 = sqrt($norm1);
        $norm2 = sqrt($norm2);

        if ($norm1 == 0.0 || $norm2 == 0.0) {
            return 0.0;
        }

        return $dotProduct / ($norm1 * $norm2);
    }
}
