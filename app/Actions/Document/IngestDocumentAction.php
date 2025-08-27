<?php

namespace App\Actions\Document;

use App\Models\Chunk;
use App\Models\Document;
use Bmadigan\Overpass\Services\PythonAiBridge;
use Illuminate\Support\Facades\DB;

class IngestDocumentAction
{
    protected PythonAiBridge $overpass;

    public function __construct(PythonAiBridge $overpass)
    {
        $this->overpass = $overpass;
    }

    public function execute(array $data): Document
    {
        return DB::transaction(function () use ($data) {
            // Create the document
            $document = Document::create([
                'title' => $data['title'],
                'bytes' => strlen($data['content']),
                'original_filename' => $data['original_filename'] ?? null,
            ]);

            // Chunk the content
            $chunks = $this->chunkContent(
                $data['content'],
                $data['chunk_size'] ?? 1000,
                $data['overlap_size'] ?? 200
            );

            // Generate embeddings and save chunks
            foreach ($chunks as $index => $chunkContent) {
                $embeddingResult = $this->overpass->generateEmbedding($chunkContent);
                $embedding = $embeddingResult['embedding'];

                Chunk::create([
                    'document_id' => $document->id,
                    'chunk_index' => $index,
                    'content' => $chunkContent,
                    'embedding_json' => json_encode($embedding),
                    'token_count' => $this->estimateTokenCount($chunkContent),
                ]);
            }

            return $document->fresh();
        });
    }

    protected function chunkContent(string $content, int $chunkSize, int $overlapSize): array
    {
        $chunks = [];
        $contentLength = strlen($content);
        $position = 0;
        $previousPosition = -1;

        while ($position < $contentLength) {
            // Prevent infinite loop
            if ($position === $previousPosition) {
                $position += $chunkSize;

                continue;
            }
            $previousPosition = $position;

            $chunk = substr($content, $position, $chunkSize);

            // Try to break at sentence boundary if possible
            if ($position + $chunkSize < $contentLength) {
                $lastPeriod = strrpos($chunk, '. ');
                if ($lastPeriod !== false && $lastPeriod > $chunkSize * 0.5) {
                    $chunk = substr($chunk, 0, $lastPeriod + 1);
                }
            }

            $trimmedChunk = trim($chunk);
            if (! empty($trimmedChunk)) {
                $chunks[] = $trimmedChunk;
            }

            // Move position forward
            $actualChunkLength = strlen($chunk);
            if ($actualChunkLength > $overlapSize) {
                $position += $actualChunkLength - $overlapSize;
            } else {
                $position += $actualChunkLength;
            }
        }

        return $chunks;
    }

    protected function estimateTokenCount(string $text): int
    {
        // Rough estimation: ~1 token per 4 characters
        return (int) ceil(strlen($text) / 4);
    }
}
