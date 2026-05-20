# Contributing to Graceful Error Pages

## Requirements

- PHP 8.0+
- Composer 2.x
- Node.js 18+ and npm
- Docker (for wp-env local environment)

## Setup

```bash
git clone git@github.com:codeverbojan/graceful-error-pages.git
cd graceful-error-pages
composer install
npm install
```

## Local Development Environment

The plugin uses [@wordpress/env](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/)
for local development. Docker must be running.

```bash
npm run env:start            # Start WordPress at http://localhost:8888
npm run env:stop             # Stop the environment
npm run env:clean            # Reset database and uploads
```

Admin login: `admin` / `password`

## Running Tests

```bash
# Unit tests (no WordPress needed)
composer test

# Single test
vendor/bin/phpunit --filter TestClassName::testMethodName

# Plugin Check (needs wp-env running)
npm run plugin-check
```

## Linting and Static Analysis

```bash
# Run everything
composer check                # PHPCS + PHPStan + PHPUnit

# Individual tools
composer lint                 # PHPCS (WordPress standard)
composer lint:fix             # PHPCBF auto-fix
composer analyze              # PHPStan level 6
```

## Coding Standards

- PHP: WordPress Coding Standards (WPCS 3.x) via PHPCS
- PHP 8.0+ strict types: every file starts with `declare(strict_types=1)`
- PSR-4 autoloading: `GracefulErrorPages\` maps to `src/`
- Constant prefix: `GEP_`, option prefix: `gep_`, hook prefix: `gep_`
- All output escaped at point of output
- All input sanitized with appropriate WordPress functions
- Nonces on all forms

## Conventional Commits

This project uses conventional commits for automatic changelog generation:

- `feat:` new feature
- `fix:` bug fix
- `perf:` / `a11y:` improvement
- `security:` security fix
- `docs:` / `test:` / `ci:` / `chore:` non-user-facing (skipped in changelog)

## Pull Request Process

1. Create a feature branch from `main`
2. Make your changes
3. Run `composer check` -- must pass
4. Write or update tests for your changes
5. Push and open a PR against `main`
6. CI must pass before merge
