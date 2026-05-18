# VELORA — Système de gestion d'événements

Application **React** (frontend) + **Laravel** (API REST) conforme au diagramme de cas d'utilisation.

## Structure

- `backend/` — API Laravel 13 + Sanctum + SQLite
- `frontend/` — React 19 + Vite + JavaScript (JSX) + Tailwind CSS

## Démarrage rapide

### 1. Backend

```bash
cd backend
composer install
php artisan migrate:fresh --seed
php -d upload_max_filesize=12M -d post_max_size=16M artisan serve
rem ou sous Windows : serve.bat (images jusqu'à 10 Mo)
```

API disponible sur **http://127.0.0.1:8000**

### 2. Frontend

```bash
cd frontend
npm install
npm run dev
```

Interface sur **http://localhost:5173** (proxy API vers Laravel)

## Comptes de démonstration

| Rôle | Email | Mot de passe |
|------|-------|--------------|
| Administrateur | admin@demo.local | password |
| Organisateur | organisateur@demo.local | password |
| Participant | participant@demo.local | password |
| Client | client@demo.local | password |

## Fonctionnalités par acteur

| Acteur | Fonctionnalités |
|--------|-----------------|
| **Client** | Demander un événement (sans login), consulter ses statistiques |
| **Participant** | Rechercher, s'inscrire, payer, télécharger billet, laisser un avis |
| **Organisateur** | CRUD événements, capacité, tâches, activités |
| **Administrateur** | Valider/rejeter demandes, assigner organisateur, CRUD utilisateurs, stats globales, voir tous les événements |

L'authentification Sanctum protège toutes les routes sauf `POST /api/event-requests`, `POST /api/login`, `POST /api/register`.

## Stack technique

- **Backend:** Laravel Sanctum, migrations SQLite, middleware `role`
- **Frontend:** React Router, Axios, routes protégées par rôle, UI sombre moderne
