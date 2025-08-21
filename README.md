# memran/marwa-error-handler

A tiny, framework-agnostic **PHP error/exception/shutdown handler** with:

- Optional **PSR-3 Logger** injection (logs if provided; no-op if not)
- Optional **Debugbar** injection (adds exception to bar in dev)
- **Professional fallback UI** when neither logger nor debugbar exists
- Clean **dev vs prod** behavior

## Install

```bash
composer require memran/marwa-error-handler
```

# Example
```bash
use Marwa\ErrorHandler\ErrorHandler;
use Marwa\ErrorHandler\Support\FallbackRenderer;

// (optional) Inject a PSR-3 logger and/or a Debugbar:
$logger   = null; // e.g., Monolog or your own PSR-3 logger
$debugbar = null; // object with addThrowable/addException/addMessage OR a callable(Throwable): void

ErrorHandler::bootstrap(
    appName: 'MyApp',
    env: 'production',    // 'development' or 'production'
    logger: $logger,
    debugbar: $debugbar,
    renderer: new FallbackRenderer() // optional; default will be created
);

```

# Behavior

- **Development** 
    - If a **Debugbar** is present: exception is added to the bar and rethrown so your dev page (Whoops/Symfony) renders.
    - If **no logger & no debugbar**: a polished **dev exception page** is rendered with trimmed trace.
    - If a **logger** is present: php_error, uncaught_exception, and fatal_shutdown entries are written.

- **Production** 
    - If a **logger** is present: errors/exceptions/fatals are logged; the response returns **HTTP 500** without details.
    - If **no logger & no debugbar**: a clean **generic error page** is rendered with only Request ID & timestamp.

# Optional DI

```bash
$handler = new ErrorHandler(appName: 'MyApp', env: 'development');
$handler->setLogger($logger);     // PSR-3
$handler->setDebugbar($debugbar); // object or callable
$handler->setRenderer(new FallbackRenderer());
$handler->register();
```
# Testing

```bash
composer install
composer test
```