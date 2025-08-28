<?php

declare(strict_types=1);

namespace App\Actions\Overpass;

use Bmadigan\Overpass\Services\PythonAiBridge;
use Exception;

class CheckHealthAction
{
    public static function run(PythonAiBridge $overpass): array
    {
        try {
            $result = $overpass->testConnection();

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
