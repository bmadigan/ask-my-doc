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

            // Check OpenAI availability from the data array
            $openaiAvailable = isset($result['data']['openai_available']) && $result['data']['openai_available'];
            
            // Transform the result to match expected format
            return [
                'success' => $result['status'] !== 'error',
                'message' => $result['message'] ?? 'Unknown status',
                'openai' => $openaiAvailable ? 'connected' : 'not configured',
                'python_bridge' => $result['status'] !== 'error' ? 'connected' : 'error',
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
