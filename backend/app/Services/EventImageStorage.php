<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Service for managing event-related image uploads.
 *
 * This service specifically handles Base64 encoded image data, which is commonly
 * used in the frontend's image selection and cropping workflows.
 */
class EventImageStorage
{
    /**
     * Maximum allowed image size in bytes (2 MB).
     */
    private const MAX_IMAGE_BYTES = 2 * 1024 * 1024;

    /**
     * Decodes and stores a Base64 image.
     *
     * @param  string|null  $imageData  The raw base64 string, optionally prefixed with data URI scheme.
     * @param  string|null  $mime  The MIME type of the image to determine the file extension.
     * @return string|null The relative path to the stored image, or null if no data provided.
     *
     * @throws ValidationException If the image is invalid or exceeds the size limit.
     */
    public function storeBase64(?string $imageData, ?string $mime = null): ?string
    {
        if (! $imageData) {
            return null;
        }

        // Handle Base64 strings that include the 'data:image/...;base64,' prefix.
        $raw = str_contains($imageData, ',')
            ? explode(',', $imageData, 2)[1]
            : $imageData;

        // Perform strict base64 decoding to ensure data integrity.
        $bytes = base64_decode($raw, true);
        if ($bytes === false) {
            throw ValidationException::withMessages([
                'image_data' => ['Image invalide.'],
            ]);
        }

        // Enforce the size limit at the service level.
        if (strlen($bytes) > self::MAX_IMAGE_BYTES) {
            throw ValidationException::withMessages([
                'image_data' => ['L\'image ne doit pas dépasser 2 Mo.'],
            ]);
        }

        // Generate a unique filename using UUID to prevent collisions.
        $path = 'events/'.Str::uuid().'.'.$this->extensionFor($mime ?? 'image/jpeg');

        // Store the decoded binary data in the public disk.
        Storage::disk('public')->put($path, $bytes);

        return $path;
    }

    /**
     * Maps common image MIME types to their standard file extensions.
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
