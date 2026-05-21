<?php

namespace App\Models\Concerns;

/**
 * HasPublicImage Trait
 *
 * Provides a standard way to retrieve the public URL of an image stored in the project.
 * It expects the model to have an 'image_path' attribute.
 *
 * @property-read string|null $image_url Computed absolute URL for the image
 */
trait HasPublicImage
{
    /**
     * Accessor for the image URL.
     *
     * Converts the internal image path to a publicly accessible URL.
     */
    public function getImageUrlAttribute(): ?string
    {
        $path = $this->attributes['image_path'] ?? null;

        if (! is_string($path) || $path === '') {
            return null;
        }

        // Normalize slashes and ensure the path is prefixed with /storage/
        return '/storage/'.ltrim(str_replace('\\', '/', $path), '/');
    }
}
