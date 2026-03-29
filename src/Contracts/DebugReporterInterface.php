<?php

declare(strict_types=1);

namespace Marwa\ErrorHandler\Contracts;

use Throwable;

/**
 * Reports throwables to optional development tooling without coupling the
 * package to any concrete debugbar implementation.
 */
interface DebugReporterInterface
{
    public function report(Throwable $throwable): void;
}
