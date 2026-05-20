# UML Workflow Documentation

This file documents the main application workflows with UML-style Mermaid diagrams. It is intended for developers who need to understand the behavior before reading the PHP files.

Mermaid is used so the diagrams remain versionable in Git. Most diagrams use sequence or activity-style flowcharts because the backend is workflow-heavy.

## Global Use-Case View

```mermaid
flowchart LR
    Admin[Admin]
    Organizer[Organizer]
    Participant[Participant]
    Client[Client]

    Auth((Authenticate))
    Browse((Browse events))
    Notifications((Manage notifications))
    ClientRequest((Request event))
    ReviewRequest((Review event request))
    ManageEvents((Manage events))
    PlanEvent((Manage tasks and activities))
    RegisterEvent((Register for event))
    PayTicket((Pay registration))
    Ticket((Download ticket))
    Feedback((Submit feedback))
    ModerateFeedback((Moderate feedback))
    UserAdmin((Manage users))
    Stats((View stats))

    Admin --> Auth
    Organizer --> Auth
    Participant --> Auth
    Client --> Auth

    Admin --> Browse
    Organizer --> Browse
    Participant --> Browse
    Client --> Browse

    Admin --> Notifications
    Organizer --> Notifications
    Participant --> Notifications
    Client --> Notifications

    Client --> ClientRequest
    Admin --> ReviewRequest
    Admin --> ManageEvents
    Organizer --> ManageEvents
    Admin --> PlanEvent
    Organizer --> PlanEvent
    Participant --> RegisterEvent
    Participant --> PayTicket
    Participant --> Ticket
    Participant --> Feedback
    Admin --> ModerateFeedback
    Admin --> UserAdmin
    Admin --> Stats
    Client --> Stats
```

## 1. Registration Workflow

```mermaid
sequenceDiagram
    actor Visitor
    participant API as AuthController
    participant Request as RegisterRequest
    participant Users as UserWriteService
    participant User as User model
    participant Token as PersonalAccessToken

    Visitor->>API: POST /api/register
    API->>Request: validate name, email, password, role
    Request-->>API: validated payload
    API->>Users: create(payload)
    Users->>User: create user with hashed password
    User-->>Users: user
    Users-->>API: user
    API->>Token: createToken("spa")
    Token-->>API: plain text bearer token
    API-->>Visitor: 201 { token, user }
```

Rules:

- Public users can register only as `participant` or `client`.
- Admin and organizer accounts are created by an admin or by seed data.
- The `users_email_unique` Mongo index prevents duplicate emails.

## 2. Login And Token Authentication Workflow

```mermaid
sequenceDiagram
    actor User
    participant API as AuthController
    participant Request as LoginRequest
    participant Auth as Laravel Auth
    participant Token as PersonalAccessToken
    participant Protected as Protected API route

    User->>API: POST /api/login
    API->>Request: validate email and password
    Request-->>API: credentials
    API->>Auth: attempt(credentials)
    alt invalid credentials
        API-->>User: 422 { message: "Identifiants invalides." }
    else valid credentials
        API->>Token: createToken("spa")
        Token-->>API: bearer token
        API-->>User: 200 { token, user }
        User->>Protected: Authorization: Bearer token
        Protected-->>User: authenticated JSON response
    end
```

Rules:

- Login is rate limited.
- Tokens are stored in the Mongo `personal_access_tokens` collection.
- API consumers must send `Authorization: Bearer <token>`.

## 3. Logout Workflow

```mermaid
sequenceDiagram
    actor User
    participant API as AuthController
    participant Token as CurrentAccessToken

    User->>API: POST /api/logout with bearer token
    API->>Token: delete current token
    Token-->>API: deleted
    API-->>User: 200 { message }
```

Rules:

- Logout revokes only the token used by the request.
- Other tokens for the same user are not revoked.

## 4. Notification Workflow

