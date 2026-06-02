<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Service pour la gestion des téléchargements d'images liées aux événements.
 *
 * Ce service gère spécifiquement les données d'image encodées en base64, qui sont couramment
 * utilisées dans les flux de travail de sélection et de recadrage d'images du frontend.
 */
class EventImageStorage
{
    /**
     * Taille maximale autorisée de l'image en octets (2 Mo).
     */
    private const MAX_IMAGE_BYTES = 2 * 1024 * 1024;

    /**
     * Décode et stocke une image base64.
     *
     * @param  string|null  $imageData  La chaîne base64 brute, éventuellement préfixée par le schéma Data URI.
     * @param  string|null  $mime  Le type MIME de l'image pour déterminer l'extension du fichier.
     * @return string|null Le chemin relatif vers l'image stockée, ou null si aucune donnée n'est fournie.
     *
     * @throws ValidationException Si l'image est invalide ou dépasse la limite de taille.
     */
    public function storeBase64(?string $imageData, ?string $mime = null): ?string
    {
        if (! $imageData) {
            return null;
        }

        // Gérer les chaînes base64 qui incluent le préfixe 'data:image/...;base64,'.
        $raw = str_contains($imageData, ',')
            ? explode(',', $imageData, 2)[1]
            : $imageData;

        // Effectuer un décodage base64 strict pour garantir l'intégrité des données.
        $bytes = base64_decode($raw, true);
        if ($bytes === false) {
            throw ValidationException::withMessages([
                'image_data' => ['Image invalide.'],
            ]);
        }

        // Appliquer la limite de taille au niveau du service.
        if (strlen($bytes) > self::MAX_IMAGE_BYTES) {
            throw ValidationException::withMessages([
                'image_data' => ['L\'image ne doit pas dépasser 2 Mo.'],
            ]);
        }

        if (config('filesystems.image_storage') === 'inline') {
            return 'data:'.($mime ?? 'image/jpeg').';base64,'.base64_encode($bytes);
        }

        // Générer un nom de fichier unique à l'aide d'un UUID pour éviter les collisions.
        $path = 'events/'.Str::uuid().'.'.$this->extensionFor($mime ?? 'image/jpeg');

        // Stocker les données binaires décodées dans le disque public.
        Storage::disk('public')->put($path, $bytes);

        return $path;
    }

    /**
     * Associe les types MIME d'images courants à leurs extensions de fichiers standards.
     */
    private function extensionFor(string $mime): string
    {
        return match ($mime) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
            default => 'jpg',
        };
    }
}
