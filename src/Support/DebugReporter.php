<?php

declare(strict_types=1);

namespace Marwa\ErrorHandler\Support;

use Marwa\ErrorHandler\Contracts\DebugReporterInterface;
use Throwable;

final class DebugReporter implements DebugReporterInterface
{
    /**
     * @param \Closure(Throwable): void $reporter
     */
    private function __construct(
        private readonly \Closure $reporter,
    ) {
    }

    public static function from(mixed $target): ?DebugReporterInterface
    {
        if ($target === null) {
            return null;
        }

        if ($target instanceof DebugReporterInterface) {
            return $target;
        }

        if (is_callable($target)) {
            /** @var callable(Throwable): void $target */
            return new self(\Closure::fromCallable($target));
        }

        if (!is_object($target)) {
            return null;
        }

        foreach (['addThrowable', 'addException'] as $method) {
            if (method_exists($target, $method)) {
                return new self(
                    static function (Throwable $throwable) use ($target, $method): void {
                        $target->{$method}($throwable);
                    },
                );
            }
        }

        if (method_exists($target, 'addMessage')) {
            return new self(
                static function (Throwable $throwable) use ($target): void {
                    $target->addMessage($throwable->getMessage(), 'exception');
                },
            );
        }

        return null;
    }

    public function report(Throwable $throwable): void
    {
        try {
            ($this->reporter)($throwable);
        } catch (Throwable) {
            // Error handling must not fail because an optional reporter misbehaved.
        }
    }
}