```mermaid
flowchart TD
    A[Workflow creates a domain event] --> B[NotificationService chooses recipients]
    B --> C[Create app_notifications documents]
    C --> D[User calls GET /api/notifications]
    D --> E[NotificationController returns current user inbox]
    E --> F{User marks notifications read?}
    F -- one notification --> G["POST notification read route"]
    F -- all notifications --> H[POST /api/notifications/read-all]
    G --> I[Set read_at]
    H --> I
    I --> J[Unread count decreases]
```

Rules:

- A user can read and update only their own notifications.
- Notification data is stored as structured metadata in `data`.

## 5. Public Event Browsing Workflow

```mermaid
flowchart TD
    A[Authenticated user requests /api/events/browse] --> B[EventIndexRequest validates q max 120 chars]
    B --> C[Query published events]
    C --> D[Apply optional search]
    D --> E[Order and paginate]
    E --> F[Return event list with image_url and ticket_price]
```

Rules:

- Browsing is authenticated.
- Only published events are listed.
- Money is exposed as `ticket_price`; storage remains `ticket_price_cents`.

## 6. Event Detail Visibility Workflow

```mermaid
flowchart TD
    A["User requests event detail route"] --> B{Event published?}
    B -- yes --> C[Return event detail]
    B -- no --> D{User manages event?}
    D -- yes --> C
    D -- no --> E[Return hidden/not found response]
```

Rules:

- Published events are visible to authenticated users.
- Draft or pending events are visible only to admins or managing organizers.

## 7. Organizer Event Creation Workflow

```mermaid
sequenceDiagram
    actor Organizer
    participant API as EventController
    participant Request as StoreEventRequest
    participant Service as EventManagementService
    participant Storage as EventImageStorage
    participant Event as Event model

    Organizer->>API: POST /api/organizer/events
    API->>Request: validate event payload
    Request-->>API: validated data
    API->>Service: create(actor, data)
    Service->>Storage: store image if present
    Storage-->>Service: image_path
    Service->>Event: create draft event
    Event-->>Service: event
    Service-->>API: event
    API-->>Organizer: 201 event
```

Rules:

- Organizer-created events remain `draft`.
- Organizers cannot directly publish by sending `status=published`.
- Image data is validated before storage.

## 8. Admin Event Creation Workflow

```mermaid
sequenceDiagram
    actor Admin
    participant API as EventController
    participant Request as StoreEventRequest
    participant Service as EventManagementService
    participant Event as Event model

    Admin->>API: POST /api/organizer/events or admin event route
    API->>Request: validate payload
    API->>Service: create(admin, data)
    alt status is published
        Service->>Event: create published event
    else other valid status
        Service->>Event: create event with requested status
    end
    Event-->>API: event
    API-->>Admin: 201 event
```

Rules:

- Admins can create published events.
- Admins can later assign organizers.

## 9. Event Update Workflow

```mermaid
flowchart TD
    A[Actor submits PATCH event] --> B[UpdateEventRequest validates data]
    B --> C{Actor is admin or event manager?}
    C -- no --> D[403 domain error]
    C -- yes --> E{Actor is organizer and requests published?}
    E -- yes --> F[Remove/deny direct publish]
    E -- no --> G[Apply safe updates]
    F --> G
    G --> H[Store replacement image if provided]
    H --> I[Return updated event]
```

Rules:

- Ownership is checked in the service.
- Organizer publish must go through publication request and admin approval.
- Capacity has its own stricter workflow.

## 10. Capacity Update Workflow

```mermaid
flowchart TD
    A[Manager submits capacity change] --> B[UpdateEventCapacityRequest validates capacity]
    B --> C[Load current registered_count]
    C --> D{new capacity >= registered_count?}
    D -- no --> E[422 capacity domain error]
    D -- yes --> F[Update capacity]
    F --> G[Return event]
```

Rules:

- Capacity can never be reduced below the number of registered participants.
- This keeps existing registrations valid.

## 11. Publication Request Workflow

