<?php
declare(strict_types=1);

namespace Marwa\ErrorHandler;

use Marwa\ErrorHandler\Contracts\RendererInterface;
use Marwa\ErrorHandler\Support\FallbackRenderer;
use Psr\Log\LoggerInterface;
use Throwable;

final class ErrorHandler
{
    private bool $registered = false;

    public function __construct(
        private string $appName = 'app',
        private string $env = 'production',
        private ?LoggerInterface $logger = null,
        private mixed $debugbar = null,                // object(addThrowable/addException/addMessage) or callable(Throwable): void
        private ?RendererInterface $renderer = null    // DI: any renderer implementing the contract
    ) {}

    public static function bootstrap(
        string $appName = 'app',
        string $env = 'production',
        ?LoggerInterface $logger = null,
        mixed $debugbar = null,
        ?RendererInterface $renderer = null
    ): self {
        $h = new self($appName, $env, $logger, $debugbar, $renderer);
        $h->register();
        return $h;
    }

    public function register(): void
    {
        if ($this->registered) return;

        $dev = $this->isDev();
        $this->renderer ??= new FallbackRenderer(); // default implementation

        @ini_set('display_errors', $dev ? '1' : '0');
        @ini_set('log_errors', '0');
        error_reporting(E_ALL);

        set_error_handler(function (int $errno, string $errstr, ?string $file = null, ?int $line = null): bool {
            if (!(error_reporting() & $errno)) return true;

            $this->logger?->error('php_error', [
                '_origin' => 'system',
                'app'     => $this->appName,
                'env'     => $this->env,
                'errno'   => $this->errnoName($errno),
                'message' => $errstr,
                'file'    => $file,
                'line'    => $line,
            ]);

            return true;
        });

        set_exception_handler(function (Throwable $e) use ($dev): void {
            $this->logger?->critical('uncaught_exception', [
                '_origin' => 'system',
                'app'     => $this->appName,
                'env'     => $this->env,
                'type'    => $e::class,
                'code'    => (int)$e->getCode(),
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
                '_trace'  => $e->getTrace(),
            ]);

            if ($dev && $this->debugbar) {
                $this->sendToDebugbar($e);
                throw $e;
            }

            if (PHP_SAPI !== 'cli' && !$this->logger && !$this->debugbar) {
                $this->renderer->renderException($e, $this->appName, $dev);
                return;
            }

            if (PHP_SAPI !== 'cli') {
                http_response_code(500);
            } else {
                $this->renderer->renderCli($e, $this->appName, $dev);
            }
        });

        register_shutdown_function(function (): void {
            $err = error_get_last();
            if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                if ($this->logger) {
                    $this->logger->alert('fatal_shutdown', [
                        '_origin' => 'system',
                        'app'     => $this->appName,
                        'env'     => $this->env,
                        'errno'   => $this->errnoName($err['type']),
                        'message' => $err['message'] ?? null,
                        'file'    => $err['file'] ?? null,
                        'line'    => $err['line'] ?? null,
                    ]);
                } elseif (PHP_SAPI !== 'cli') {
                    $this->renderer?->renderGeneric($this->appName);
                } else {
                    fwrite(STDERR, "[fatal][{$this->appName}] {$err['message']} @ {$err['file']}:{$err['line']}\n");
                }
            }
        });

        $this->logger?->info('error_handler_booted', [
            '_origin' => 'system',
            'app'     => $this->appName,
            'env'     => $this->env,
            'php'     => PHP_VERSION,
            'sapi'    => PHP_SAPI,
        ]);

        $this->registered = true;
    }

    /* DI helpers */
    public function setLogger(?LoggerInterface $logger): self { $this->logger = $logger; return $this; }
    public function setDebugbar(mixed $debugbar): self { $this->debugbar = $debugbar; return $this; }
    public function setRenderer(?RendererInterface $renderer): self { $this->renderer = $renderer; return $this; }
    public function getLogger(): ?LoggerInterface { return $this->logger; }

    /* Internals */
    private function isDev(): bool
    {
        return in_array(strtolower($this->env), ['local','dev','development','debug'], true);
    }

    private function errnoName(int $errno): string
    {
        return match ($errno) {
            E_ERROR => 'E_ERROR', E_WARNING => 'E_WARNING', E_PARSE => 'E_PARSE',
            E_NOTICE => 'E_NOTICE', E_CORE_ERROR => 'E_CORE_ERROR', E_CORE_WARNING => 'E_CORE_WARNING',
            E_COMPILE_ERROR => 'E_COMPILE_ERROR', E_COMPILE_WARNING => 'E_COMPILE_WARNING',
            E_USER_ERROR => 'E_USER_ERROR', E_USER_WARNING => 'E_USER_WARNING', E_USER_NOTICE => 'E_USER_NOTICE',
            E_STRICT => 'E_STRICT', E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
            E_DEPRECATED => 'E_DEPRECATED', E_USER_DEPRECATED => 'E_USER_DEPRECATED',
            default => 'E_UNKNOWN',
        };
    }

    private function sendToDebugbar(Throwable $e): void
    {
        if (!$this->debugbar) return;

        if (is_callable($this->debugbar)) {
            try { ($this->debugbar)($e); } catch (Throwable) {}
            return;
        }
        if (is_object($this->debugbar)) {
            foreach (['addThrowable','addException','addMessage'] as $m) {
                if (method_exists($this->debugbar, $m)) {
                    try {
                        $m === 'addMessage' ? $this->debugbar->$m($e->getMessage(), 'exception')
                                            : $this->debugbar->$m($e);
                    } catch (Throwable) {}
                    return;
                }
            }
        }
    }
}
