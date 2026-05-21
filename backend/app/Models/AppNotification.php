<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use MongoDB\Laravel\Eloquent\Model;

/**
 * Modèle AppNotification
 *
 * Représente une notification dans l'application envoyée à un utilisateur spécifique. Ce modèle
 * gère le stockage et l'état (lu/non lu) des notifications au sein de l'application.
 *
 * @property string $_id ID du document MongoDB
 * @property string $user_id ID de l'utilisateur destinataire
 * @property string $type Type de notification pour la catégorisation
 * @property string $title Titre de la notification
 * @property string $message Contenu détaillé de la notification
 * @property array<string, mixed>|null $data Métadonnées supplémentaires associées à la notification
 * @property Carbon|null $read_at Horodatage du moment où la notification a été lue
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user Destinataire de la notification
 */
class AppNotification extends Model
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
    protected $table = 'app_notifications';

    /**
     * Attributs qui sont assignables en masse.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'type',
        'title',
        'message',
        'data',
        'read_at',
    ];

    /**
     * Récupère les attributs qui doivent être castés.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'data' => 'array',
            'read_at' => 'datetime',
        ];
    }

    /**
     * Définit la relation pour l'utilisateur destinataire.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