```mermaid
flowchart TD
    A[Organizer requests publication] --> B{Organizer manages event?}
    B -- no --> C[403]
    B -- yes --> D{Event can be submitted?}
    D -- no --> E[422 domain error]
    D -- yes --> F[Set status pending_publication]
    F --> G[Notify admins]
    G --> H[Return event]
```

Rules:

- Publication is a two-step workflow for organizers.
- Admin approval is required before participants can register.

## 12. Publication Approval Workflow

```mermaid
flowchart TD
    A[Admin approves publication] --> B{Event is publishable?}
    B -- no --> C[422 domain error]
    B -- yes --> D[Set status published]
    D --> E[Notify organizer or stakeholders]
    E --> F[Event becomes visible in browse list]
```

Rules:

- Only admins can approve publication.
- Published events become registerable if other registration rules pass.

## 13. Admin Organizer Assignment Workflow

```mermaid
sequenceDiagram
    actor Admin
    participant API as EventController
    participant Request as AssignEventOrganizerRequest
    participant Service as EventManagementService
    participant Event as Event model
    participant User as User model

    Admin->>API: PATCH admin event organizer assignment route
    API->>Request: validate organizer_id
    Request->>User: verify user is organizer
    API->>Service: assignOrganizer(event, organizer)
    Service->>Event: update organizer_id
    Event-->>Service: updated event
    Service-->>API: event
    API-->>Admin: 200 event
```

Rules:

- The assigned user must have the organizer role.
- Admins can still manage all events even when not assigned.

## 14. Client Event Request Submission Workflow

```mermaid
sequenceDiagram
    actor Client
    participant API as EventRequestController
    participant Request as StoreClientEventRequest
    participant Eligibility as EventRequestEligibilityService
    participant Storage as EventRequestImageStorage
    participant RequestModel as EventRequest

    Client->>API: POST /api/event-requests
    API->>Request: validate payload
    Request-->>API: validated data
    API->>Eligibility: ensure client may submit
    Eligibility-->>API: allowed
    API->>Storage: store image if present
    Storage-->>API: image_path
    API->>RequestModel: create pending request
    RequestModel-->>API: event request
    API-->>Client: 201 event request
```

Rules:

- A client with a pending request is blocked from submitting another.
- A client with an active event is blocked from submitting another.
- Contact fields default from the authenticated client when omitted.

## 15. Client Event Request Deletion Workflow

```mermaid
flowchart TD
    A[Client deletes event request] --> B{Client owns request?}
    B -- no --> C[403 or hidden response]
    B -- yes --> D{Request is still pending?}
    D -- no --> E[422 domain error]
    D -- yes --> F[Delete stored image if present]
    F --> G[Delete request]
    G --> H[Return success message]
```

Rules:

- Reviewed requests are audit history and cannot be deleted by the client.

## 16. Admin Event Request Approval Workflow

```mermaid
sequenceDiagram
    actor Admin
    participant API as EventRequestController
    participant Request as ReviewEventRequestRequest
    participant Service as EventRequestReviewService
    participant Mongo as Mongo transaction
    participant EventRequest as EventRequest
    participant Event as Event
    participant Notify as NotificationService

    Admin->>API: POST admin event request review route with decision=approved
    API->>Request: validate decision
    API->>Service: approve(eventRequest, admin)
    Service->>Mongo: start transaction
    Mongo->>EventRequest: update status pending -> approved
    Mongo->>Event: create draft event
    Mongo-->>Service: commit
    Service->>Notify: notify client
    Service-->>API: { event_request, event }
    API-->>Admin: 200 approval response
```

Rules:

- Approval is atomic with draft event creation.
- The status update is conditional so a reviewed request cannot be reviewed again.

## 17. Admin Event Request Rejection Workflow

```mermaid
flowchart TD
    A[Admin rejects request] --> B[ReviewEventRequestRequest validates decision and reason]
    B --> C{Request is pending?}
    C -- no --> D[422 already reviewed]
    C -- yes --> E[Set status rejected]
    E --> F[Store rejection_reason and reviewed metadata]
    F --> G[Notify client]
    G --> H[Return reviewed request]
```

