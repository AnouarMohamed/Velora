<?php

namespace App\Models;

use App\Models\Concerns\StoresMoneyAsCents;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use MongoDB\Laravel\Eloquent\Model;

/**
 * Modèle Event
 *
 * Représente un événement géré dans le système. Les événements peuvent être créés par des clients
 * (sous forme de demandes) puis gérés par des organisateurs ou des administrateurs.
 *
 * @property string $_id ID du document MongoDB
 * @property string|null $event_request_id ID de la demande d'événement d'origine
 * @property string|null $organizer_id ID de l'organisateur assigné
 * @property string $created_by ID de l'utilisateur ayant créé l'enregistrement de l'événement
 * @property string $title Titre de l'événement
 * @property string|null $description Description détaillée de l'événement
 * @property string|null $image_path Chemin vers l'image de l'événement dans le stockage
 * @property string|null $location Lieu physique ou nom de la salle
 * @property string|null $room Salle spécifique ou sous-emplacement
 * @property Carbon|null $start_at Date et heure de début de l'événement
 * @property Carbon|null $end_at Date et heure de fin de l'événement
 * @property int $capacity Nombre maximum de participants
 * @property int $registered_count Nombre actuel de participants inscrits
 * @property int $ticket_price_cents Prix du billet en centimes
 * @property string $status Statut de l'événement (draft, pending_publication, published, cancelled, completed)
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read string|null $image_url URL absolue calculée pour l'image de l'événement
 * @property-read float $ticket_price Prix du billet calculé dans l'unité monétaire principale
 * @property-read EventRequest|null $eventRequest Demande d'origine pour cet événement
 * @property-read User|null $organizer Organisateur assigné
 * @property-read User|null $creator Utilisateur ayant créé l'enregistrement
 * @property-read Collection<int, EventTask> $tasks Tâches de planification associées
 * @property-read Collection<int, EventActivity> $activities Activités d'événement associées
 * @property-read Collection<int, Registration> $registrations Inscriptions des participants
 * @property-read Collection<int, Feedback> $feedbacks Commentaires des participants
 */
class Event extends Model
{
    use StoresMoneyAsCents;

    /** Constantes de statut */
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PENDING_PUBLICATION = 'pending_publication';

    public const STATUS_PUBLISHED = 'published';

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
    protected $table = 'events';

    /**
     * Accesseurs à ajouter à la forme tableau du modèle.
     *
     * @var list<string>
     */
    protected $appends = ['image_url', 'ticket_price'];

    /**
     * Attributs qui doivent être cachés pour la sérialisation.
     *
     * @var list<string>
     */
    protected $hidden = ['ticket_price_cents'];

    /**
     * Attributs qui sont assignables en masse.
     *
     * @var list<string>
     */
    protected $fillable = [
        'event_request_id',
        'organizer_id',
        'created_by',
        'title',
        'description',
        'image_path',
        'location',
        'room',
        'start_at',
        'end_at',
        'capacity',
        'registered_count',
        'ticket_price',
        'ticket_price_cents',
        'status',
    ];

    /**
     * Récupère les attributs qui doivent être castés.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_at' => 'datetime',
            'end_at' => 'datetime',
        ];
    }

    /**
     * Accesseur pour le prix du billet, convertissant les centimes en décimal.
     *
     * @return Attribute<string|null, mixed>
     */
    protected function ticketPrice(): Attribute
    {
        return $this->moneyCast('ticket_price_cents');
    }

    /**
     * Accesseur pour l'URL de l'image.
     */
    public function getImageUrlAttribute(): ?string
    {
        $path = $this->attributes['image_path'] ?? null;
        if (is_string($path) && $path !== '') {
            if (str_starts_with($path, 'data:image/') || str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
                return $path;
            }

            return url('/storage/'.ltrim(str_replace('\\', '/', $path), '/'));
        }

        if ($this->relationLoaded('eventRequest') && $this->eventRequest?->image_path) {
            return $this->eventRequest->image_url;
        }

        return null;
    }

    /**
     * Récupère la demande d'événement d'origine.
     *
     * @return BelongsTo<EventRequest, $this>
     */
    public function eventRequest(): BelongsTo
    {
        return $this->belongsTo(EventRequest::class);
    }

    /**
     * Récupère l'organisateur assigné.
     *
     * @return BelongsTo<User, $this>
     */
    public function organizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'organizer_id');
    }

    /**
     * Récupère l'utilisateur ayant créé l'enregistrement de l'événement.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Récupère les tâches de planification associées.
     *
     * @return HasMany<EventTask, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(EventTask::class);
    }

    /**
     * Récupère les activités d'événement associées.
     *
     * @return HasMany<EventActivity, $this>
     */
    public function activities(): HasMany
    {
        return $this->hasMany(EventActivity::class);
    }

    /**
     * Récupère les inscriptions des participants.
     *
     * @return HasMany<Registration, $this>
     */
    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class);
    }

    /**
     * Récupère les commentaires des participants.
     *
     * @return HasMany<Feedback, $this>
     */
    public function feedbacks(): HasMany
    {
        return $this->hasMany(Feedback::class);
    }

    /**
     * Vérifie si un utilisateur donné est un organisateur de cet événement.
     * Les administrateurs sont toujours considérés comme des organisateurs.
     */
    public function isOrganizer(User $user): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $this->organizer_id === $user->id || $this->created_by === $user->id;
    }

    /**
     * Vérifie si l'événement est déjà terminé en fonction de l'heure de fin.
     */
    public function isFinished(): bool
    {
        $endsAt = $this->end_at ?? $this->start_at;

        return $endsAt !== null && $endsAt->lte(now());
    }

    /**
     * Portée (scope) d'une requête pour inclure uniquement les événements qui ne sont pas encore terminés.
     *
     * @param  Builder<Event>  $query
     * @return Builder<Event>
     */
    public function scopeNotFinished(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->where('end_at', '>=', now())
                ->orWhere(function ($q2) {
                    $q2->whereNull('end_at')->where('start_at', '>=', now());
                });
        });
    }

    /**
     * Portée (scope) d'une requête pour inclure uniquement les événements déjà terminés.
     *
     * @param  Builder<Event>  $query
     * @return Builder<Event>
     */
    public function scopeFinished(Builder $query): Builder
    {
        return $query->where(function ($q) {
            $q->where('end_at', '<', now())
                ->orWhere(function ($q2) {
                    $q2->whereNull('end_at')->where('start_at', '<', now());
                });
        });
    }
}
