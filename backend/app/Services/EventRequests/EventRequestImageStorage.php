<?php

namespace App\Services\EventRequests;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Service pour la gestion du stockage des images pour les demandes d'événements.
 *
 * Supporte à la fois les téléchargements de formulaires multi-parties standards et les données encodées en base64 (souvent utilisées dans les intégrations SPA/Mobiles).
 */
class EventRequestImageStorage
{
    /** @var int Taille maximale autorisée pour les images en base64 (2 Mo) */
    private const MAX_IMAGE_BYTES = 2 * 1024 * 1024;

    /**
     * Stocke une image et retourne son chemin public.
     *
     * Cette méthode est polymorphe : elle préfère un objet UploadedFile mais se rabat sur les données base64.
     *
     * @param  UploadedFile|null  $image  Fichier téléchargé Laravel standard.
     * @param  string|null  $imageData  Chaîne d'image encodée en base64 (peut inclure le préfixe Data URI).
     * @param  string|null  $mime  Type MIME explicite pour les données base64 (par défaut image/jpeg).
     * @return string|null Le chemin relatif vers l'image stockée, ou null si aucune image n'a été fournie.
     *
     * @throws ValidationException Si le décodage base64 échoue ou dépasse les limites de taille.
     */
    public function store(?UploadedFile $image, ?string $imageData, ?string $mime = null): ?string
    {
        // Cas 1 : Téléchargement de fichier Laravel standard
        if ($image?->isValid()) {
            $path = $image->store('event-requests', 'public');

            return is_string($path) ? $path : null;
        }

        // Cas 2 : Données base64 (utilisées lors de l'envoi de charges utiles JSON)
        if (! $imageData) {
            return null;
        }

        // Supprimer le préfixe Data URI s'il est présent (ex: "data:image/png;base64,")
        $raw = str_contains($imageData, ',')
            ? explode(',', $imageData, 2)[1]
            : $imageData;

        $bytes = base64_decode($raw, true);

        // Cas limite : caractères base64 ou formatage invalides.
        if ($bytes === false) {
            throw ValidationException::withMessages([
                'image' => ['Image invalide.'],
            ]);
        }

        // Appliquer la limite de taille pour le base64 (puisqu'elle n'est pas gérée par le upload_max_filesize de PHP)
        if (strlen($bytes) > self::MAX_IMAGE_BYTES) {
            throw ValidationException::withMessages([
                'image' => ['L\'image ne doit pas dépasser 2 Mo.'],
            ]);
        }

        // Générer un nom de fichier unique à l'aide d'un UUID pour éviter les collisions.
        $path = 'event-requests/'.Str::uuid().'.'.$this->extensionFor($mime ?? 'image/jpeg');
        Storage::disk('public')->put($path, $bytes);

        return $path;
    }

    /**
     * Supprime une image du stockage.
     *
     * @param  string|null  $path  Le chemin relatif de l'image à supprimer.
     */
    public function delete(?string $path): void
    {
        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }

    /**
     * Associe les types MIME courants à des extensions de fichiers.
     *
     * @param  string  $mime  Le type MIME à associer.
     * @return string L'extension de fichier appropriée.
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
