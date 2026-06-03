# 🚀 Guide de Déploiement Rapide (Fork)

Ce guide explique comment déployer ce projet sur **Vercel** en moins de 5 minutes.

## 1. Préparer la Base de Données (MongoDB Atlas)
Le projet utilise MongoDB Atlas. 
1. Connectez-vous à [MongoDB Atlas](https://www.mongodb.com/cloud/atlas).
2. Allez dans **Network Access** (menu de gauche).
3. Cliquez sur **Add IP Address** et ajoutez `0.0.0.0/0` (nécessaire car Vercel utilise des IPs dynamiques).
4. Allez dans **Database** -> **Connect** -> **Drivers** pour récupérer votre `DATABASE_URL` (DSN).

## 2. Déployer le Backend sur Vercel
1. Créez un nouveau projet sur Vercel et importez le dossier `backend`.
2. **Framework Preset:** Choisissez `Other` (Vercel détectera la config `vercel.json`).
   (Laissez les "Build and Development Settings" par défaut, ne spécifiez pas de commande de build manuel).
3. **Root Directory:** Sélectionnez `backend`.
4. **Environment Variables:** Ajoutez les variables suivantes dans l'interface Vercel (copiez les valeurs depuis `backend/.env.vercel`) :
   - `APP_KEY`: (Ex: `base64:neRQgVih...`)
   - `DB_DSN`: Votre lien MongoDB Atlas.
   - `DB_DATABASE`: `velora`
   - `APP_ENV`: `production`
   - `APP_DEBUG`: `false`
   - `CORS_ALLOWED_ORIGINS`: L'URL de votre futur frontend (ex: `https://sec-ret-cms.vercel.app`).
   - `CACHE_STORE`: `array`
   - `SESSION_DRIVER`: `cookie`
   - `QUEUE_CONNECTION`: `sync`
5. Déployez. Notez l'URL générée (ex: `https://sec-ret-cms-backend.vercel.app`).

## 3. Déployer le Frontend sur Vercel
1. Créez un nouveau projet sur Vercel et importez le dossier `frontend`.
2. **Root Directory:** Sélectionnez `frontend`.
3. **Environment Variables:**
   - `VITE_API_URL`: L'URL de votre backend suivie de `/api` (ex: `https://sec-ret-cms-backend.vercel.app/api`).
4. Déployez.

## 4. Vérification
Une fois déployé, visitez `https://votre-backend.vercel.app/up`. Une page blanche avec "OK" signifie que tout fonctionne.
