# marwa-error-handler

[![Latest Version](https://img.shields.io/packagist/v/memran/marwa-error-handler.svg)](https://packagist.org/packages/memran/marwa-error-handler)
[![Total Downloads](https://img.shields.io/packagist/dt/memran/marwa-error-handler.svg)](https://packagist.org/packages/memran/marwa-error-handler)
[![License](https://img.shields.io/packagist/l/memran/marwa-error-handler.svg)](LICENSE)
[![PHP Version](https://img.shields.io/packagist/php-v/memran/marwa-error-handler.svg)](https://packagist.org/packages/memran/marwa-error-handler)
[![CI](https://github.com/memran/marwa-error-handler/actions/workflows/ci.yml/badge.svg)](https://github.com/memran/marwa-error-handler/actions/workflows/ci.yml)
[![Coverage](https://img.shields.io/codecov/c/github/memran/marwa-error-handler.svg)](https://codecov.io/gh/memran/marwa-error-handler)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%20max-brightgreen.svg)](https://phpstan.org/)

Production-focused, framework-agnostic PHP error handling with PSR-3 logging, optional debug reporting, and safe fallback rendering for HTTP and CLI applications.

## Requirements

- PHP 8.1 or newer
- Composer
- Optional: a PSR-3 compatible logger such as Monolog

## Installation

```bash
composer require memran/marwa-error-handler
```

## Usage

```php
use Marwa\ErrorHandler\ErrorHandler;

ErrorHandler::bootstrap(
    appName: 'MyApp',
    env: 'production',
    logger: $logger,
    debugbar: $debugReporter, // optional callable/object reporter
);
```

For manual wiring:

```php
$handler = new ErrorHandler(appName: 'MyApp', env: 'development');
$handler->setLogger($logger);
$handler->setDebugbar($debugReporter);
$handler->register();
```

## Configuration

- `appName`: used in logs and fallback pages.
- `env`: `development`, `dev`, `local`, and `debug` enable detailed dev output.
- `logger`: any `Psr\Log\LoggerInterface`; logger failures are safely ignored.
- `debugbar`: optional callable or object with `addThrowable()`, `addException()`, or `addMessage()`.
- `renderer`: optional custom `RendererInterface` implementation for full control over output.

Safe defaults:

- Production never renders exception details in HTML.
- CLI output stays concise in production.
- Request IDs from headers are validated before being echoed.

## Testing

```bash
composer install
composer test
composer test:coverage
```

## Static Analysis

```bash
composer analyse
composer lint
composer fix
```

PHPStan runs at max level against `src/`. PHP-CS-Fixer enforces PSR-12-oriented formatting.

## CI/CD

GitHub Actions runs the package on PHP 8.1, 8.2, and 8.3 using the workflow in `.github/workflows/ci.yml`. The pipeline executes formatting checks, static analysis, and PHPUnit.

## Contributing

Open a focused pull request with a clear summary, test evidence, and notes about any behavior changes. Follow the repository conventions in `AGENTS.md`, keep examples framework-agnostic, and avoid introducing optional integrations as hard runtime dependencies.
