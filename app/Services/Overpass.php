<?php

namespace App\Services;

use Exception;
use OpenAI;
use Symfony\Component\Process\Process;

class Overpass
{
    protected $client;

    protected $scriptPath;

    protected $timeout;

    public function __construct()
    {
        $this->client = OpenAI::client(config('services.openai.key', env('OPENAI_API_KEY')));
        $this->scriptPath = config('overpass.script_path', env('OVERPASS_SCRIPT_PATH', base_path('overpass-ai/main.py')));
        $this->timeout = config('overpass.timeout', env('OVERPASS_TIMEOUT', 60));
    }

    public function testConnection(): array
    {
        try {
            // Test OpenAI connection
            $response = $this->client->models()->list();

            // Test Python bridge
            $pythonTest = $this->execute('health_check', []);

            return [
                'success' => true,
                'openai' => 'connected',
                'python_bridge' => $pythonTest['success'] ?? false ? 'connected' : 'error',
                'message' => 'All systems operational',
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Connection failed: '.$e->getMessage(),
            ];
        }
    }

    public function generateEmbedding(string $text, string $model = 'text-embedding-3-small'): array
    {
        try {
            $response = $this->client->embeddings()->create([
                'model' => $model,
                'input' => $text,
            ]);

            return $response->embeddings[0]->embedding;
        } catch (Exception $e) {
            throw new Exception('Failed to generate embedding: '.$e->getMessage());
        }
    }

    public function chat(array $messages, string $model = 'gpt-4o-mini'): string
    {
        try {
            $response = $this->client->chat()->create([
                'model' => $model,
                'messages' => $messages,
                'temperature' => 0.3,
            ]);

            return $response->choices[0]->message->content;
        } catch (Exception $e) {
            throw new Exception('Chat operation failed: '.$e->getMessage());
        }
    }

    public function execute(string $operation, array $payload): array
    {
        try {
            if (! file_exists($this->scriptPath)) {
                throw new Exception("Python script not found at: {$this->scriptPath}");
            }

            $input = json_encode([
                'operation' => $operation,
                'payload' => $payload,
            ]);

            // Use full path to python3 to avoid PATH issues
            $pythonPaths = [
                '/opt/anaconda3/bin/python3',
                '/usr/local/bin/python3',
                '/usr/bin/python3',
            ];

            $pythonPath = 'python3'; // default fallback
            foreach ($pythonPaths as $path) {
                if (file_exists($path)) {
                    $pythonPath = $path;
                    break;
                }
            }

            $process = new Process([
                $pythonPath,
                $this->scriptPath,
                $input,
            ]);

            // Pass environment variables to Python script while preserving system env
            $env = array_merge($_ENV ?? [], [
                'OPENAI_API_KEY' => env('OPENAI_API_KEY'),
                'PATH' => getenv('PATH') ?: '/usr/bin:/bin:/usr/local/bin:/opt/anaconda3/bin',
            ]);
            $process->setEnv($env);

            $process->setTimeout($this->timeout);
            $process->run();

            if (! $process->isSuccessful()) {
                $error = $process->getErrorOutput();
                $output = $process->getOutput();
                throw new Exception("Python script failed. Error: {$error} Output: {$output}");
            }

            $output = $process->getOutput();
            $result = json_decode($output, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Invalid JSON response from Python script: '.$output);
            }

            return $result;
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function vectorSearch(array $vectors, array $query, int $k = 5): array
    {
        return $this->execute('vector_search', [
            'vectors' => $vectors,
            'query' => $query,
            'k' => $k,
        ]);
    }

    public function checkHealth(): array
    {
        $status = [
            'success' => false,
            'message' => 'Checking health...',
            'openai' => 'unknown',
            'python_bridge' => 'unknown',
        ];

        // Check OpenAI connection
        try {
            $embedding = $this->generateEmbedding('test');
            if (is_array($embedding) && count($embedding) > 0) {
                $status['openai'] = 'connected';
            } else {
                $status['openai'] = 'error';
            }
        } catch (Exception $e) {
            $status['openai'] = 'error';
        }

        // Check Python bridge
        try {
            $result = $this->executePython('health_check', []);
            if (isset($result['success']) && $result['success']) {
                $status['python_bridge'] = 'connected';
            } else {
                $status['python_bridge'] = 'error';
            }
        } catch (Exception $e) {
            $status['python_bridge'] = 'error';
        }

        // Determine overall status
        if ($status['openai'] === 'connected' && $status['python_bridge'] === 'connected') {
            $status['success'] = true;
            $status['message'] = 'All systems operational';
        } elseif ($status['openai'] === 'connected' || $status['python_bridge'] === 'connected') {
            $status['success'] = true;
            $status['message'] = 'Partial connectivity';
        } else {
            $status['success'] = false;
            $status['message'] = 'Connection failed';
        }

        return $status;
    }

    public function executePython(string $operation, array $payload): array
    {
        return $this->execute($operation, $payload);
    }

    public function validateEmbedding(array $embedding): void
    {
        if (count($embedding) !== 1536) {
            throw new \InvalidArgumentException('Embedding must have exactly 1536 dimensions');
        }
    }
}
