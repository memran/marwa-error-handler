<?php
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Marwa\ErrorHandler\ErrorHandler;

ErrorHandler::bootstrap(
    appName: 'MyApp',
    env: 'development',    // DEV: pretty exception page when no logger/debugbar
);

// Trigger a notice
echo $undefinedVar;

// Trigger an exception to view the dev UI
throw new InvalidArgumentException('Development example: invalid input given');
