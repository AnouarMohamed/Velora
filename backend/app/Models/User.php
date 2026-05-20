<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;
use MongoDB\Laravel\Auth\User as Authenticatable;

/**
 * User Model
 *
 * Represents a user in the system with specific roles and permissions.
 * This model uses MongoDB as its primary data store.
 *
 * @property string $_id MongoDB document ID
 * @property string $name Full name of the user
 * @property string $email Unique email address used for authentication
 * @property string $password Hashed password
 * @property string $role User role (admin, organizer, participant, client)
 * @property Carbon|null $email_verified_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection|Event[] $organizedEvents Events where this user is the organizer
 * @property-read Collection|Registration[] $registrations Event registrations made by this user
 * @property-read Collection|AppNotification[] $appNotifications Notifications sent to this user
 */
#[Fillable(['name', 'email', 'password', 'role'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The database connection used by the model.
     *
     * @var string
     */
    protected $connection = 'mongodb';

    /**
     * The table/collection associated with the model.
     *
     * @var string
     */
    protected $table = 'users';

    /**
     * Administrator role constant.
     */
    public const ROLE_ADMIN = 'admin';

    /**
     * Organizer role constant.
     */
    public const ROLE_ORGANIZER = 'organizer';

    /**
     * Participant/End-user role constant.
     */
    public const ROLE_PARTICIPANT = 'participant';

    /**
     * Client/Requestor role constant.
     */
    public const ROLE_CLIENT = 'client';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Check if the user has the administrator role.
     */
    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    /**
     * Check if the user has the organizer role or higher (admin).
     */
    public function isOrganizer(): bool
    {
        return $this->role === self::ROLE_ORGANIZER || $this->isAdmin();
    }

    /**
     * Define the relationship for events organized by this user.
     *
     * @return HasMany
     */
    public function organizedEvents()
    {
        return $this->hasMany(Event::class, 'organizer_id');
    }

    /**
     * Define the relationship for registrations made by this user.
     *
     * @return HasMany
     */
    public function registrations()
    {
        return $this->hasMany(Registration::class);
    }

    /**
     * Define the relationship for in-app notifications received by this user.
     *
     * @return HasMany
     */
    public function appNotifications()
    {
        return $this->hasMany(AppNotification::class);
    }
}
