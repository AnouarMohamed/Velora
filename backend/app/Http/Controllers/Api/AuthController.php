<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use App\Services\Users\UserWriteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Contrôleur gérant l'authentification et l'inscription.
 *
 * Ce contrôleur gère les événements du cycle de vie des utilisateurs tels que l'inscription,
 * la connexion et la déconnexion en utilisant Laravel Sanctum pour l'authentification par jeton.
 */
class AuthController extends Controller
{
    /**
     * @param  UserWriteService  $users  Service pour la gestion de la création et des mises à jour d'utilisateurs.
     */
    public function __construct(private readonly UserWriteService $users) {}

    /**
     * Enregistrer un nouvel utilisateur.
     *
     * Ce point de terminaison est public. Il valide la requête d'inscription,
     * crée un nouvel utilisateur via le UserWriteService, et renvoie un jeton d'authentification.
     *
     * @param  RegisterRequest  $request  Données d'inscription validées (nom, email, mot de passe, rôle).
     * @return JsonResponse 201 Created avec l'utilisateur et le jeton SPA.
     */
    public function register(RegisterRequest $request)
    {
        // Déléguer la création de l'utilisateur au service
        /** @var array{name: string, email: string, password: string, role: string} $data */
        $data = $request->validated();
        $user = $this->users->create($data);

        // Créer un jeton Sanctum pour le nouvel utilisateur
        $token = $user->createToken('spa')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user,
        ], 201);
    }

    /**
     * Authentifier un utilisateur et renvoyer un jeton.
     *
     * Ce point de terminaison est public. Il tente d'authentifier l'utilisateur en utilisant les identifiants fournis.
     * En cas de succès, il renvoie un nouveau jeton SPA.
     *
     * @param  LoginRequest  $request  Identifiants de connexion validés (email, mot de passe).
     * @return JsonResponse 200 OK avec le jeton ou 422 en cas d'échec.
     */
    public function login(LoginRequest $request)
    {
        $credentials = $request->validated();

        // Tentative d'authentification en utilisant la façade Auth de Laravel
        if (! Auth::attempt($credentials)) {
            return response()->json(['message' => 'Identifiants invalides.'], 422);
        }

        /** @var User $user */
        $user = User::where('email', $credentials['email'])->firstOrFail();
        // Émettre un nouveau jeton Sanctum pour la session
        $token = $user->createToken('spa')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user,
        ]);
    }

    /**
     * Déconnecter l'utilisateur en révoquant son jeton actuel.
     *
     * Ce point de terminaison nécessite une authentification.
     *
     * @return JsonResponse 200 OK message.
     */
    public function logout(Request $request)
    {
        // Supprimer le jeton qui a été utilisé pour cette requête
        $request->user()?->currentAccessToken()?->delete();

        return response()->json(['message' => 'Déconnexion réussie.']);
    }

    /**
     * Récupérer les informations de l'utilisateur authentifié.
     *
     * Ce point de terminaison nécessite une authentification.
     *
     * @return JsonResponse 200 OK avec l'objet utilisateur.
     */
    public function user(Request $request)
    {
        // Renvoyer l'utilisateur associé au jeton d'authentification
        return response()->json($request->user());
    }
}
