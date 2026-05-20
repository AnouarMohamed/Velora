# Backend Architecture Map

This backend is intentionally organized around thin HTTP controllers and business services. The goal is not to make each file tiny; the goal is to make each file responsible for one clear layer.

## Reading Order

Use this order when trying to understand or debug a feature:

1. `routes/api.php`
   Maps the URL, role middleware, and controller method.
2. `app/Http/Requests/...`
   Validates input and role-specific authorization before the controller runs.
3. `app/Http/Controllers/Api/...`
   Converts the HTTP request into a service call and returns JSON.
4. `app/Services/...`
   Holds business rules, workflow decisions, transactions, and domain errors.
5. `app/Models/...`
   Defines Mongo collections, fillable fields, casts, accessors, and relationships.
6. `database/migrations/2026_05_18_000000_create_mongo_indexes.php`
   Defines the Mongo indexes that make the workflows safe and fast.
7. `tests/Feature/...`
   Shows expected behavior from the API consumer point of view.

## Commenting Standard

Comments and docblocks are part of the codebase documentation style and should stay. They should be useful to a developer reading the system for the first time.

Good comments explain:

- why a business rule exists;
- what a Mongo transaction, atomic update, or unique index is protecting;
- what role is allowed to perform an action;
- how money, dates, images, and ObjectId strings are represented;
- what response shape the frontend depends on.

Avoid comments that only repeat the PHP syntax. For example, a comment saying "return response as JSON" is less useful than a comment explaining why the response keeps an existing frontend shape.

## Feature Map

| Feature | Routes | Controller | Requests | Services | Models | Main Tests |
| --- | --- | --- | --- | --- | --- | --- |
| Health | `/api/health` | `HealthController` | none | `HealthCheckService` | none | `MongoOnlyConfigurationTest`, `ApiMiddlewareTest` |
| Auth | `/register`, `/login`, `/logout`, `/user` | `AuthController` | `RegisterRequest`, `LoginRequest` | `UserWriteService` | `User`, `PersonalAccessToken` | `AuthAndUserManagementFlowTest` |
| User admin | `/admin/users`, `/admin/organizers` | `UserAdminController` | `UserIndexRequest`, `StoreUserRequest`, `UpdateUserRequest` | `UserWriteService` | `User` | `AuthAndUserManagementFlowTest`, `QueryValidationTest` |
| Event browsing | `/events/browse`, `/events/{event}` | `EventController` | `EventIndexRequest` | `EventManagementService` | `Event`, `Feedback` | `EventManagementFlowTest`, `QueryValidationTest` |
| Organizer event management | `/organizer/events...` | `EventController` | `StoreEventRequest`, `UpdateEventRequest`, `UpdateEventCapacityRequest` | `EventManagementService`, `EventImageStorage` | `Event` | `EventManagementFlowTest` |
| Admin event management | `/admin/events...` | `EventController` | `AssignEventOrganizerRequest`, event write requests | `EventManagementService`, `EventImageStorage` | `Event`, `User` | `EventManagementFlowTest` |
| Client event requests | `/event-requests` | `EventRequestController` | `StoreClientEventRequest` | `EventRequestSubmissionService`, `EventRequestEligibilityService`, `EventRequestImageStorage` | `EventRequest`, `Event` | `EventRequestClientFlowTest` |
| Event request review | `/admin/event-requests...` | `EventRequestController` | `EventRequestIndexRequest`, `ReviewEventRequestRequest` | `EventRequestReviewService` | `EventRequest`, `Event` | `EventRequestReviewFlowTest` |
| Planning tasks | `/organizer/events/{event}/tasks`, `/admin/events/{event}/tasks` | `EventTaskController` | `StoreEventTaskRequest`, `UpdateEventTaskRequest` | `EventTaskService` | `EventTask`, `Event` | `EventPlanningFlowTest` |
| Event activities | `/organizer/events/{event}/activities`, `/admin/events/{event}/activities` | `EventActivityController` | `StoreEventActivityRequest`, `UpdateEventActivityRequest` | `EventActivityService` | `EventActivity`, `Event` | `EventPlanningFlowTest` |
| Participant registrations | `/events/{event}/register`, `/my-registrations`, `/registrations/{registration}` | `RegistrationController` | `ParticipantRegistrationIndexRequest` | `ParticipantRegistrationService`, `RegistrationService` | `Registration`, `Payment`, `Event` | `RegistrationFlowTest`, `MoneyStorageTest` |
| Staff registration management | `/organizer/registrations...`, `/admin/registrations...` | `StaffRegistrationController` | `StaffRegistrationIndexRequest` | `StaffRegistrationService`, `RegistrationStatsService` | `Registration`, `Event` | `StaffRegistrationFlowTest` |
| Feedback | `/events/{event}/feedback`, `/admin/feedbacks...` | `FeedbackController` | `StoreFeedbackRequest` | `FeedbackService` | `Feedback`, `Registration`, `Event` | `FeedbackFlowTest` |
| Notifications | `/notifications...` | `NotificationController` | none | `NotificationService` | `AppNotification` | covered through workflow tests |
| Stats | `/admin/stats`, `/client/stats` | `StatsController` | none | `AdminStatsService`, `ClientStatsService` | `Event`, `Registration`, `Payment`, `EventRequest`, `Feedback` | `StatsFlowTest`, `MoneyStorageTest` |

## Layer Responsibilities

### Controllers

Controllers should stay short. They are responsible for:

- receiving already-authenticated requests;
- extracting the authenticated actor;
- passing validated data into a service;
- preserving the API response shape expected by the frontend.

Controllers should not hold multi-step business workflows, capacity logic, approval rules, or Mongo transaction details.

### Form Requests

Form Requests are the first explicit boundary for input safety. They are responsible for:

- validating strings, dates, numbers, ObjectId values, and enum values;
- applying simple role authorization when the role is enough to decide access;
- keeping query-string filters out of controllers.

### Services

Services are the backend's business layer. They are responsible for:

- event lifecycle rules;
- registration and payment rules;
- feedback moderation rules;
- client request eligibility;
- admin review decisions;
- notification fan-out;
- Mongo transactions and atomic updates.

### Models

Models describe Mongo documents and API serialization behavior. They are responsible for:

- collection names;
- fillable fields;
- casts for BSON dates and arrays;
- computed public fields such as `image_url`, `ticket_price`, and `amount`;
- relationships between ObjectId string fields.

## Mongo-Only Data Rules

- `DB_CONNECTION` must stay `mongodb`.
- Public relationship IDs are Mongo ObjectId strings.
- Money is stored as integer cents and exposed through decimal-compatible API fields.
- Dates are stored as Mongo BSON dates and serialized as ISO-8601 through Laravel.
- Duplicate prevention belongs in Mongo unique indexes, with service-level checks for user-friendly errors.

## Most Important Safety Rules

| Rule | Where Enforced |
| --- | --- |
| No duplicate user email | `users_email_unique` index and user validation |
| No duplicate registration for same event and participant | `registrations_event_user_unique` index and `RegistrationService` |
| No duplicate feedback for same event and participant | `feedbacks_event_user_unique` index and `FeedbackService` |
| No event overbooking | conditional atomic increment in `RegistrationService` |
| No reducing capacity below current registrations | `EventManagementService` |
| No direct organizer publish | `EventManagementService` |
| No repeated event request review | conditional status update in `EventRequestReviewService` |
| No paid registration deletion | `RegistrationService` and `StaffRegistrationService` |
| No unauthenticated API access | `auth:sanctum` middleware |
| No wrong-role route access | `role` middleware and Form Requests |
