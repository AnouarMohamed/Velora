# VELORA Backend Documentation

This directory contains two documentation layers:

- Generated phpDocumentor class reference pages for backend internals.
- `api/` contains authored API documentation for routes, roles, request bodies, and frontend-facing contracts.

Only the authored docs in this folder are versioned:

- `README.md`
- `api/README.md`
- `api/openapi.yaml`

The generated class-doc output and the `backend/.phpdoc/` cache are local build artifacts and should not be committed.

## API Docs

- Human-readable route guide: `api/README.md`
- OpenAPI 3.1 contract: `api/openapi.yaml`

## Regenerating Class Docs

From the repository root:

```bash
docker run --rm -v "$PWD/backend:/data" phpdoc/phpdoc:3 \
  -d /data/app \
  -t /data/docs \
  --title "VELORA API Documentation" \
  --cache-folder /data/.phpdoc/cache
```

After regenerating, run the backend checks before committing:

```bash
docker compose run --rm backend composer test
docker compose run --rm backend ./vendor/bin/pint --test
docker compose run --rm backend ./vendor/bin/phpstan analyse --memory-limit=512M
```
