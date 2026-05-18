<?php

namespace App\Models\Concerns;

trait HasPublicImage
{
    public function getImageUrlAttribute(): ?string
    {
        $path = $this->attributes['image_path'] ?? null;

        if (! $path) {
            return null;
        }

        return '/storage/'.ltrim(str_replace('\\', '/', $path), '/');
    }
}
