<?php

namespace App\Services\EventRequests;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EventRequestImageStorage
{
    private const MAX_IMAGE_BYTES = 2 * 1024 * 1024;

    public function store(?UploadedFile $image, ?string $imageData, ?string $mime = null): ?string
    {
        if ($image?->isValid()) {
            return $image->store('event-requests', 'public');
        }

        if (! $imageData) {
            return null;
        }

        $raw = str_contains($imageData, ',')
            ? explode(',', $imageData, 2)[1]
            : $imageData;

        $bytes = base64_decode($raw, true);
        if ($bytes === false) {
            throw ValidationException::withMessages([
                'image' => ['Image invalide.'],
            ]);
        }

        if (strlen($bytes) > self::MAX_IMAGE_BYTES) {
            throw ValidationException::withMessages([
                'image' => ['L\'image ne doit pas dépasser 2 Mo.'],
            ]);
        }

        $path = 'event-requests/'.Str::uuid().'.'.$this->extensionFor($mime ?? 'image/jpeg');
        Storage::disk('public')->put($path, $bytes);

        return $path;
    }

    public function delete(?string $path): void
    {
        if ($path) {
            Storage::disk('public')->delete($path);
        }
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
