<?php

namespace Nreljed\Platform\Services;

use JsonException;

class DeployService
{
    private string $lockFile;

    public function __construct()
    {
        $this->lockFile = storage_path('framework/deploy/lock');
    }

    public function check(): array
    {
        if (! is_file($this->lockFile)) {
            return [
                'status' => 'idle',
                'tag' => null,
                'output' => [],
                'core' => false,
            ];
        }

        $contents = @file_get_contents($this->lockFile);

        if ($contents === false) {
            return [
                'status' => 'unknown',
                'tag' => null,
                'output' => [],
                'core' => false,
            ];
        }

        try {
            $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            return [
                'status' => 'unknown',
                'tag' => null,
                'output' => [],
                'core' => false,
            ];
        }

        return [
            'status' => is_string($decoded['status'] ?? null) ? $decoded['status'] : 'unknown',
            'tag' => is_string($decoded['tag'] ?? null) ? $decoded['tag'] : null,
            'output' => is_array($decoded['output'] ?? null) ? $decoded['output'] : [],
            'core' => (bool) ($decoded['core'] ?? false),
        ];
    }
}
