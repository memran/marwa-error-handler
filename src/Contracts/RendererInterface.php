<?php
declare(strict_types=1);

namespace Marwa\ErrorHandler\Contracts;

use Throwable;

/**
 * Contract for rendering exception/error output in different runtimes.
 *
 * Implementations should:
 * - Avoid leaking sensitive data in production (when $dev === false).
 * - Be safe to call after headers may have been sent (gracefully no-op or echo).
 * - Handle both web (HTML/JSON) and CLI output paths.
 */
interface RendererInterface
{
    /**
     * Render an exception response for web/HTTP contexts.
     *
     * In development ($dev === true), implementations may include exception
     * details and a trimmed stack trace. In production, they must render
     * a generic, non-sensitive error page.
     */
    public function renderException(Throwable $e, string $appName, bool $dev): void;

    /**
     * Render a generic error page (no exception object available).
     * Used typically for fatal shutdowns where only last error info exists.
     */
    public function renderGeneric(string $appName): void;

    /**
     * Render an exception for CLI contexts.
     * In development, include a concise stack trace. In production, keep it minimal.
     */
    public function renderCli(Throwable $e, string $appName, bool $dev): void;
}
