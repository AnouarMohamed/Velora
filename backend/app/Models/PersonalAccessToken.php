<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;
use MongoDB\Laravel\Eloquent\DocumentModel;

/**
 * Modèle PersonalAccessToken
 *
 * Implémentation personnalisée du PersonalAccessToken de Sanctum pour MongoDB.
 * Il gère les jetons d'API utilisés pour l'authentification des utilisateurs.
 *
 * @property string $_id ID du document MongoDB
 * @property string $tokenable_type Nom de classe du modèle propriétaire du jeton
 * @property string $tokenable_id ID du modèle propriétaire du jeton
 * @property string $name Nom convivial du jeton
 * @property string $token Valeur du jeton hachée
 * @property array<int, string>|null $abilities Liste des permissions accordées à ce jeton
 * @property Carbon|null $last_used_at Horodatage de la dernière utilisation du jeton
 * @property Carbon|null $expires_at Horodatage de l'expiration du jeton
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Model $tokenable Le propriétaire du jeton
 */
class PersonalAccessToken extends SanctumPersonalAccessToken
{
    use DocumentModel;

    /**
     * La connexion à la base de données utilisée par le modèle.
     *
     * @var string
     */
    protected $connection = 'mongodb';

    /**
     * Sanctum valide les IDs des jetons porteurs en fonction du type de clé du modèle.
     *
     * Les ObjectIds MongoDB sont des chaînes hexadécimales, donc ce modèle ne doit pas utiliser
     * le type de clé entier par défaut d'Eloquent, sinon Sanctum rejettera les jetons valides avant la recherche.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * La table/collection associée au modèle.
     *
     * @var string
     */
    protected $table = 'personal_access_tokens';
}
