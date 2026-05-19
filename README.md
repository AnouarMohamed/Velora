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

## Fonctionnalites par acteur

| Acteur | Fonctionnalites |
|--------|-----------------|
| Client | Demander un evenement, consulter ses statistiques |
| Participant | Rechercher, s'inscrire, payer, telecharger billet, laisser un avis |
| Organisateur | CRUD evenements, capacite, taches, activites |
| Administrateur | Valider/rejeter demandes, assigner organisateur, CRUD utilisateurs, stats globales |
