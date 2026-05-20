<?php

namespace App\Http\Resources;

use App\Models\Feedback;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource for transforming Feedback model data into an API-friendly format.
 *
 * This resource ensures consistent output for feedback records, including
 * nested user information if the relationship is loaded.
 */
class FeedbackResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * Maps the Feedback model attributes to the response structure.
     * Includes the user's name and ID if the 'user' relation is available.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $feedback = $this->resource;
        if (! $feedback instanceof Feedback) {
            return [];
        }

        // Safely extract the user relation if it has been eager-loaded
        $user = $feedback->relationLoaded('user') ? $feedback->getRelation('user') : null;
        $user = $user instanceof User ? $user : null;

        return [
            'id' => $feedback->getKey(),
            'event_id' => $feedback->getAttribute('event_id'),
            'rating' => (int) $feedback->getAttribute('rating'),
            'comment' => $feedback->getAttribute('comment'),
            'status' => $feedback->getAttribute('status'),
            'created_at' => $feedback->getAttribute('created_at'),
            'user' => $user ? [
                'id' => $user->getKey(),
                'name' => $user->getAttribute('name'),
            ] : null,
        ];
    }
}