Rules:

- Rejection requires a rejection reason.
- No event is created.

## 18. Event Task Workflow

```mermaid
flowchart TD
    A[Admin or organizer opens event tasks] --> B{Actor manages event?}
    B -- no --> C[403]
    B -- yes --> D[List tasks ordered for planning]
    D --> E{Create, update, or delete?}
    E -- create --> F[StoreEventTaskRequest validates task]
    E -- update --> G[UpdateEventTaskRequest validates task]
    E -- delete --> H[Verify task belongs to route event]
    F --> I[Create task]
    G --> J[Update task]
    H --> K[Delete task]
```

Rules:

- Tasks are always scoped to an event.
- A task from a different event is rejected even if the user manages both events.

## 19. Event Activity Workflow

```mermaid
flowchart TD
    A[Admin or organizer opens activities] --> B{Actor manages event?}
    B -- no --> C[403]
    B -- yes --> D[List activities by sort_order and starts_at]
    D --> E{Create, update, or delete?}
    E -- create --> F[Validate title, starts_at, ends_at]
    E -- update --> G[Validate changed fields]
    E -- delete --> H[Verify activity belongs to event]
    F --> I{ends_at before starts_at?}
    G --> I
    I -- yes --> J[422 validation error]
    I -- no --> K[Persist activity]
    H --> L[Delete activity]
```

Rules:

- Activities define the event timeline.
- End time cannot be earlier than start time.

## 20. Participant Registration Workflow

```mermaid
sequenceDiagram
    actor P as Participant
    participant API as RegistrationController
    participant Service as ParticipantRegistrationService
    participant Core as RegistrationService
    participant Mongo as Mongo transaction
    participant Event as Event
    participant Registration as Registration
    participant Payment as Payment
    participant Notify as NotificationService

    P->>API: POST event registration route
    API->>Service: register(participant, event)
    Service->>Core: register(participant, event)
    Core->>Mongo: start transaction
    Mongo->>Event: verify published and capacity available
    Mongo->>Registration: check duplicate event/user
    Mongo->>Event: conditional increment registered_count
    Mongo->>Registration: create registration with unique ticket_code
    alt free event
        Mongo->>Payment: create completed free payment
    end
    Mongo-->>Core: commit
    Core->>Notify: notify admins/organizers
    Core-->>Service: registration
    Service-->>API: registration
    API-->>P: 201 registration
```

Rules:

- The event must be published.
- `registered_count` increments only when capacity is still available.
- `registrations_event_user_unique` prevents duplicate registrations.
- `registrations_ticket_code_unique` prevents duplicate ticket codes.

## 21. Full Capacity Registration Failure Workflow

```mermaid
flowchart TD
    A[Participant requests registration] --> B[Load current event]
    B --> C{registered_count < capacity?}
    C -- no --> D[Return 422 event full]
    C -- yes --> E[Try atomic increment where count < capacity]
    E --> F{increment succeeded?}
    F -- no --> D
    F -- yes --> G[Create registration]
```

Rules:

- The initial capacity check is not enough by itself.
- The conditional update is the race-condition protection.

## 22. Duplicate Registration Failure Workflow

```mermaid
flowchart TD
    A[Participant requests registration] --> B[Service checks existing event/user registration]
    B --> C{Existing registration found?}
    C -- yes --> D[Return 422 with existing registration]
    C -- no --> E[Create registration]
    E --> F{Mongo unique index conflict?}
    F -- no --> G[Registration succeeds]
    F -- yes --> H[Translate duplicate key into user-friendly 422]
```

Rules:

- The service check gives a clean user experience.
- The unique index is the final database-level protection.

## 23. Payment Workflow

