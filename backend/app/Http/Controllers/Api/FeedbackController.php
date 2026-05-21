<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Feedbacks\StoreFeedbackRequest;
use App\Http\Resources\FeedbackResource;
use App\Models\Event;
use App\Models\Feedback;
use App\Services\Feedbacks\FeedbackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Contrôleur pour la gestion des commentaires d'événement (avis).
 *
 * Les participants peuvent soumettre des commentaires pour les événements auxquels ils ont assisté.
 * Les commentaires peuvent nécessiter l'approbation d'un administrateur avant de devenir publics.
 */
class FeedbackController extends ApiController
{
    /**
     * @param  FeedbackService  $feedbacks  Service pour la logique métier des commentaires.
     */
    public function __construct(private readonly FeedbackService $feedbacks) {}

    /**
     * Lister les commentaires pour un événement spécifique.
     *
     * @return AnonymousResourceCollection Collection de FeedbackResource.
     */
    public function index(Request $request, Event $event)
    {
        // Le service gère la visibilité : commentaires publics pour tout le monde, tous les commentaires pour les administrateurs.
        return FeedbackResource::collection($this->feedbacks->listForEvent($this->actor($request), $event));
    }

    /**
     * Soumettre un nouveau commentaire pour un événement.
     *
     * @param  StoreFeedbackRequest  $request  Commentaire validé (note, commentaire).
     * @return JsonResponse 201 Created avec FeedbackResource et un message de succès.
     */
    public function store(StoreFeedbackRequest $request, Event $event)
    {
        /** @var array{rating: int, comment?: string|null} $data */
        $data = $request->validated();
        $feedback = $this->feedbacks->submit($this->actor($request), $event, $data);

        return FeedbackResource::make($feedback)
            ->additional(['message' => 'Votre avis a bien été envoyé. Il sera visible après validation par notre équipe.'])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Approuver une entrée de commentaire (Administrateur uniquement).
     *
     * Rend le commentaire visible au public.
     *
     * @return FeedbackResource Commentaire mis à jour.
     */
    public function approve(Request $request, Feedback $feedback)
    {
        $result = $this->feedbacks->approve($this->actor($request), $feedback);

        return FeedbackResource::make($result->feedback)
            ->additional(['message' => $result->message]);
    }

    /**
     * Supprimer une entrée de commentaire.
     *
     * @return JsonResponse 204 No Content.
     */
    public function destroy(Request $request, Feedback $feedback)
    {
        $this->feedbacks->delete($this->actor($request), $feedback);

        return response()->json(null, 204);
    }
}
