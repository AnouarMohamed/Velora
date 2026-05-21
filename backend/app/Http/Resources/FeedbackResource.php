<?php

namespace App\Http\Resources;

use App\Models\Feedback;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Ressource pour transformer les données du modèle Feedback dans un format adapté à l'API.
 *
 * Cette ressource garantit une sortie cohérente pour les enregistrements de feedback, y compris
 * les informations utilisateur imbriquées si la relation est chargée.
 */
class FeedbackResource extends JsonResource
{
    /**
     * Transformer la ressource en tableau.
     *
     * Mappe les attributs du modèle Feedback à la structure de la réponse.
     * Inclut le nom et l'ID de l'utilisateur si la relation 'user' est disponible.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $feedback = $this->resource;
        if (! $feedback instanceof Feedback) {
            return [];
        }

        // Extraire en toute sécurité la relation utilisateur si elle a été chargée en amont
        $user = $feedback->relationLoaded('user') ? $feedback->getRelation('user') : null;
        $user = $user instanceof User ? $user : null;
        $rating = $feedback->getAttribute('rating');

        return [
            'id' => $feedback->getKey(),
            'event_id' => $feedback->getAttribute('event_id'),
            'rating' => is_numeric($rating) ? (int) $rating : 0,
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
