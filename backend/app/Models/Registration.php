<?php

namespace App\Models;

use App\Models\Concerns\StoresMoneyAsCents;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use MongoDB\Laravel\Eloquent\Model;

/**
 * Modèle Registration
 *
 * Représente l'inscription d'un participant à un événement spécifique.
 * Il suit les détails du billet, le statut du paiement et les informations de présence.
 *
 * @property string $_id ID du document MongoDB
 * @property string $event_id ID de l'événement
 * @property string $user_id ID de l'utilisateur participant
 * @property string $ticket_type Type de billet (ex: early_bird, general, vip)
 * @property string $status Statut de l'inscription (ex: pending, confirmed, cancelled)
 * @property string $payment_status Statut du paiement (ex: unpaid, paid, refunded)
 * @property string|null $ticket_code Code unique assigné au billet
 * @property int $amount_cents Montant total de l'inscription en centimes
 * @property Carbon|null $paid_at Horodatage du paiement complet de l'inscription
 * @property Carbon $registered_at Horodatage de la création de l'inscription
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read float $amount Montant calculé de l'inscription dans l'unité monétaire principale
 * @property-read Event|null $event Événement associé à cette inscription
 * @property-read User|null $user Participant qui s'est inscrit
 * @property-read Payment|null $payment L'enregistrement de paiement principal
 * @property-read Collection<int, Payment> $payments Tous les enregistrements de paiement associés à cette inscription
 */
class Registration extends Model
{
    use StoresMoneyAsCents;

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
    protected $table = 'registrations';

    /**
     * Accesseurs à ajouter à la forme tableau du modèle.
     *
     * @var list<string>
     */
    protected $appends = ['amount'];

    /**
     * Attributs qui doivent être cachés pour la sérialisation.
     *
     * @var list<string>
     */
    protected $hidden = ['amount_cents'];

    /**
     * Attributs qui sont assignables en masse.
     *
     * @var list<string>
     */
    protected $fillable = [
        'event_id',
        'user_id',
        'ticket_type',
        'status',
        'payment_status',
        'ticket_code',
        'amount',
        'amount_cents',
        'paid_at',
        'registered_at',
    ];

    /**
     * Récupère les attributs qui doivent être castés.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'paid_at' => 'datetime',
            'registered_at' => 'datetime',
        ];
    }

    /**
     * Accesseur pour le montant de l'inscription, convertissant les centimes en décimal.
     *
     * @return Attribute<string|null, mixed>
     */
    protected function amount(): Attribute
    {
        return $this->moneyCast('amount_cents');
    }

    /**
     * Définit la relation pour l'événement associé.
     *
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * Définit la relation pour le participant inscrit.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Définit la relation pour l'enregistrement de paiement principal.
     *
     * @return HasOne<Payment, $this>
     */
    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    /**
     * Définit la relation pour tous les enregistrements de paiement associés.
     *
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
