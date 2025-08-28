<?php

declare(strict_types=1);

namespace App\Actions\Document;

use App\Models\Chunk;
use App\Models\Document;
use Bmadigan\Overpass\Services\PythonAiBridge;
use Illuminate\Support\Facades\DB;

class IngestDocumentAction
{
    public static function run(array $data, PythonAiBridge $overpass): Document
    {
        return DB::transaction(function () use ($data, $overpass) {
            // Create the document
            $document = Document::create([
                'title' => $data['title'],
                'bytes' => strlen($data['content']),
                'original_filename' => $data['original_filename'] ?? null,
            ]);

            // Chunk the content
            $chunks = self::chunkContent(
                $data['content'],
                $data['chunk_size'] ?? 1000,
                $data['overlap_size'] ?? 200
            );

            // Generate embeddings and save chunks
            foreach ($chunks as $index => $chunkContent) {
                $embeddingResult = $overpass->generateEmbedding($chunkContent);
                $embedding = $embeddingResult['embedding'];

                Chunk::create([
                    'document_id' => $document->id,
                    'chunk_index' => $index,
                    'content' => $chunkContent,
                    'embedding_json' => json_encode($embedding),
                    'token_count' => self::estimateTokenCount($chunkContent),
                ]);
            }

            return $document->fresh();
        });
    }

    protected static function chunkContent(string $content, int $chunkSize, int $overlapSize): array
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

    protected static function estimateTokenCount(string $text): int
    {
        // Rough estimation: ~1 token per 4 characters
        return (int) ceil(strlen($text) / 4);
    }
}
