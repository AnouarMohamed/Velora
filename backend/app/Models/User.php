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
 * Modèle User
 *
 * Représente un utilisateur dans le système avec des rôles et des permissions spécifiques.
 * Ce modèle utilise MongoDB comme stockage de données principal.
 *
 * @property string $_id ID du document MongoDB
 * @property string $name Nom complet de l'utilisateur
 * @property string $email Adresse email unique utilisée pour l'authentification
 * @property string $password Mot de passe haché
 * @property string $role Rôle de l'utilisateur (admin, organizer, participant, client)
 * @property Carbon|null $email_verified_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Event> $organizedEvents Événements dont cet utilisateur est l'organisateur
 * @property-read Collection<int, Registration> $registrations Inscriptions aux événements effectuées par cet utilisateur
 * @property-read Collection<int, AppNotification> $appNotifications Notifications envoyées à cet utilisateur
 */
#[Fillable(['name', 'email', 'password', 'role'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * La connexion à la base de données utilisée par le modèle.
     *
     * @var string
     */
    protected $connection = 'mongodb';

    /**
     * La table/collection associée au modèle.
     *
     * @var string
     */
    protected $table = 'users';

    /**
     * Constante pour le rôle administrateur.
     */
    public const ROLE_ADMIN = 'admin';

    /**
     * Constante pour le rôle organisateur.
     */
    public const ROLE_ORGANIZER = 'organisateur';

    /**
     * Constante pour le rôle participant/utilisateur final.
     */
    public const ROLE_PARTICIPANT = 'participant';

    /**
     * Constante pour le rôle client/demandeur.
     */
    public const ROLE_CLIENT = 'client';

    /**
     * Récupère les attributs qui doivent être castés.
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
     * Vérifie si l'utilisateur a le rôle administrateur.
     */
    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    /**
     * Vérifie si l'utilisateur a le rôle organisateur ou supérieur (admin).
     */
    public function isOrganizer(): bool
    {
        return $this->role === self::ROLE_ORGANIZER || $this->isAdmin();
    }

    /**
     * Définit la relation pour les événements organisés par cet utilisateur.
     *
     * @return HasMany<Event, $this>
     */
    public function organizedEvents(): HasMany
    {
        return $this->hasMany(Event::class, 'organizer_id');
    }

    /**
     * Définit la relation pour les inscriptions effectuées par cet utilisateur.
     *
     * @return HasMany<Registration, $this>
     */
    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class);
    }

    /**
     * Définit la relation pour les notifications dans l'application reçues par cet utilisateur.
     *
     * @return HasMany<AppNotification, $this>
     */
    public function appNotifications(): HasMany
    {
        return $this->hasMany(AppNotification::class);
    }
}
