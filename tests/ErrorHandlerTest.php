<?php
declare(strict_types=1);

use Marwa\ErrorHandler\ErrorHandler;
use Marwa\ErrorHandler\Support\FallbackRenderer;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class ErrorHandlerTest extends TestCase
{
    public function testBootstrapWithoutDeps(): void
    {
        $handler = ErrorHandler::bootstrap(
            appName: 'TestApp',
            env: 'production'
            // no logger, no debugbar, default renderer
        );

        $this->assertInstanceOf(ErrorHandler::class, $handler);
        $this->assertNull($handler->getLogger());
    }

    public function testInjectLoggerLater(): void
    {
        $handler = new ErrorHandler(appName: 'TestApp', env: 'development');
        $this->assertNull($handler->getLogger());

        $logger = $this->getMockBuilder(LoggerInterface::class)
            ->onlyMethods([])
            ->getMock();
        $handler->setLogger($logger);

        $this->assertSame($logger, $handler->getLogger());
    }

    public function testCustomRendererInjection(): void
    {
        $renderer = new FallbackRenderer();
        $handler  = new ErrorHandler(appName: 'TestApp', env: 'production', logger: null, debugbar: null, renderer: $renderer);

        // Should register without throwing
        $handler->register();

        $this->assertTrue(true); // minimal assertion to pass
    }
}
