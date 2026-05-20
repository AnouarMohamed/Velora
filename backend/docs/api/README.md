# Backend API Reference

VELORA exposes a Laravel Sanctum JSON API under `/api`. The backend is MongoDB-only and stores model relationships as Mongo ObjectId string values in API-facing fields.

## Authentication

Public endpoints:

| Method | Path | Purpose |
| --- | --- | --- |
| `GET` | `/api/health` | Health check. |
| `POST` | `/api/register` | Register a participant or client account. |
| `POST` | `/api/login` | Login and receive a Sanctum bearer token. |

Authenticated requests use:

```http
Authorization: Bearer <token>
Accept: application/json
```

Demo users after `php artisan migrate:fresh --seed --force`:

| Role | Email | Password |
| --- | --- | --- |
| Admin | `admin@demo.local` | `password` |
| Organizer | `organisateur@demo.local` | `password` |
| Participant | `participant@demo.local` | `password` |
| Client | `client@demo.local` | `password` |

## Role Route Groups

### Shared Authenticated Routes

| Method | Path | Notes |
| --- | --- | --- |
| `POST` | `/api/logout` | Revokes the current token. |
| `GET` | `/api/user` | Current authenticated user. |
| `GET` | `/api/notifications` | Current user's notifications. |
| `GET` | `/api/notifications/unread-count` | Unread notification count. |
| `POST` | `/api/notifications/read-all` | Marks all current user's notifications as read. |
| `POST` | `/api/notifications/{notification}/read` | Marks one notification as read. |
| `GET` | `/api/events/browse` | Published event browser. |
| `GET` | `/api/events/{event}` | Event detail. Unpublished events are manager-only. |
| `GET` | `/api/events/{event}/feedbacks` | Public feedback, with expanded visibility for admins. |

### Participant

| Method | Path | Notes |
| --- | --- | --- |
| `POST` | `/api/events/{event}/register` | Register for a published event. Capacity and duplicate registration are guarded. |
| `GET` | `/api/events/{event}/my-registration` | Participant registration for one event. |
| `GET` | `/api/my-registrations` | Participant registration history. |
| `POST` | `/api/registrations/{registration}/pay` | Mock payment for a pending registration. |
| `DELETE` | `/api/registrations/{registration}` | Cancel an unpaid registration. |
| `GET` | `/api/registrations/{registration}/ticket` | Returns ticket payload for a paid registration. |
| `POST` | `/api/events/{event}/feedback` | Submit feedback for an attended paid event. |

### Organizer

| Method | Path | Notes |
| --- | --- | --- |
| `GET` | `/api/organizer/events` | Organizer-owned or created events. |
| `POST` | `/api/organizer/events` | Create draft event. |
| `PATCH` | `/api/organizer/events/{event}` | Update managed event. |
| `PATCH` | `/api/organizer/events/{event}/capacity` | Update capacity, never below registered count. |
| `POST` | `/api/organizer/events/{event}/request-publication` | Submit event for admin approval. |
| `GET` | `/api/organizer/events/{event}/tasks` | List planning tasks. |
| `POST` | `/api/organizer/events/{event}/tasks` | Create planning task. |
| `PATCH` | `/api/organizer/events/{event}/tasks/{eventTask}` | Update planning task. |
| `DELETE` | `/api/organizer/events/{event}/tasks/{eventTask}` | Delete planning task. |
| `GET` | `/api/organizer/events/{event}/activities` | List event activities. |
| `POST` | `/api/organizer/events/{event}/activities` | Create event activity. |
| `PATCH` | `/api/organizer/events/{event}/activities/{eventActivity}` | Update event activity. |
| `DELETE` | `/api/organizer/events/{event}/activities/{eventActivity}` | Delete event activity. |
| `GET` | `/api/organizer/registrations/events` | Events with registration counts. |
| `GET` | `/api/organizer/registrations` | Registrations for organizer-managed events. |
| `DELETE` | `/api/organizer/registrations/{registration}` | Delete unpaid registration for managed event. |

### Admin

Admins can use organizer event planning routes and also have these admin-only routes:

| Method | Path | Notes |
| --- | --- | --- |
| `GET` | `/api/admin/events` | All events. |
| `GET` | `/api/admin/organizer-events` | Events owned or created by organizers. |
| `GET` | `/api/admin/my-events` | Events assigned to or created by the admin. |
| `DELETE` | `/api/admin/events/{event}` | Delete event. |
| `PATCH` | `/api/admin/events/{event}/assign-organizer` | Assign organizer. |
| `PATCH` | `/api/admin/events/{event}` | Update event. |
| `PATCH` | `/api/admin/events/{event}/capacity` | Update capacity. |
| `POST` | `/api/admin/events/{event}/approve-publication` | Publish pending event. |
| `GET` | `/api/admin/event-requests` | List client event requests. |
| `POST` | `/api/admin/event-requests/{eventRequest}/review` | Approve or reject event request. |
| `GET` | `/api/admin/users` | List users. |
| `GET` | `/api/admin/organizers` | List organizers. |
| `POST` | `/api/admin/users` | Create user. |
| `PATCH` | `/api/admin/users/{user}` | Update user. |
| `DELETE` | `/api/admin/users/{user}` | Delete user. Self-delete is blocked. |
| `GET` | `/api/admin/stats` | Admin dashboard metrics. |
| `GET` | `/api/admin/registrations/events` | Events with registration counts. |
| `GET` | `/api/admin/registrations` | Registration management list. |
| `DELETE` | `/api/admin/registrations/{registration}` | Delete unpaid registration. |
| `POST` | `/api/admin/feedbacks/{feedback}/approve` | Approve feedback. |
| `DELETE` | `/api/admin/feedbacks/{feedback}` | Delete feedback. |

### Client

| Method | Path | Notes |
| --- | --- | --- |
| `POST` | `/api/event-requests` | Submit an event request. One pending request or active event blocks new submissions. |
| `DELETE` | `/api/event-requests/{eventRequest}` | Delete own pending request. |
| `GET` | `/api/client/stats` | Client dashboard data, request groups, event lists, and revenue. |

## Money and Dates

- Money is stored internally in integer cents (`amount_cents`, `ticket_price_cents`).
- API compatibility fields remain decimal-compatible strings or numbers, such as `amount` and `ticket_price`.
- Dates should be sent as ISO-8601 strings.
- Mongo stores dates as BSON dates through Laravel casts.

## Error Shape

Validation errors use Laravel's default `422` JSON response. Domain errors use:

```json
{
  "message": "Human readable error."
}
```

The OpenAPI contract in `openapi.yaml` documents the route inventory and main request/response schemas. It is intentionally frontend-facing; the generated class docs remain available for internal PHP implementation details.
