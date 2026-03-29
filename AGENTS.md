# Repository Guidelines

## Project Structure & Module Organization
This package is a small PHP library. Core code lives in `src/`, with the main entry point at `src/ErrorHandler.php`. Shared contracts and support classes are grouped under `src/Contracts/` and `src/Support/`. Tests live in `tests/`, currently centered on `tests/ErrorHandlerTest.php`. Runnable usage samples are kept in `example/`. Composer metadata is defined in `composer.json`, and PHPUnit is configured through `phpunit.xml.dist`.

## Build, Test, and Development Commands
Use Composer for local setup and test execution:

- `composer install`: install runtime and dev dependencies.
- `composer test`: run the PHPUnit suite via the Composer script.
- `vendor/bin/phpunit`: run PHPUnit directly with the repo config.

For a quick manual check, run one of the examples, for example `php example/01_example.php`.

## Coding Style & Naming Conventions
Target PHP `>=8.1` and keep `declare(strict_types=1);` at the top of PHP files. Follow the existing style: 4-space indentation, braces on the next line for classes and methods, typed properties, and explicit return types. Use PSR-4 namespacing under `Marwa\\ErrorHandler\\`. Class names use `StudlyCase`; methods use `camelCase`; test methods follow the `test...` pattern, such as `testBootstrapWithoutDeps`.

No formatter or linter is configured in this repository, so match the surrounding code closely and keep diffs minimal.

## Testing Guidelines
Tests use PHPUnit 10 and load through `vendor/autoload.php`. Add or update tests in `tests/` whenever library behavior changes, especially around bootstrapping, logger/debugbar integration, and fallback rendering. Prefer focused unit tests with clear method names. Run `composer test` before opening a PR; the current baseline is a small fast suite, so regressions should be caught there first.

## Commit & Pull Request Guidelines
Recent commits use short, imperative subjects such as `Update DX Function` and `Fixed Debugbar Issues`. Keep commit titles concise, capitalized, and focused on one change. For pull requests, include a short summary, note any behavior changes, list the tests you ran, and reference related issues if applicable. If output or rendered error pages change, include a screenshot or terminal sample.

## Security & Configuration Tips
This library handles exceptions and may render error output. Avoid committing app-specific secrets or environment values in examples and tests. Keep production examples generic, and prefer mock loggers/debugbar integrations in tests rather than real services.
