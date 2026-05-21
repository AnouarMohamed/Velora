<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use MongoDB\Laravel\Eloquent\Model;

/**
 * Modèle EventActivity
 *
 * Représente une activité spécifique ou un élément du programme au sein d'un événement.
 * Les activités aident à diviser un événement en une chronologie pour les participants.
 *
 * @property string $_id ID du document MongoDB
 * @property string $event_id ID de l'événement parent
 * @property string $title Titre de l'activité
 * @property string|null $description Description détaillée de l'activité
 * @property Carbon $starts_at Heure de début de l'activité
 * @property Carbon|null $ends_at Heure de fin de l'activité
 * @property int $sort_order Ordre de priorité pour l'affichage de l'activité
 * @property string|null $location Emplacement spécifique au sein du lieu pour cette activité
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Event $event Événement parent auquel cette activité appartient
 */
class EventActivity extends Model
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
    protected $table = 'event_activities';

    /**
     * Attributs qui sont assignables en masse.
     *
     * @var list<string>
     */
    protected $fillable = [
        'event_id',
        'title',
        'description',
        'starts_at',
        'ends_at',
        'sort_order',
        'location',
    ];

    /**
     * Récupère les attributs qui doivent être castés.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    /**
     * Définit la relation pour l'événement parent.
     *
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
