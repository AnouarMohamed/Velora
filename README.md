# VELORA - Systeme de gestion d'evenements


Application React + Laravel pour gerer les demandes d'evenements, les publications, les inscriptions, les paiements simules, les avis et les notifications.

## Stack locale

- `backend/` - Laravel 13 API REST, Sanctum, MongoDB
- `frontend/` - React 19 + Vite
- `docker-compose.yml` - Backend, frontend, MongoDB replica set local et Redis

Le backend ne supporte plus SQLite, MySQL, PostgreSQL ou SQL Server. `DB_CONNECTION` doit rester `mongodb`.

## Demarrage rapide avec Docker

```bash
docker compose up --build
```

- API: http://127.0.0.1:8000/api
- Health check: http://127.0.0.1:8000/api/health
- Frontend: http://127.0.0.1:5173
- MongoDB local: `mongodb://127.0.0.1:27017/?replicaSet=rs0`
- Redis local: `127.0.0.1:6379`

Au premier demarrage, le conteneur backend installe les dependances Composer, genere `APP_KEY`, lance les migrations MongoDB et charge les donnees de demonstration si la collection `users` est vide.

## Demarrage backend hors Docker

Le backend a besoin de PHP avec les extensions `mongodb` et `redis`, Composer, un MongoDB replica set et Redis.

```bash
cd backend
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force
php artisan serve --host=127.0.0.1 --port=8000
```

## Comptes de demonstration

| Role | Email | Mot de passe |
|------|-------|--------------|
| Administrateur | admin@demo.local | password |
| Organisateur | organisateur@demo.local | password |
| Participant | participant@demo.local | password |
| Client | client@demo.local | password |

## Documentation backend

- Guide API par roles: `backend/docs/api/README.md`
- Specification OpenAPI 3.1: `backend/docs/api/openapi.yaml`
- Carte de lecture du backend: `backend/docs/architecture/backend-map.md`
- Documentation Merise: `backend/docs/merise/README.md`
- Diagrammes UML des workflows: `backend/docs/uml/workflows.md`
- Reference PHP generee: ouvrir `backend/docs/index.html` dans un navigateur

Le cache phpDocumentor `backend/.phpdoc/` est ignore. Les pages generees doivent etre regenerees uniquement quand les docblocks backend changent vraiment.

## Comment lire le backend

Pour comprendre une fonctionnalite, suivez toujours le meme chemin:

1. `backend/routes/api.php` pour voir la route, le middleware d'authentification et le role requis.
2. `backend/app/Http/Requests/` pour voir la validation et l'autorisation d'entree.
3. `backend/app/Http/Controllers/Api/` pour voir la forme HTTP de la reponse.
4. `backend/app/Services/` pour lire les vraies regles metier.
5. `backend/app/Models/` et la migration d'indexes Mongo pour comprendre les collections, les champs et les contraintes.
6. `backend/tests/Feature/` pour confirmer le comportement attendu par role.

Les commentaires dans le code sont volontaires. Ils doivent rester explicites, surtout autour des transactions Mongo, des mises a jour atomiques, des roles, des montants en centimes, des dates et des formes de reponse attendues par le frontend.

## Fonctionnalites par acteur

| Acteur | Fonctionnalites |
|--------|-----------------|
| Client | Demander un evenement, consulter ses statistiques |
| Participant | Rechercher, s'inscrire, payer, telecharger billet, laisser un avis |
| Organisateur | CRUD evenements, capacite, taches, activites |
| Administrateur | Valider/rejeter demandes, assigner organisateur, CRUD utilisateurs, stats globales |
