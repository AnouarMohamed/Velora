<?php

use App\Http\Controllers\Api\AdminEventController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\EventActivityController;
use App\Http\Controllers\Api\EventRequestController;
use App\Http\Controllers\Api\EventTaskController;
use App\Http\Controllers\Api\FeedbackController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\OrganizerEventController;
use App\Http\Controllers\Api\PublicEventController;
use App\Http\Controllers\Api\RegistrationController;
use App\Http\Controllers\Api\StaffRegistrationController;
use App\Http\Controllers\Api\StatsController;
use App\Http\Controllers\Api\UserAdminController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class);

Route::get('/diag', function () {
    return [
        'php' => PHP_VERSION,
        'mongodb_extension' => extension_loaded('mongodb'),
        'redis_extension' => extension_loaded('redis'),
    ];
});

Route::post('/register', [AuthController::class, 'register'])
    ->middleware('throttle:auth.register')
    ->name('register');

Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:auth.login')
    ->name('login');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead']);

    Route::get('/events/browse', [PublicEventController::class, 'browse']);
    Route::get('/events/{event}', [PublicEventController::class, 'show']);
    Route::get('/events/{event}/feedbacks', [FeedbackController::class, 'index']);

    Route::middleware('role:participant')->group(function () {
        Route::post('/events/{event}/register', [RegistrationController::class, 'store']);
        Route::get('/events/{event}/my-registration', [RegistrationController::class, 'myRegistrationForEvent']);
        Route::get('/my-registrations', [RegistrationController::class, 'myRegistrations']);
        Route::post('/registrations/{registration}/pay', [RegistrationController::class, 'pay']);
        Route::delete('/registrations/{registration}', [RegistrationController::class, 'destroy']);
        Route::get('/registrations/{registration}/ticket', [RegistrationController::class, 'ticket']);
        Route::post('/events/{event}/feedback', [FeedbackController::class, 'store']);
    });

    Route::middleware('role:organizer')->group(function () {
        Route::get('/organizer/registrations/events', [StaffRegistrationController::class, 'eventsForOrganizer']);
        Route::get('/organizer/registrations', [StaffRegistrationController::class, 'indexForOrganizer']);
        Route::delete('/organizer/registrations/{registration}', [StaffRegistrationController::class, 'destroyForOrganizer']);
    });

    Route::middleware('role:organizer,admin')->group(function () {
        Route::get('/organizer/events', [OrganizerEventController::class, 'index']);
        Route::post('/organizer/events', [OrganizerEventController::class, 'store']);
        Route::patch('/organizer/events/{event}', [OrganizerEventController::class, 'update']);
        Route::patch('/organizer/events/{event}/capacity', [OrganizerEventController::class, 'updateCapacity']);
        Route::post('/organizer/events/{event}/request-publication', [OrganizerEventController::class, 'requestPublication']);

        Route::get('/organizer/events/{event}/tasks', [EventTaskController::class, 'index']);
        Route::post('/organizer/events/{event}/tasks', [EventTaskController::class, 'store']);
        Route::patch('/organizer/events/{event}/tasks/{eventTask}', [EventTaskController::class, 'update']);
        Route::delete('/organizer/events/{event}/tasks/{eventTask}', [EventTaskController::class, 'destroy']);

        Route::get('/organizer/events/{event}/activities', [EventActivityController::class, 'index']);
        Route::post('/organizer/events/{event}/activities', [EventActivityController::class, 'store']);
        Route::patch('/organizer/events/{event}/activities/{eventActivity}', [EventActivityController::class, 'update']);
        Route::delete('/organizer/events/{event}/activities/{eventActivity}', [EventActivityController::class, 'destroy']);
    });

    Route::middleware('role:admin')->group(function () {
        Route::get('/admin/events', [AdminEventController::class, 'index']);
        Route::get('/admin/organizer-events', [AdminEventController::class, 'organizerSpace']);
        Route::get('/admin/my-events', [AdminEventController::class, 'assignedToMe']);
        Route::delete('/admin/events/{event}', [AdminEventController::class, 'destroy']);
        Route::patch('/admin/events/{event}/assign-organizer', [AdminEventController::class, 'assignOrganizer']);

        Route::patch('/admin/events/{event}', [AdminEventController::class, 'update']);
        Route::patch('/admin/events/{event}/capacity', [AdminEventController::class, 'updateCapacity']);
        Route::post('/admin/events/{event}/approve-publication', [AdminEventController::class, 'approvePublication']);

        Route::get('/admin/events/{event}/tasks', [EventTaskController::class, 'index']);
        Route::post('/admin/events/{event}/tasks', [EventTaskController::class, 'store']);
        Route::patch('/admin/events/{event}/tasks/{eventTask}', [EventTaskController::class, 'update']);
        Route::delete('/admin/events/{event}/tasks/{eventTask}', [EventTaskController::class, 'destroy']);

        Route::get('/admin/events/{event}/activities', [EventActivityController::class, 'index']);
        Route::post('/admin/events/{event}/activities', [EventActivityController::class, 'store']);
        Route::patch('/admin/events/{event}/activities/{eventActivity}', [EventActivityController::class, 'update']);
        Route::delete('/admin/events/{event}/activities/{eventActivity}', [EventActivityController::class, 'destroy']);

        Route::get('/admin/event-requests', [EventRequestController::class, 'index']);
        Route::post('/admin/event-requests/{eventRequest}/review', [EventRequestController::class, 'review']);

        Route::get('/admin/users', [UserAdminController::class, 'index']);
        Route::get('/admin/organizers', [UserAdminController::class, 'organizers']);
        Route::post('/admin/users', [UserAdminController::class, 'store']);
        Route::patch('/admin/users/{user}', [UserAdminController::class, 'update']);
        Route::delete('/admin/users/{user}', [UserAdminController::class, 'destroy']);

        Route::get('/admin/stats', [StatsController::class, 'admin']);

        Route::get('/admin/registrations/events', [StaffRegistrationController::class, 'eventsForAdmin']);
        Route::get('/admin/registrations', [StaffRegistrationController::class, 'indexForAdmin']);
        Route::delete('/admin/registrations/{registration}', [StaffRegistrationController::class, 'destroyForAdmin']);

        Route::post('/admin/feedbacks/{feedback}/approve', [FeedbackController::class, 'approve']);
        Route::delete('/admin/feedbacks/{feedback}', [FeedbackController::class, 'destroy']);
    });

    Route::middleware('role:client')->group(function () {
        Route::post('/event-requests', [EventRequestController::class, 'store']);
        Route::delete('/event-requests/{eventRequest}', [EventRequestController::class, 'destroy']);
        Route::get('/client/stats', [StatsController::class, 'client']);
    });
});
