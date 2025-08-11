# Getting Started for Library Developers

This guide covers setting up the environment to work on the Orbital Dev Library itself and the workflow for linting, formatting, and testing.

## Prerequisites

- PHP 8.1+
- Composer
- Git

## Initial setup

From the library root directory:

```sh
bash scripts/setup-dev.sh
```

What this does:

- Runs a clean dependency install (composer update)
- Installs the pre-commit Git hook (if a .git directory is present)
- Prints the commonly used Composer scripts

Install (or re-install) the hook manually anytime:

```sh
composer hooks:install
```

## Composer scripts overview

- lint: dry-run style check and PHPCS summary. Internally: cs:check + standards
- cs: Fix code style with PHP CS Fixer (fast; uses cache)
- cs:check: Dry-run PHP CS Fixer with diff
- standards: PHPCS summary via the project runner
- standards:full: PHPCS full output via the project runner
- standards:fix: PHPCBF (auto-fix) via the project runner
- format: Full repo auto-fix (PHP CS Fixer, then PHPCBF). Most thorough; slower
- test: Run the PHPUnit suite
- test:coverage: Run tests with coverage text
- qa: Lint + summary standards + tests (lint plus tests)
- qa:ci: Lint + full standards + tests with coverage (recommended for CI)
- hooks:install: Install the pre-commit hook into .git/hooks/

Examples:

```sh
# Quick check without changing files
composer lint

# Auto-fix everything repo-wide
composer format

# Run style checks + tests before pushing/PR
composer qa

# CI-grade checks locally
composer qa:ci
```

## Typical workflow

- While coding: run `composer cs` periodically to keep files tidy
- Commit: rely on the pre-commit hook; optionally run `composer lint` first
- Before push/PR: run `composer qa`
- Repo-wide normalization (infrequent): run `composer format`

## Pre-commit hook (what it covers)

The hook runs on staged PHP files only:

1. PHP CS Fixer dry-run. If issues are found, it auto-fixes and re-stages.
2. PHPCS auto-fix (PHPCBF) via the project runner, then re-stages.
3. PHPCS verification. If violations remain, the commit is blocked.

## Notes on tools and config

- PHPCS is invoked via scripts/php-codesniffer.php (a runner) to avoid path/parallel issues and ensure consistent rules.
- PHP CS Fixer uses a cache file (.php-cs-fixer.cache) for speed. It generally should not be committed.
- Tests run with composer test (uses phpdbg and phpunit.xml).

## Troubleshooting

- "Referenced sniff … does not exist": Use the provided standards\* scripts (runner) rather than vendor/bin/phpcs directly.
- Hook didn’t install: Run `composer hooks:install` from the repo root and ensure this is a Git repo (.git exists).
- Large diffs after format: Expected initially; format normalizes the whole repo (Fixer + PHPCBF). Day-to-day, prefer `cs` and the pre-commit hook.

## CI recommendation

Use `composer qa:ci` in CI. Do not rely on local Git hooks in CI.
