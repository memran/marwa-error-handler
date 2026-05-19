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
     * @param mixed $message
     * @param array<string, mixed> $context
     */
    public function log($level, Stringable|string $message, array $context = []): void
    {
        $directory = dirname($this->path);

        if ($directory !== '' && $directory !== '.' && !is_dir($directory)) {
            @mkdir($directory, 0777, true);
        }

        $entry = [
            'timestamp' => gmdate('c'),
            'level' => (string) $level,
            'message' => (string) $message,
            'context' => $context,
        ];

        @error_log((string) json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL, 3, $this->path);
    }
}
