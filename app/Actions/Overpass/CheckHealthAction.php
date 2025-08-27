<?php

namespace App\Actions\Overpass;

use Bmadigan\Overpass\Services\PythonAiBridge;
use Exception;

class CheckHealthAction
{
    protected PythonAiBridge $overpass;

    public function __construct(PythonAiBridge $overpass)
    {
        $this->overpass = $overpass;
    }

    public function execute(): array
    {
        try {
            $result = $this->overpass->testConnection();

            // Transform the result to match expected format
            return [
                'success' => $result['status'] !== 'error',
                'message' => $result['message'] ?? 'Unknown status',
                'openai' => isset($result['openai']) ? $result['openai'] : 'unknown',
                'python_bridge' => isset($result['python_bridge']) ? $result['python_bridge'] : ($result['status'] !== 'error' ? 'connected' : 'error'),
            ];
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
