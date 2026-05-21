<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use MongoDB\Laravel\Eloquent\Model;

/**
 * Modèle Feedback
 *
 * Représente un commentaire soumis par un participant après la fin d'un événement.
 * Le feedback comprend une note numérique et des commentaires textuels facultatifs.
 *
 * @property string $_id ID du document MongoDB
 * @property string $event_id ID de l'événement noté
 * @property string|null $user_id ID du participant ayant soumis le commentaire
 * @property int $rating Note numérique (ex: 1-5)
 * @property string|null $comment Commentaires textuels facultatifs du participant
 * @property string $status Statut de modération (pending, approved)
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Event|null $event Événement associé à ce commentaire
 * @property-read User|null $user Participant ayant fourni le commentaire
 */
class Feedback extends Model
{
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
    protected $table = 'feedbacks';

    /** Constantes de statut */
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    /**
     * Attributs qui sont assignables en masse.
     *
     * @var list<string>
     */
    protected $fillable = [
        'event_id',
        'user_id',
        'rating',
        'comment',
        'status',
    ];

    /**
     * Portée (scope) d'une requête pour inclure uniquement les commentaires approuvés.
     *
     * @param  Builder<Feedback>  $query
     * @return Builder<Feedback>
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_APPROVED);
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
     * Définit la relation pour le participant ayant fourni le commentaire.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
