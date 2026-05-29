# Deployment Instructions (Back4app + Vercel)

This document tracks the autonomous deployment process of the SecRet-cms application.

## Phase 1: Audit the Project
- [x] Laravel Version: 13.x
- [x] PHP Version Required: 8.3
- [x] React Version: 19.x
- [x] Identify .env variables: Done
- [x] Check for Dockerfile: Created
- [x] Database Compatibility: MongoDB (Atlas)

## Phase 2: Prepare Backend
- [x] Create `backend/Dockerfile`
- [x] Create `backend/nginx.conf`
- [x] Update CORS for Back4app/Vercel

## Phase 3: Prepare Frontend
- [x] Create `frontend/.env.production`
- [x] Verify API call environment variables

## Phase 4: Database Setup
- [x] Request MongoDB Atlas connection string

## Phase 5: Git & Deploy
- [x] Update `.gitignore`
- [x] Commit and Push

## Target URLs
- Backend: https://houdaz-8dd9bxe5.b4a.run
- Frontend: https://sec-ret-cms.vercel.app

