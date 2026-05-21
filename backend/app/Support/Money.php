<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Classe utilitaire pour la gestion des valeurs monétaires et des conversions de devises.
 *
 * Cette classe suit le modèle "Cents" pour éviter les problèmes de précision des virgules flottantes
 * lors des calculs ou du stockage des valeurs dans la base de données.
 */
final class Money
{
    /**
     * Convertit divers formats numériques en une représentation entière en centimes.
     *
     * Supporte :
     * - Entiers (multipliés par 100)
     * - Flottants (arrondis au centime le plus proche)
     * - Chaînes numériques (ex: "12.50", "-5.00")
     *
     * @param  mixed  $amount  La valeur à convertir.
     * @return int Le montant en centimes.
     *
     * @throws InvalidArgumentException Si la chaîne fournie n'est pas un format numérique valide.
     */
    public static function toCents(mixed $amount): int
    {
        if ($amount === null || $amount === '') {
            return 0;
        }

        if (is_int($amount)) {
            return $amount * 100;
        }

        if (is_float($amount)) {
            return (int) round($amount * 100);
        }

        if (! is_scalar($amount)) {
            throw new InvalidArgumentException('Invalid money amount.');
        }

        $value = trim((string) $amount);

        if (! preg_match('/^-?\d+(\.\d+)?$/', $value)) {
            throw new InvalidArgumentException('Invalid money amount.');
        }

        $isNegative = str_starts_with($value, '-');
        $unsigned = ltrim($value, '-');

        [$whole, $fraction] = array_pad(explode('.', $unsigned, 2), 2, '');

        $cents = ((int) $whole) * 100;

        $cents += (int) substr(str_pad($fraction, 2, '0'), 0, 2);

        if ((int) ($fraction[2] ?? 0) >= 5) {
            $cents++;
        }

        return $isNegative ? -$cents : $cents;
    }

    /**
     * Convertit un entier en centimes en une chaîne décimale formatée.
     *
     * Exemple : 1250 devient "12.50".
     *
     * @param  mixed  $cents  Le montant en centimes.
     * @return string Chaîne formatée "XX.YY".
     */
    public static function fromCents(mixed $cents): string
    {
        $cents = is_scalar($cents) ? (int) $cents : 0;
        $prefix = $cents < 0 ? '-' : '';
        $absolute = abs($cents);

        return sprintf('%s%d.%02d', $prefix, intdiv($absolute, 100), $absolute % 100);
    }

    /**
     * Convertit un entier en centimes en un flottant pour les calculs ou l'affichage.
     *
     * @param  mixed  $cents  Le montant en centimes.
     * @return float Le montant sous forme de flottant (ex : 12.5).
     */
    public static function floatFromCents(mixed $cents): float
    {
        return (float) self::fromCents($cents);
    }
}
