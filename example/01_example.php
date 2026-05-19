<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Marwa\ErrorHandler\ErrorHandler;

ErrorHandler::bootstrap(
    appName: 'MyApp',
    env: 'production',      // PROD: no details rendered
    logFile: __DIR__ . '/../storage/error.log',
    debugbar: null,
);


// Trigger an exception to see the generic UI
throw new RuntimeException('Production example: something failed');
