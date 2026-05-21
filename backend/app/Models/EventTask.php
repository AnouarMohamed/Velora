<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use MongoDB\Laravel\Eloquent\Model;

/**
 * Modèle EventTask
 *
 * Représente une tâche de planification ou d'exécution associée à un événement.
 * Les tâches sont assignées aux organisateurs ou aux membres du personnel pour assurer la préparation de l'événement.
 *
 * @property string $_id ID du document MongoDB
 * @property string $event_id ID de l'événement associé
 * @property string|null $assigned_to ID de l'utilisateur assigné à cette tâche
 * @property string $title Titre de la tâche
 * @property string|null $description Description détaillée de la tâche
 * @property bool $is_done Indique si la tâche a été accomplie
 * @property Carbon|null $due_at Date d'échéance de la tâche
 * @property string $status Statut actuel de la tâche
 * @property string $priority Niveau de priorité de la tâche (low, medium, high)
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Event $event Événement auquel cette tâche appartient
 * @property-read User|null $assignee Utilisateur responsable de la tâche
 */
class EventTask extends Model
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
    protected $table = 'event_tasks';

    /**
     * Attributs qui sont assignables en masse.
     *
     * @var list<string>
     */
    protected $fillable = [
        'event_id',
        'assigned_to',
        'title',
        'description',
        'is_done',
        'due_at',
        'status',
        'priority',
    ];

    /**
     * Récupère les attributs qui doivent être castés.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_done' => 'boolean',
            'due_at' => 'datetime',
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

    /**
     * Définit la relation pour l'utilisateur assigné.
     *
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
