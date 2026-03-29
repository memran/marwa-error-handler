<?php

declare(strict_types=1);

namespace Marwa\ErrorHandler;

use Marwa\ErrorHandler\Contracts\DebugReporterInterface;
use Marwa\ErrorHandler\Contracts\RendererInterface;
use Marwa\ErrorHandler\Support\DebugReporter;
use Marwa\ErrorHandler\Support\FallbackRenderer;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Framework-agnostic error handler with optional logging, reporter integration,
 * and safe fallback rendering for HTTP and CLI runtimes.
 */
final class ErrorHandler
{
    private const DEV_ENVIRONMENTS = ['local', 'dev', 'development', 'debug'];
    private const FATAL_ERROR_TYPES = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR];

    private bool $registered = false;
    private ?DebugReporterInterface $debugReporter;

    public function __construct(
        private string $appName = 'app',
        private string $env = 'production',
        private ?LoggerInterface $logger = null,
        mixed $debugbar = null,
        private ?RendererInterface $renderer = null,
    ) {
        $this->debugReporter = DebugReporter::from($debugbar);
    }

    public static function bootstrap(
        string $appName = 'app',
        string $env = 'production',
        ?LoggerInterface $logger = null,
        mixed $debugbar = null,
        ?RendererInterface $renderer = null,
    ): self {
        $handler = new self($appName, $env, $logger, $debugbar, $renderer);
        $handler->register();

        return $handler;
    }

    /**
     * Registers PHP error, exception, and shutdown handlers once.
     */
    public function register(): void
    {
        if ($this->registered) {
            return;
        }

        $this->renderer ??= new FallbackRenderer();
        $this->configurePhpRuntime();

        set_error_handler($this->handlePhpError(...));
        set_exception_handler($this->handleException(...));
        register_shutdown_function($this->handleShutdown(...));

        $this->safeLog('info', 'error_handler_booted', [
            '_origin' => 'system',
            'app' => $this->appName,
            'env' => $this->env,
            'php' => PHP_VERSION,
            'sapi' => PHP_SAPI,
        ]);

        $this->registered = true;
    }

    public function setLogger(?LoggerInterface $logger): self
    {
        $this->logger = $logger;

        return $this;
    }

    public function setDebugbar(mixed $debugbar): self
    {
        $this->debugReporter = DebugReporter::from($debugbar);

        return $this;
    }

    public function setRenderer(?RendererInterface $renderer): self
    {
        $this->renderer = $renderer;

        return $this;
    }

    public function getLogger(): ?LoggerInterface
    {
        return $this->logger;
    }

    public function isDevelopment(): bool
    {
        return in_array(strtolower($this->env), self::DEV_ENVIRONMENTS, true);
    }

    private function configurePhpRuntime(): void
    {
        $displayErrors = $this->isDevelopment() ? '1' : '0';

        @ini_set('display_errors', $displayErrors);
        @ini_set('log_errors', '0');
        error_reporting(E_ALL);
    }

    private function handlePhpError(int $errno, string $errstr, ?string $file = null, ?int $line = null): bool
    {
        if ((error_reporting() & $errno) === 0) {
            return false;
        }

        $this->safeLog('error', 'php_error', [
            '_origin' => 'system',
            'app' => $this->appName,
            'env' => $this->env,
            'errno' => $this->errnoName($errno),
            'message' => $errstr,
            'file' => $file,
            'line' => $line,
        ]);

        return true;
    }

    private function handleException(Throwable $throwable): void
    {
        $this->safeLog('critical', 'uncaught_exception', [
            '_origin' => 'system',
            'app' => $this->appName,
            'env' => $this->env,
            'type' => $throwable::class,
            'code' => (int) $throwable->getCode(),
            'message' => $throwable->getMessage(),
            'file' => $throwable->getFile(),
            'line' => $throwable->getLine(),
            '_trace' => $throwable->getTrace(),
        ]);

        $this->debugReporter?->report($throwable);

        if (PHP_SAPI === 'cli') {
            $this->renderer?->renderCli($throwable, $this->appName, $this->isDevelopment());

            return;
        }

        $this->renderer?->renderException($throwable, $this->appName, $this->isDevelopment());
    }

    private function handleShutdown(): void
    {
        $error = error_get_last();

        if ($error === null || !in_array($error['type'], self::FATAL_ERROR_TYPES, true)) {
            return;
        }

        $this->safeLog('alert', 'fatal_shutdown', [
            '_origin' => 'system',
            'app' => $this->appName,
            'env' => $this->env,
            'errno' => $this->errnoName($error['type']),
            'message' => $error['message'],
            'file' => $error['file'],
            'line' => $error['line'],
        ]);

        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, sprintf(
                "[fatal][%s] %s @ %s:%s\n",
                $this->appName,
                $error['message'],
                $error['file'],
                (string) $error['line'],
            ));

            return;
        }

        $this->renderer?->renderGeneric($this->appName);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function safeLog(string $level, string $message, array $context = []): void
    {
        if ($this->logger === null) {
            return;
        }

        try {
            $this->logger->log($level, $message, $context);
        } catch (Throwable) {
            // Logging failures must not cascade during exception handling.
        }
    }

    private function errnoName(int $errno): string
    {
        return match ($errno) {
            E_ERROR => 'E_ERROR',
            E_WARNING => 'E_WARNING',
            E_PARSE => 'E_PARSE',
            E_NOTICE => 'E_NOTICE',
            E_CORE_ERROR => 'E_CORE_ERROR',
            E_CORE_WARNING => 'E_CORE_WARNING',
            E_COMPILE_ERROR => 'E_COMPILE_ERROR',
            E_COMPILE_WARNING => 'E_COMPILE_WARNING',
            E_USER_ERROR => 'E_USER_ERROR',
            E_USER_WARNING => 'E_USER_WARNING',
            E_USER_NOTICE => 'E_USER_NOTICE',
            E_STRICT => 'E_STRICT',
            E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
            E_DEPRECATED => 'E_DEPRECATED',
            E_USER_DEPRECATED => 'E_USER_DEPRECATED',
            default => 'E_UNKNOWN',
        };
    }
}
