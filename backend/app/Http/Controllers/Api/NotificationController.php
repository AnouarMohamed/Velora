<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Laravel\Connection as MongoConnection;
use Traversable;

/**
 * Contrôleur pour la gestion des notifications dans l'application pour l'utilisateur authentifié.
 */
class NotificationController extends Controller
{
    /**
     * Lister les notifications pour l'utilisateur authentifié.
     *
     * @return JsonResponse Notifications paginées avec le nombre de messages non lus.
     */
    public function index(Request $request)
    {
        $perPage = 30;
        $page = max(1, $request->integer('page', 1));
        $user = $this->actor($request);
        $match = ['user_id' => $this->userId($user)];
        $unreadMatch = $this->unreadMatch();

        // Filtrage optionnel pour afficher uniquement les notifications non lues
        if ($request->boolean('unread_only')) {
            $match = array_merge($match, $unreadMatch);
        }

        /** @var MongoConnection $connection */
        $connection = DB::connection('mongodb');
        $results = $connection
            ->getDatabase()
            ->selectCollection('app_notifications')
            ->aggregate([
                ['$match' => $match],
                ['$sort' => ['created_at' => -1, '_id' => -1]],
                ['$facet' => [
                    'data' => [
                        ['$skip' => ($page - 1) * $perPage],
                        ['$limit' => $perPage],
                    ],
                    'meta' => [
                        ['$count' => 'total'],
                    ],
                    'unread' => [
                        ['$match' => $unreadMatch],
                        ['$count' => 'count'],
                    ],
                ]],
            ]);

        $facet = $this->plainValue(iterator_to_array($results, false)[0] ?? []);
        $data = is_array($facet) && is_array($facet['data'] ?? null)
            ? array_map(fn (mixed $document): array => $this->formatNotification($document), $facet['data'])
            : [];
        $total = is_array($facet) ? $this->facetCount($facet, 'meta', 'total') : 0;
        $unreadCount = is_array($facet) ? $this->facetCount($facet, 'unread', 'count') : 0;

        return response()->json([
            'data' => $data,
            'unread_count' => $unreadCount,
            'meta' => [
                'current_page' => $page,
                'last_page' => max(1, (int) ceil($total / $perPage)),
                'per_page' => $perPage,
                'total' => $total,
            ],
        ]);
    }

    /**
     * Obtenir le nombre de notifications non lues pour l'utilisateur actuel.
     *
     * @return JsonResponse
     */
    public function unreadCount(Request $request)
    {
        $count = AppNotification::query()
            ->where('user_id', $this->actor($request)->id)
            ->whereNull('read_at')
            ->count();

        return response()->json(['count' => $count]);
    }

    /**
     * Marquer une notification spécifique comme lue.
     *
     * @return JsonResponse La notification mise à jour.
     */
    public function markRead(Request $request, AppNotification $notification)
    {
        // Autorisation : s'assurer que la notification appartient au demandeur
        abort_unless($notification->user_id === $this->actor($request)->id, 403);

        if (! $notification->read_at) {
            $notification->update(['read_at' => now()]);
        }

        return response()->json($notification->fresh());
    }

    /**
     * Marquer toutes les notifications de l'utilisateur actuel comme lues.
     *
     * @return JsonResponse 200 OK message.
     */
    public function markAllRead(Request $request)
    {
        AppNotification::query()
            ->where('user_id', $this->actor($request)->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['message' => 'Toutes les notifications ont été lues.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function unreadMatch(): array
    {
        return [
            '$or' => [
                ['read_at' => null],
                ['read_at' => ['$exists' => false]],
            ],
        ];
    }

    /**
     * @param  array<mixed, mixed>  $facet
     */
    private function facetCount(array $facet, string $facetKey, string $countKey): int
    {
        $entries = $facet[$facetKey] ?? null;
        if (! is_array($entries)) {
            return 0;
        }

        $first = $entries[0] ?? null;
        if (! is_array($first)) {
            return 0;
        }

        $count = $first[$countKey] ?? 0;

        return is_int($count) ? $count : 0;
    }

    /**
     * @return array<string, mixed>
     */
    private function formatNotification(mixed $document): array
    {
        $notification = $this->plainValue($document);
        if (! is_array($notification)) {
            return [];
        }

        return [
            'id' => $notification['_id'] ?? null,
            'user_id' => $notification['user_id'] ?? null,
            'type' => $notification['type'] ?? null,
            'title' => $notification['title'] ?? null,
            'message' => $notification['message'] ?? null,
            'data' => $notification['data'] ?? null,
            'read_at' => $notification['read_at'] ?? null,
            'created_at' => $notification['created_at'] ?? null,
            'updated_at' => $notification['updated_at'] ?? null,
        ];
    }

    private function plainValue(mixed $value): mixed
    {
        if ($value instanceof ObjectId) {
            return (string) $value;
        }

        if ($value instanceof UTCDateTime) {
            return $value->toDateTime()->format(DateTimeInterface::ATOM);
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format(DateTimeInterface::ATOM);
        }

        if ($value instanceof Traversable) {
            return $this->plainValue(iterator_to_array($value));
        }

        if (is_array($value)) {
            return array_map(fn (mixed $item): mixed => $this->plainValue($item), $value);
        }

        return $value;
    }

    /**
     * Récupérer et valider l'utilisateur authentifié.
     */
    private function actor(Request $request): User
    {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(401);
        }

        return $user;
    }

    private function userId(User $user): string
    {
        $id = $user->id;

        return is_scalar($id) ? (string) $id : '';
    }
}