```mermaid
sequenceDiagram
    actor P as Participant
    participant API as RegistrationController
    participant Service as ParticipantRegistrationService
    participant Core as RegistrationService
    participant Mongo as Mongo transaction
    participant Registration as Registration
    participant Payment as Payment
    participant Notify as NotificationService

    P->>API: POST registration payment route
    API->>Service: pay(participant, registration)
    Service->>Core: pay(registration)
    Core->>Mongo: start transaction
    Mongo->>Registration: update pending -> paid
    Mongo->>Payment: create completed card_mock payment
    Mongo-->>Core: commit
    Core->>Notify: notify admins/organizers
    Core-->>API: paid registration
    API-->>P: 200 registration
```

Rules:

- Already paid registrations return a domain response instead of double-charging.
- Payments are stored as integer cents.

## 24. Participant Cancellation Workflow

```mermaid
flowchart TD
    A[Participant deletes registration] --> B{Participant owns registration?}
    B -- no --> C[403]
    B -- yes --> D{payment_status is paid?}
    D -- yes --> E[422 cannot cancel paid registration]
    D -- no --> F[Delete registration]
    F --> G[Decrement event registered_count if above zero]
    G --> H[Return success message]
```

Rules:

- Paid registrations cannot be cancelled through this endpoint.
- Cancelling an unpaid registration frees capacity.

## 25. Ticket Download Workflow

```mermaid
flowchart TD
    A[Participant requests ticket] --> B{Owns registration?}
    B -- no --> C[403]
    B -- yes --> D{Registration paid?}
    D -- no --> E[422 unpaid ticket]
    D -- yes --> F[Build JSON ticket payload]
    F --> G[Stream ticket download]
```

Rules:

- Tickets are available only after payment.
- The current implementation returns a JSON ticket file.

## 26. Staff Registration Management Workflow

```mermaid
flowchart TD
    A[Organizer or admin opens registration management] --> B{Actor role}
    B -- admin --> C[Can query registrations for all events]
    B -- organizer --> D[Can query only managed events]
    C --> E[Optional event_id filter validated as Mongo ObjectId]
    D --> E
    E --> F[List registrations with event/user data]
    F --> G{Delete registration?}
    G -- yes --> H{Registration unpaid and actor manages event?}
    H -- no --> I[403 or 422 domain error]
    H -- yes --> J[Delete registration and decrement count]
```

Rules:

- Organizer views are scoped to their own events.
- Admin views are global.
- Staff deletion is still blocked for paid registrations.

## 27. Feedback Submission Workflow

```mermaid
sequenceDiagram
    actor P as Participant
    participant API as FeedbackController
    participant Request as StoreFeedbackRequest
    participant Service as FeedbackService
    participant Registration as Registration
    participant Feedback as Feedback
    participant Notify as NotificationService

    P->>API: POST event feedback route
    API->>Request: validate rating and comment
    API->>Service: submit(participant, event, data)
    Service->>Registration: verify paid registration
    alt no paid registration
        Service-->>API: 403 domain error
    else eligible
        Service->>Feedback: create pending feedback
        Service->>Notify: notify admins/organizers
        Service-->>API: feedback
        API-->>P: 201 feedback
    end
```

Rules:

- Only paid participants can submit feedback.
- Feedback starts as `pending`.
- A unique index prevents duplicate feedback for the same event and user.

## 28. Feedback Moderation Workflow

```mermaid
flowchart TD
    A[Admin reviews pending feedback] --> B{Approve or delete?}
    B -- approve --> C[Set status approved]
    C --> D[Notify author and event client when relevant]
    D --> E[Feedback becomes visible publicly]
    B -- delete --> F[Delete feedback]
    F --> G[Feedback removed from all lists]
```

Rules:

- Public feedback lists show approved feedback.
- Admins can see pending feedback for moderation.

## 29. Admin Stats Workflow

```mermaid
flowchart TD
    A[Admin calls /api/admin/stats] --> B[Count users by role]
    B --> C[Count events by status]
    C --> D[Count pending requests and feedback]
    D --> E[Sum completed payment cents]
    E --> F[Return dashboard payload]
```

Rules:

