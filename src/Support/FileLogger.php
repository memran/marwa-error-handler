<?php

declare(strict_types=1);

namespace Marwa\ErrorHandler\Support;

use Psr\Log\AbstractLogger;
use Stringable;

final class FileLogger extends AbstractLogger
{
    public function __construct(private string $path)
    {
    }

    /**
     * @param mixed $level
     * @param string|Stringable $message
     * @param array<string, mixed> $context
     */
    public function log($level, Stringable|string $message, array $context = []): void
    {
        $directory = dirname($this->path);

        if ($directory !== '' && $directory !== '.' && !is_dir($directory)) {
            @mkdir($directory, 0755, true);
        }

        $entry = [
            'timestamp' => gmdate('c'),
            'level' => $this->normalizeLevel($level),
            'message' => (string) $message,
            'context' => $context,
        ];

        @error_log((string) json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL, 3, $this->path);
    }

    private function normalizeLevel(mixed $level): string
    {
        if (is_string($level)) {
            return $level;
        }

        if ($level instanceof Stringable) {
            return (string) $level;
        }

        return 'unknown';
    }
}
