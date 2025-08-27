<?php

namespace App\Actions\Overpass;

use App\Services\Overpass;
use Exception;

class CheckHealthAction
{
    protected Overpass $overpass;

    public function __construct(Overpass $overpass)
    {
        $this->overpass = $overpass;
    }

    public function execute(): array
    {
        try {
            return $this->overpass->checkHealth();
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to check status: '.$e->getMessage(),
                'openai' => 'error',
                'python_bridge' => 'error',
            ];
        }
    }
}
