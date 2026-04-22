<?php

declare(strict_types=1);

use Marwa\ErrorHandler\Contracts\RendererInterface;
use Marwa\ErrorHandler\ErrorHandler;
use Marwa\ErrorHandler\Support\FallbackRenderer;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;

final class ErrorHandlerTest extends TestCase
{
    public function testBootstrapLogsBootEvent(): void
    {
        $logger = new ArrayLogger();

        ErrorHandler::bootstrap(
            appName: 'TestApp',
            env: 'production',
            logger: $logger,
        );

        $this->assertSame('info', $logger->records[0]['level']);
        $this->assertSame('error_handler_booted', $logger->records[0]['message']);
        $this->assertSame('TestApp', $logger->records[0]['context']['app']);
    }

    public function testPhpErrorLoggingUsesNamedErrnoContext(): void
    {
        $logger = new ArrayLogger();
        $handler = new ErrorHandler(
            appName: 'TestApp',
            env: 'production',
            logger: $logger,
        );

        $handler->register();

        $registeredHandler = set_error_handler(static fn (): bool => true);
        restore_error_handler();

        $this->assertIsCallable($registeredHandler);
        $this->assertTrue($registeredHandler(E_USER_NOTICE, 'notice message', __FILE__, __LINE__));
        $this->assertSame('php_error', $logger->records[1]['message']);
        $this->assertSame('E_USER_NOTICE', $logger->records[1]['context']['errno']);
    }

    public function testMaskedPhpErrorsFallBackToNativeHandler(): void
    {
        $handler = new ErrorHandler(appName: 'TestApp', env: 'production');
        $handler->register();

        $registeredHandler = set_error_handler(static fn (): bool => true);
        restore_error_handler();

        $this->assertIsCallable($registeredHandler);

        $previousReporting = error_reporting(0);

        try {
            $this->assertFalse($registeredHandler(E_USER_NOTICE, 'suppressed'));
        } finally {
            error_reporting($previousReporting);
        }
    }

    public function testExceptionHandlerReportsAndRendersCliOutput(): void
    {
        $renderer = new SpyRenderer();
        $reported = null;
        $handler = new ErrorHandler(
            appName: 'TestApp',
            env: 'development',
            logger: null,
            debugbar: static function (Throwable $throwable) use (&$reported): void {
                $reported = $throwable;
            },
            renderer: $renderer,
        );

        $handler->register();

        $registeredHandler = set_exception_handler(
            static function (Throwable $throwable): void {
                throw $throwable;
            },
        );
        restore_exception_handler();

        $this->assertIsCallable($registeredHandler);

        $exception = new RuntimeException('Boom');
        $registeredHandler($exception);

        $this->assertSame($exception, $reported);
        $this->assertSame('cli', $renderer->lastMethod);
        $this->assertTrue($renderer->lastDev);
        $this->assertSame('TestApp', $renderer->lastAppName);
        $this->assertSame($exception, $renderer->lastThrowable);
    }

    public function testLoggerFailureDoesNotBlockRendering(): void
    {
        $renderer = new SpyRenderer();
        $handler = new ErrorHandler(
            appName: 'TestApp',
            env: 'development',
            logger: new ThrowingLogger(),
            renderer: $renderer,
        );

        $handler->register();

        $registeredHandler = set_exception_handler(
            static function (Throwable $throwable): void {
                throw $throwable;
            },
        );
        restore_exception_handler();

        $this->assertIsCallable($registeredHandler);

        $registeredHandler(new RuntimeException('Renderer should still run'));

        $this->assertSame('cli', $renderer->lastMethod);
        $this->assertInstanceOf(RuntimeException::class, $renderer->lastThrowable);
    }

    public function testFallbackRendererEscapesDynamicValues(): void
    {
        $_SERVER['HTTP_X_REQUEST_ID'] = 'trace-123';

        $renderer = new FallbackRenderer();

        ob_start();
        $renderer->renderException(
            new RuntimeException('<script>alert("x")</script>'),
            '<b>UnsafeApp</b>',
            true,
        );
        $output = (string) ob_get_clean();

        unset($_SERVER['HTTP_X_REQUEST_ID']);

        $this->assertStringContainsString('&lt;b&gt;UnsafeApp&lt;/b&gt;', $output);
        $this->assertStringContainsString('&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt;', $output);
        $this->assertStringContainsString('trace-123', $output);
        $this->assertStringNotContainsString('<script>alert("x")</script>', $output);
    }

    public function testFallbackRendererRejectsUnsafeRequestIdHeaders(): void
    {
        $_SERVER['HTTP_X_REQUEST_ID'] = '<script>';

        $renderer = new FallbackRenderer();

        ob_start();
        $renderer->renderGeneric('TestApp');
        $output = (string) ob_get_clean();

        unset($_SERVER['HTTP_X_REQUEST_ID']);

        $this->assertStringNotContainsString('<script>', $output);
        $this->assertMatchesRegularExpression('/Request ID: r-[a-f0-9]{12}/', $output);
    }
}

final class SpyRenderer implements RendererInterface
{
    public string $lastMethod = '';
    public string $lastAppName = '';
    public bool $lastDev = false;
    public ?Throwable $lastThrowable = null;

    public function renderException(Throwable $e, string $appName, bool $dev): void
    {
        $this->lastMethod = 'exception';
        $this->lastThrowable = $e;
        $this->lastAppName = $appName;
        $this->lastDev = $dev;
    }

    public function renderGeneric(string $appName): void
    {
        $this->lastMethod = 'generic';
        $this->lastAppName = $appName;
    }

    public function renderCli(Throwable $e, string $appName, bool $dev): void
    {
        $this->lastMethod = 'cli';
        $this->lastThrowable = $e;
        $this->lastAppName = $appName;
        $this->lastDev = $dev;
    }
}

final class ArrayLogger extends AbstractLogger
{
    /**
     * @var list<array{level:string, message:string, context:array<string, mixed>}>
     */
    public array $records = [];

    /**
     * @param mixed $message
     * @param array<string, mixed> $context
     */
    public function log($level, $message, array $context = []): void
    {
        $this->records[] = [
            'level' => (string) $level,
            'message' => (string) $message,
            'context' => $context,
        ];
    }
}

final class ThrowingLogger extends AbstractLogger
{
    /**
     * @param mixed $level
     * @param mixed $message
     * @param array<string, mixed> $context
     */
    public function log($level, $message, array $context = []): void
    {
        throw new RuntimeException('Logger failure');
    }
}
