<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Marwa\DebugBar\Debugbar;
use Marwa\ErrorHandler\ErrorHandler;

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
    env: 'development',
    logger: null,
    debugbar: $bar,
);

throw new RuntimeException('Development example: exception reported to the debugbar and renderer');
