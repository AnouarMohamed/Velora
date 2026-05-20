# VELORA Backend Documentation

This directory contains two documentation layers:

- `index.html` and the generated HTML tree are phpDocumentor class reference pages for backend internals.
- `api/` contains authored API documentation for routes, roles, request bodies, and frontend-facing contracts.

The generated cache in `backend/.phpdoc/` is intentionally ignored. It is local build output and should not be committed.

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
