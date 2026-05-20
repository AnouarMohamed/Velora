<?php

namespace App\Services\EventRequests;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Service for handling image storage for event requests.
 *
 * Supports both standard multi-part form uploads and base64 encoded data (often used in SPA/Mobile integrations).
 */
class EventRequestImageStorage
{
    /** @var int Maximum allowed size for base64 images (2MB) */
    private const MAX_IMAGE_BYTES = 2 * 1024 * 1024;

    /**
     * Stores an image and returns its public path.
     *
     * This method is polymorphic: it prefers an UploadedFile object but falls back to base64 data.
     *
     * @param  UploadedFile|null  $image  Standard Laravel uploaded file.
     * @param  string|null  $imageData  Base64 encoded image string (can include Data URI prefix).
     * @param  string|null  $mime  Explicit MIME type for base64 data (defaults to image/jpeg).
     * @return string|null The relative path to the stored image, or null if no image was provided.
     *
     * @throws ValidationException If base64 decoding fails or exceeds size limits.
     */
    public function store(?UploadedFile $image, ?string $imageData, ?string $mime = null): ?string
    {
        // Case 1: Standard Laravel File Upload
        if ($image?->isValid()) {
            return $image->store('event-requests', 'public');
        }

        // Case 2: Base64 Data (used when sending JSON payloads)
        if (! $imageData) {
            return null;
        }

        // Strip Data URI prefix if present (e.g., "data:image/png;base64,")
        $raw = str_contains($imageData, ',')
            ? explode(',', $imageData, 2)[1]
            : $imageData;

        $bytes = base64_decode($raw, true);

        // Edge Case: Invalid base64 characters or formatting.
        if ($bytes === false) {
            throw ValidationException::withMessages([
                'image' => ['Image invalide.'],
            ]);
        }

        // Enforce size limit for base64 (since it's not handled by PHP's upload_max_filesize)
        if (strlen($bytes) > self::MAX_IMAGE_BYTES) {
            throw ValidationException::withMessages([
                'image' => ['L\'image ne doit pas dépasser 2 Mo.'],
            ]);
        }

        // Generate a unique filename using UUID to prevent collisions.
        $path = 'event-requests/'.Str::uuid().'.'.$this->extensionFor($mime ?? 'image/jpeg');
        Storage::disk('public')->put($path, $bytes);

        return $path;
    }

    /**
     * Deletes an image from storage.
     *
     * @param  string|null  $path  The relative path to the image to delete.
     */
    public function delete(?string $path): void
    {
        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }

    /**
     * Maps common MIME types to file extensions.
     *
     * @param  string  $mime  The MIME type to map.
     * @return string The appropriate file extension.
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
