# Plan de Durcissement Backend

Ce document est la référence courte pour expliquer ce qui est déjà durci dans le backend et ce qui doit être vérifié avant un déploiement réel.

## État Actuel

| Sujet | Statut | Preuve dans le code |
| --- | --- | --- |
| Base de données unique | MongoDB uniquement | `DB_CONNECTION=mongodb`, migrations d'index Mongo, tests `MongoOnlyConfigurationTest` |
| Authentification API | Sanctum bearer tokens | `AuthController`, middleware `auth:sanctum`, collection `personal_access_tokens` |
| Autorisation par rôle | Routes et Form Requests | Middleware `role`, contrôleurs séparés par surface admin/organisateur/public |
| Validation d'entrée | Form Requests | `app/Http/Requests/*` |
| Intégrité métier | Services et index | Transactions Mongo, index uniques, incrémentation atomique de capacité |
| Notifications massives | Queue Redis | `FanOutPublishedEventNotifications` traite les participants par curseur et lots de 500 |
| Boîte de réception notifications | Agrégation Mongo | `NotificationInboxService` renvoie `data`, `unread_count` et `meta` avec un `$facet` |
| Stats admin | Cache court | `AdminStatsService` cache le payload pendant 60 secondes |
| Santé API | Dépendances réelles | `HealthCheckService` vérifie MongoDB et Redis |
| Erreurs santé en production | Messages génériques | `APP_DEBUG=false` masque les messages d'exception de dépendances |
| En-têtes de sécurité | Middleware API | `ApplyApiSecurityHeaders` inclut CSP, frame denial, nosniff, policies |
| Analyse statique | PHPStan max sans baseline | `phpstan.neon` niveau `max`, aucune baseline ignorée |
| CI | Gates automatisés | `.github/workflows/ci.yml` exécute Pint, PHPStan, tests, audits et scans Trivy |
| Contrat OpenAPI | Test automatisé | `OpenApiContractTest` parse `openapi.yaml` et vérifie les points sensibles |

## Checklist Production

- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_KEY` générée et gardée secrète.
- `SEED_DEMO_DATA=0`
- `DB_DSN` MongoDB sans credentials exposés dans les logs ou réponses HTTP.
- MongoDB en replica set, avec sauvegardes testées.
- Redis protégé par réseau privé et mot de passe si exposé hors host local.
- `QUEUE_CONNECTION=redis`
- `REDIS_QUEUE_RETRY_AFTER` supérieur au timeout du job le plus long. Le défaut local est `180` secondes pour un job de diffusion à `120` secondes.
- Worker de queue actif, par exemple `php artisan queue:work redis --tries=3 --timeout=120`.
- Supervision du worker : redémarrage automatique, alertes sur jobs échoués, logs persistés.
- HTTPS terminé devant l'API.
- Configuration proxy de confiance si l'API est derrière un reverse proxy.
- Origines CORS limitées au frontend réel.
- `composer audit`, `npm audit`, tests et PHPStan exécutés avant livraison.

## Dette Connue

- La baseline PHPStan contient encore 130 erreurs historiques ignorées. Le niveau est `max`, mais il faut réduire cette baseline progressivement.
- Le cache admin stats est volontairement basé sur un TTL court de 60 secondes. Il n'y a pas encore d'invalidation événementielle.
- Si la documentation de classe PHP générée est publiée, elle doit être régénérée après modification des docblocks PHP. La visionneuse `backend/docs/api/index.html` charge directement `backend/docs/api/openapi.yaml`.
