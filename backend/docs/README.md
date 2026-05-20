# VELORA Backend Documentation

This directory contains three documentation layers:

- Generated phpDocumentor class reference pages for backend internals.
- `api/` contains authored API documentation for routes, roles, request bodies, and frontend-facing contracts.
- `architecture/`, `merise/`, and `uml/` contain authored backend explanation, Merise analysis, and workflow diagrams.

Only the authored docs in this folder are versioned:

- `README.md`
- `api/README.md`
- `api/openapi.yaml`
- `architecture/backend-map.md`
- `merise/README.md`
- `uml/workflows.md`

The generated class-doc output and the `backend/.phpdoc/` cache are local build artifacts and should not be committed.

## API Docs

- Human-readable route guide: `api/README.md`
- OpenAPI 3.1 contract: `api/openapi.yaml`

## Architecture And Diagrams

- Backend reading map and commenting standard: `architecture/backend-map.md`
- Merise documentation with MCD, MLD, and MCT: `merise/README.md`
- UML-style workflow diagrams for application flows: `uml/workflows.md`

## Commenting Standard

Code comments and docblocks should stay. They are part of the backend's readability layer. Improve them when touching a file, especially around business rules, Mongo transactions, atomic updates, role checks, money/date representation, and frontend response compatibility.

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
