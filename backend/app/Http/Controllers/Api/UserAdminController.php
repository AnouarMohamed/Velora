<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Users\StoreUserRequest;
use App\Http\Requests\Users\UpdateUserRequest;
use App\Http\Requests\Users\UserIndexRequest;
use App\Models\User;
use App\Services\Users\UserWriteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Contrôleur pour la gestion des utilisateurs du point de vue de l'administrateur.
 *
 * Ce contrôleur permet aux administrateurs de lister, créer, mettre à jour et supprimer des utilisateurs.
 */
class UserAdminController extends ApiController
{
    /**
     * @param  UserWriteService  $users  Service pour la création et la mise à jour des utilisateurs.
     */
    public function __construct(private readonly UserWriteService $users) {}

    /**
     * Lister les utilisateurs (vue Administrateur).
     *
     * @return JsonResponse Liste paginée des utilisateurs, optionnellement filtrée par rôle.
     */
    public function index(UserIndexRequest $request)
    {
        $q = User::query()->orderBy('created_at', 'desc');

        // Filtre de rôle optionnel (admin, organisateur, client)
        if ($role = $this->validatedNullableString($request, 'role')) {
            $q->where('role', $role);
        }

        return response()->json($q->paginate(30));
    }

    /**
     * Obtenir une liste de tous les organisateurs.
     *
     * Utilisé pour remplir les listes déroulantes (ex : lors de l'assignation d'un organisateur à un événement).
     *
     * @return JsonResponse Liste des organisateurs avec les champs de base.
     */
    public function organizers()
    {
        $users = User::query()
            ->where('role', User::ROLE_ORGANIZER)
            ->orderBy('name', 'asc')
            ->get(['id', 'name', 'email', 'role']);

        return response()->json($users);
    }

    /**
     * Créer un nouvel utilisateur manuellement.
     *
     * @param  StoreUserRequest  $request  Données d'utilisateur validées.
     * @return JsonResponse 201 Created.
     */
    public function store(StoreUserRequest $request)
    {
        /** @var array{name: string, email: string, password: string, role: string} $data */
        $data = $request->validated();
        $user = $this->users->create($data);

        return response()->json($user, 201);
    }

    /**
     * Mettre à jour le profil d'un utilisateur existant.
     *
     * @param  UpdateUserRequest  $request  Mises à jour d'utilisateur validées.
     * @param  User  $user  L'utilisateur à mettre à jour.
     * @return JsonResponse Utilisateur mis à jour.
     */
    public function update(UpdateUserRequest $request, User $user)
    {
        return response()->json($this->users->update($user, $request->validated()));
    }

    /**
     * Supprimer un compte utilisateur.
     *
     * @param  User  $user  L'utilisateur à supprimer.
     * @return JsonResponse 204 No Content.
     */
    public function destroy(Request $request, User $user)
    {
        $this->users->delete($this->actor($request), $user);

        return response()->json(null, 204);
    }
}
