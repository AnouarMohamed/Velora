# Backend Refactoring Plan: Achieving a 10/10 Architecture

This document outlines a step-by-step, robust plan to upgrade the Laravel backend from a good implementation to an enterprise-grade standard.

## Phase 1: Strict Typing & Enums (Foundation)
**Goal:** Prevent silent type coercion bugs and enforce strict value boundaries.

1. **Add Strict Types:**
   - Add `declare(strict_types=1);` to the very top of every PHP file (Controllers, Models, Services, Requests).
   - *Tooling:* Use a tool like PHP-CS-Fixer or Rector to automate this across the `backend/app` directory.
2. **Implement Native PHP Enums:**
   - Create an `app/Enums` directory.
   - Refactor string constants in Models to Backed Enums.
     - Example: Create `UserRole.php` (Admin, Organizer, Participant, Client).
     - Update `$casts` in the `User` model to automatically cast the `role` column to `UserRole::class`.
   - Update middleware (`EnsureRole.php`) and Requests to validate against the Enum using `Rule::enum(UserRole::class)`.

## Phase 2: API Resources (Data Encapsulation)
**Goal:** Never leak database structures to the frontend. Ensure the API contract remains stable even if the database schema changes.

1. **Create Base Resources:**
   - Run `php artisan make:resource UserResource`
   - Run `php artisan make:resource EventResource`, etc.
2. **Refactor Controllers:**
   - Go through every controller returning models directly (e.g., `return response()->json($user)` or `return $event`).
   - Replace with `return new UserResource($user);` or `return EventResource::collection($events);`.
3. **Handle Relationships:**
   - Use `$this->whenLoaded('relation')` inside the resources to conditionally load nested data (preventing N+1 query problems on the frontend).

## Phase 3: Data Transfer Objects (DTOs)
**Goal:** Strongly type the data passing between the HTTP layer (Controllers) and the Business Logic layer (Services).

1. **Setup DTO Structure:**
   - Create an `app/DTOs` directory.
   - Use PHP 8.2+ `readonly class` to define DTOs (e.g., `RegisterUserDTO`, `CreateEventDTO`).
2. **Map Requests to DTOs:**
   - Add a `toDTO()` method in your FormRequests.
   - Example: `$dto = $request->toDTO();`
3. **Refactor Services:**
   - Change service method signatures from `array $data` to strongly typed DTOs.
     - Example: `public function create(RegisterUserDTO $dto): User`

## Phase 4: Interface-Driven Architecture (Contracts)
**Goal:** Decouple controllers from concrete implementations, making the code 100% modular and testable.

1. **Define Contracts:**
   - Create an `app/Contracts/Services` namespace.
   - Extract interfaces from your existing services.
     - Example: Create `UserWriteServiceInterface`.
2. **Implement & Bind:**
   - Ensure `UserWriteService` implements `UserWriteServiceInterface`.
   - In `AppServiceProvider.php` (or a dedicated `ServiceBindingProvider`), bind the interface to the concrete class:
     `$this->app->bind(UserWriteServiceInterface::class, UserWriteService::class);`
3. **Update Dependency Injection:**
   - Update controllers to inject the Interface rather than the concrete class.

## Phase 5: Automated API Documentation
**Goal:** Provide a live, interactive, and self-updating API manual for the frontend developer.

1. **Install OpenAPI/Swagger or Scribe:**
   - Recommend using `knuckleswtf/scribe` for Laravel as it parses FormRequests and API Resources automatically.
2. **Annotate Controllers:**
   - Add endpoint grouping, descriptions, and expected response tags to Controller methods.
3. **Generate & Expose:**
   - Run the generation command (e.g., `php artisan scribe:generate`).
   - Expose the documentation route (e.g., `http://localhost:8000/docs`).

## Phase 6: Code Quality & Static Analysis
**Goal:** Enforce the new standards automatically on every commit.

1. **PHPStan Integration:**
   - You already have `phpstan.neon`. Update its level to `Max` (Level 9).
   - Run `vendor/bin/phpstan analyse` and resolve all typing and generic iterable warnings (e.g., specifying `Collection<int, Event>`).
2. **CI/CD Pipeline:**
   - Ensure you have a GitHub Action or GitLab CI that runs PHPStan and PHPUnit before allowing any merges to the main branch.