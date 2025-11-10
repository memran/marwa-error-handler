<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Marwa\ErrorHandler\ErrorHandler;
use Marwa\DebugBar\Debugbar;

$bar = new Debugbar(true);
//$bar->registerExceptionHandlers();

$bar->collectors()->register(\Marwa\DebugBar\Collectors\TimelineCollector::class);
$bar->collectors()->register(\Marwa\DebugBar\Collectors\MemoryCollector::class);
$bar->collectors()->register(\Marwa\DebugBar\Collectors\PhpCollector::class);
$bar->collectors()->register(\Marwa\DebugBar\Collectors\RequestCollector::class);
$bar->collectors()->register(\Marwa\DebugBar\Collectors\KpiCollector::class);
$bar->collectors()->register(\Marwa\DebugBar\Collectors\ExceptionCollector::class);

ErrorHandler::bootstrap(
      appName: 'MyApp',
      env: 'development',     // PROD: no details rendered
      logger: null,          // no logger injected
      debugbar: null         // debugbar injected
);

// Trigger an exception to see the generic UI
throw new RuntimeException('Production example: something failed');
//$bar->addException(new RuntimeException('Failed Debugbar Exception'));
// In your HTML response:
echo (new \Marwa\DebugBar\Renderer($bar))->render();
