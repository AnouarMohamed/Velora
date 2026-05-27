# VELORA - Systeme de gestion d'evenements


Application React + Laravel pour gerer les demandes d'evenements, les publications, les inscriptions, les paiements simules, les avis et les notifications.

## Guide Pas à Pas (Baby Steps)

Voici comment lancer l'application et commencer à l'utiliser en quelques secondes :

### 1. Demarrer l'application
Depuis la racine du projet, lancez tous les services en arrière-plan :
```bash
docker compose up -d --build
```

### 2. Charger les donnees de demonstration
Une fois les conteneurs démarrés, initialisez la base de données MongoDB et injectez les données de démonstration :
```bash
docker compose exec backend php artisan migrate:fresh --seed --force
```

### 3. Ouvrir dans le navigateur
* **Application (Frontend React)** : [http://127.0.0.1:5173](http://127.0.0.1:5173)
* **API (Backend Laravel)** : [http://127.0.0.1:8000/api](http://127.0.0.1:8000/api)

### 4. Comptes de demonstration disponibles
Tous les comptes utilisent le mot de passe : `password`
* **Organisateur** : `organisateur@demo.local`
* **Administrateur** : `admin@demo.local`
* **Participant** : `participant@demo.local`
* **Client** : `client@demo.local`

---

> [!IMPORTANT]
> **Note sur les tests automatises :**
> L'exécution des tests du backend (`docker compose exec backend php artisan test`) va écraser la base de données. 
> 
> Si vous obtenez une erreur `422 (Unprocessable Content)` en essayant de vous connecter après avoir lancé les tests, restaurez simplement vos comptes de démo en exécutant :
> ```bash
> docker compose exec backend php artisan db:seed
> ```

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
- Visionneuse API HTML: ouvrir `backend/docs/api/index.html`
- Carte de lecture du backend: `backend/docs/architecture/backend-map.md`
- Plan de durcissement backend: `backend/docs/operations/hardening.md`
- Documentation Merise: `backend/docs/merise/README.md`
- Diagrammes UML des workflows: `backend/docs/uml/workflows.md`
- Reference PHP generee: generer localement avec la commande indiquee dans `backend/docs/README.md` si une documentation de classes est necessaire

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

## Durcissement backend

Le backend s'appuie sur Redis pour le cache, les sessions, la limitation de debit et les files d'attente. En production, un worker doit tourner pour traiter les jobs, notamment la diffusion des notifications aux participants:

```bash
php artisan queue:work redis --tries=3 --timeout=120
```

La checklist complete est dans `backend/docs/operations/hardening.md`.

## Fonctionnalites par acteur

| Acteur | Fonctionnalites |
|--------|-----------------|
| Client | Demander un evenement, consulter ses statistiques |
| Participant | Rechercher, s'inscrire, payer, telecharger billet, laisser un avis |
| Organisateur | CRUD evenements, capacite, taches, activites |
| Administrateur | Valider/rejeter demandes, assigner organisateur, CRUD utilisateurs, stats globales |
