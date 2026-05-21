<?php

namespace App\Models;

use App\Models\Concerns\HasPublicImage;
use App\Models\Concerns\StoresMoneyAsCents;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use MongoDB\Laravel\Eloquent\Model;

/**
 * Modèle EventRequest
 *
 * Représente une demande faite par un client pour l'organisation d'un nouvel événement.
 * Ces demandes sont examinées par les administrateurs et peuvent être approuvées pour créer un Événement.
 *
 * @property string $_id ID du document MongoDB
 * @property string $user_id ID du client ayant soumis la demande
 * @property string $title Titre de l'événement demandé
 * @property string $description Description détaillée de l'événement demandé
 * @property string|null $image_path Chemin vers la bannière/image de l'événement demandé
 * @property Carbon|null $preferred_start Date et heure de début souhaitées
 * @property Carbon|null $preferred_end Date et heure de fin souhaitées
 * @property string|null $location Lieu demandé ou emplacement général
 * @property int $ticket_price_cents Prix du billet proposé en centimes
 * @property string $contact_name Nom de la personne à contacter
 * @property string $contact_email Adresse email de la personne à contacter
 * @property string $contact_phone Numéro de téléphone de la personne à contacter
 * @property string $status Statut de la demande (pending, approved, rejected)
 * @property string|null $rejection_reason Explication si la demande a été rejetée
 * @property Carbon|null $reviewed_at Horodatage du moment où la demande a été examinée
 * @property string|null $reviewed_by_id ID de l'administrateur ayant examiné la demande
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read string|null $image_url URL absolue calculée pour l'image de l'événement
 * @property-read float $ticket_price Prix du billet calculé dans l'unité monétaire principale
 * @property-read User $user Client ayant soumis la demande
 * @property-read User|null $reviewer Administrateur ayant examiné la demande
 * @property-read Event|null $event L'événement réel créé à partir de cette demande
 */
class EventRequest extends Model
{
    use HasPublicImage;
    use StoresMoneyAsCents;

    /** Constantes de statut */
    public const STATUS_APPROVED = 'approved';

    public const STATUS_PENDING = 'pending';

    public const STATUS_REJECTED = 'rejected';

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
    protected $table = 'event_requests';

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
        'user_id',
        'title',
        'description',
        'image_path',
        'preferred_start',
        'preferred_end',
        'location',
        'ticket_price',
        'ticket_price_cents',
        'contact_name',
        'contact_email',
        'contact_phone',
        'status',
        'rejection_reason',
        'reviewed_at',
        'reviewed_by_id',
    ];

    /**
     * Récupère les attributs qui doivent être castés.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'preferred_start' => 'datetime',
            'preferred_end' => 'datetime',
            'reviewed_at' => 'datetime',
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
     * Définit la relation pour le client ayant fait la demande.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Définit la relation pour l'administrateur ayant examiné la demande.
     *
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_id');
    }

    /**
     * Définit la relation pour l'événement résultant si approuvé.
     *
     * @return HasOne<Event, $this>
     */
    public function event(): HasOne
    {
        return $this->hasOne(Event::class);
    }
}