- Revenue uses completed payments only.
- Amounts are summed from cents, not decimal display fields.

## 30. Client Stats Workflow

```mermaid
flowchart TD
    A[Client calls /api/client/stats] --> B[Find client event requests]
    B --> C[Find events created from approved requests]
    C --> D[Group requests by status]
    D --> E[Sum revenue for owned/requested events]
    E --> F[Return client dashboard payload]
```

Rules:

- Client stats are limited to that client's requests/events.
- Client revenue is derived from completed payments on their events.

## 31. User Administration Workflow

```mermaid
sequenceDiagram
    actor Admin
    participant API as UserAdminController
    participant Request as User FormRequest
    participant Service as UserWriteService
    participant User as User model

    Admin->>API: GET/POST/PATCH/DELETE /api/admin/users
    API->>Request: validate role, email, password, filters
    API->>Service: create/update/delete user
    alt self delete requested
        Service-->>API: 422 self-delete blocked
    else valid operation
        Service->>User: persist change
        User-->>Service: user or delete result
        Service-->>API: result
        API-->>Admin: JSON response
    end
```

Rules:

- Admins manage users.
- Self-delete is blocked.
- Email uniqueness is enforced by validation and Mongo index.

## 32. Health Check Workflow

```mermaid
flowchart TD
    A[GET /api/health] --> B[Check MongoDB connection]
    B --> C[Check Redis connection]
    C --> D{All dependencies ok?}
    D -- yes --> E[200 status ok]
    D -- no --> F[503 status degraded]
    E --> G[Return service status report]
    F --> G
```

Rules:

- Health checks are public.
- The endpoint is used by Docker healthchecks and local smoke testing.

## 33. Cross-Cutting API Middleware Workflow

```mermaid
flowchart TD
    A[API request enters Laravel] --> B[AttachRequestId middleware]
    B --> C[Role/auth middleware if route requires it]
    C --> D[Controller or FormRequest]
    D --> E[Response generated]
    E --> F[ApplyApiSecurityHeaders middleware]
    F --> G[JSON response with request id and security headers]
```

Rules:

- API errors return JSON.
- A safe inbound `X-Request-Id` is reused; otherwise a new request id is generated.
- Security headers are attached to success and error responses.

## 34. Workflow Ownership Summary

| Workflow | Main Actor | Main Service | Main Collections |
| --- | --- | --- | --- |
| Register account | visitor | `UserWriteService` | `users`, `personal_access_tokens` |
| Login/logout | any user | Laravel Auth/Sanctum | `users`, `personal_access_tokens` |
| Browse events | authenticated user | `EventManagementService` and query layer | `events` |
| Create/update event | admin, organizer | `EventManagementService` | `events`, `users` |
| Request publication | organizer | `EventManagementService` | `events`, `app_notifications` |
| Approve publication | admin | `EventManagementService` | `events`, `app_notifications` |
| Submit event request | client | `EventRequestSubmissionService` | `event_requests`, `events` |
| Review event request | admin | `EventRequestReviewService` | `event_requests`, `events` |
| Manage tasks | admin, organizer | `EventTaskService` | `event_tasks`, `events` |
| Manage activities | admin, organizer | `EventActivityService` | `event_activities`, `events` |
| Register for event | participant | `RegistrationService` | `events`, `registrations`, `payments` |
| Pay registration | participant | `RegistrationService` | `registrations`, `payments` |
| Cancel registration | participant | `RegistrationService` | `registrations`, `events` |
| Staff manage registrations | admin, organizer | `StaffRegistrationService` | `registrations`, `events` |
| Submit feedback | participant | `FeedbackService` | `feedbacks`, `registrations`, `events` |
| Moderate feedback | admin | `FeedbackService` | `feedbacks`, `app_notifications` |
| Notifications | all authenticated users | `NotificationService` | `app_notifications` |
| Stats | admin, client | `AdminStatsService`, `ClientStatsService` | `users`, `events`, `event_requests`, `registrations`, `payments`, `feedbacks` |
