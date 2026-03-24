# Contributing

## Local setup

```bash
composer install
composer test
```

The package targets PHP 8.2+ and Laravel 11/12 through Orchestra Testbench.

## Development guidelines

- Keep the package focused on reusable Laravel package behavior, not app-specific conventions.
- Update tests when public routes, commands, config keys, or payload handling changes.
- Keep the README and changelog aligned with the shipped code.
- Do not commit editor files, cache files, or `composer.lock`; this is a reusable library package.

## Pull requests

- Include tests for behavior changes.
- Document public-facing changes in `README.md` and `CHANGELOG.md` when relevant.
- Prefer small, reviewable changes over broad refactors.
