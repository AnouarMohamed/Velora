<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EventImageStorage
{
    private const MAX_IMAGE_BYTES = 2 * 1024 * 1024;

    public function storeBase64(?string $imageData, ?string $mime = null): ?string
    {
        if (! $imageData) {
            return null;
        }

        $raw = str_contains($imageData, ',')
            ? explode(',', $imageData, 2)[1]
            : $imageData;

        $bytes = base64_decode($raw, true);
        if ($bytes === false) {
            throw ValidationException::withMessages([
                'image_data' => ['Image invalide.'],
            ]);
        }

        if (strlen($bytes) > self::MAX_IMAGE_BYTES) {
            throw ValidationException::withMessages([
                'image_data' => ['L\'image ne doit pas dépasser 2 Mo.'],
            ]);
        }

        $path = 'events/'.Str::uuid().'.'.$this->extensionFor($mime ?? 'image/jpeg');
        Storage::disk('public')->put($path, $bytes);

        return $path;
    }

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
