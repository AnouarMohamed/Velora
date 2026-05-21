<?php

namespace App\Models;

use App\Models\Concerns\StoresMoneyAsCents;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use MongoDB\Laravel\Eloquent\Model;

/**
 * Modèle Payment
 *
 * Représente une transaction financière associée à une inscription d'événement.
 * Ce modèle suit le statut du paiement, le montant et les détails de la transaction.
 *
 * @property string $_id ID du document MongoDB
 * @property string $registration_id ID de l'inscription associée
 * @property int $amount_cents Montant du paiement en centimes
 * @property string $currency Code de la devise (ex: USD, EUR)
 * @property string $status Statut du paiement (ex: pending, completed, failed, refunded)
 * @property string|null $transaction_id ID de transaction externe du fournisseur de paiement
 * @property string|null $method Méthode de paiement utilisée (ex: credit_card, paypal)
 * @property array<string, mixed>|null $meta Métadonnées supplémentaires du fournisseur de paiement
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read float $amount Montant calculé du paiement dans l'unité monétaire principale
 * @property-read Registration $registration Inscription associée à ce paiement
 */
class Payment extends Model
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
    protected $table = 'payments';

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
        'registration_id',
        'amount',
        'amount_cents',
        'currency',
        'status',
        'transaction_id',
        'method',
        'meta',
    ];

    /**
     * Récupère les attributs qui doivent être castés.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }

    /**
     * Accesseur pour le montant du paiement, convertissant les centimes en décimal.
     *
     * @return Attribute<string|null, mixed>
     */
    protected function amount(): Attribute
    {
        return $this->moneyCast('amount_cents');
    }

    /**
     * Définit la relation pour l'inscription associée.
     *
     * @return BelongsTo<Registration, $this>
     */
    public function registration(): BelongsTo
    {
        return $this->belongsTo(Registration::class);
    }
}
