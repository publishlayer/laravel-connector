# Releasing

## Before tagging

```bash
composer validate --strict
composer test
```

Confirm:

- `README.md` matches the current routes, commands, and environment variables.
- `CHANGELOG.md` contains the release entry.
- `composer.json` metadata and support links are correct.
- GitHub Actions is green on the supported matrix.

## Tagging

```bash
git commit -am "Prepare v0.1.0 release"
git tag v0.1.0
git push origin master
git push origin v0.1.0
```

## Packagist

- Ensure the GitHub repository is connected in Packagist.
- Trigger a Packagist update after the tag is pushed if auto-update is not enabled.
