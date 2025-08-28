<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Document extends Model
{
    protected $casts = [
        'bytes' => 'integer',
    ];

    public function chunks(): HasMany
    {
        return $this->hasMany(Chunk::class)->orderBy('chunk_index');
    }

    public function queries(): HasMany
    {
        return $this->hasMany(Query::class);
    }
}
