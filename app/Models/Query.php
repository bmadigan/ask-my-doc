<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Query extends Model
{
    protected $fillable = [
        'document_id',
        'question',
        'top_k_returned',
        'latency_ms',
    ];

    protected $casts = [
        'top_k_returned' => 'integer',
        'latency_ms' => 'integer',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
