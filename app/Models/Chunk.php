<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Chunk extends Model
{
    protected $casts = [
        'chunk_index' => 'integer',
        'token_count' => 'integer',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function getEmbeddingAttribute(): array
    {
        return json_decode($this->embedding_json, true) ?? [];
    }

    public function setEmbeddingAttribute(array $value): void
    {
        $this->attributes['embedding_json'] = json_encode($value);
    }
}
