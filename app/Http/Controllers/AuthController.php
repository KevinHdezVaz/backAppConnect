<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:5'
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password'])
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token
        ]);
    }

    public function login(Request $request)
{
    $validated = $request->validate([
        'email' => 'required|email',
        'password' => 'nullable' // Permite que el campo sea vacío, ya que algunos usuarios no usan contraseña
    ]);
    
    $user = User::where('email', $validated['email'])->first();
    
    if (!$user) {
        return response()->json(['message' => 'Usuario no encontrado'], 404);
    }
    
    // Si el usuario no tiene contraseña (caso de login con Google), no valida la contraseña
    if ($user->google_id && !$validated['password']) {
        // El usuario se registró con Google, así que no se valida la contraseña
        $token = $user->createToken('auth_token')->plainTextToken;
        return response()->json(['token' => $token, 'user' => $user]);
    }

    if (!$user || !Hash::check($validated['password'], $user->password)) {
        return response()->json(['message' => 'Credenciales inválidas'], 401);
    }
    
    $token = $user->createToken('auth_token')->plainTextToken;
    
    return response()->json(['token' => $token, 'user' => $user]);
}

    public function profile(Request $request)
    {
    return response()->json($request->user());
    }


    public function redirectToGoogle()
{
    return Socialite::driver('google')->redirect();
}



public function loginWithGoogle(Request $request)
{
    $request->validate([
        'id_token' => 'required|string',
    ]);

    try {
        $googleUser = Socialite::driver('google')->stateless()->userFromToken($request->id_token);

        // Busca o crea un usuario en la base de datos
        $user = User::updateOrCreate([
            'email' => $googleUser->getEmail(),
        ], [
            'name' => $googleUser->getName(),
            'google_id' => $googleUser->getId(),
            'avatar' => $googleUser->getAvatar(),
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user,
        ]);
    } catch (\Exception $e) {
        return response()->json(['message' => 'Error al iniciar sesión con Google', 'error' => $e->getMessage()], 400);
    }
}



public function handleGoogleCallback()
{
    $googleUser = Socialite::driver('google')->user();
    
    $user = User::updateOrCreate(
        ['email' => $googleUser->getEmail()],
        [
            'name' => $googleUser->getName(),
            'google_id' => $googleUser->getId(),
            'avatar' => $googleUser->getAvatar(), // Opcional, si deseas almacenar la foto del perfil
            'google_token' => $googleUser->token, // Si quieres almacenar el token de acceso de Google
        ]
    );
    

    $token = $user->createToken('auth_token')->plainTextToken;
    return response()->json(['token' => $token, 'user' => $user]);
}

public function logout(Request $request)
{
    $request->user()->tokens->each(function ($token) {
        $token->delete();
    });
    
    return response()->json(['message' => 'Logged out successfully']);
}

}