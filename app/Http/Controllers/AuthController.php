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
            'password' => 'required|min:6',
            'phone' => 'required|string',
            'codigo_postal' => 'required|string',
            'profile_image' => 'nullable|image|max:10240',   
        ]);
    
        // Manejar imagen
        if ($request->hasFile('profile_image')) {
            $path = $request->file('profile_image')->store('profiles', 'public');
            $validated['profile_image'] = $path;
            
            // Debug
            \Log::info('Image uploaded:', ['path' => $path]);
        }
    
        $validated['password'] = Hash::make($validated['password']);
        $user = User::create($validated);
    
        return response()->json([
            'user' => $user,
            'token' => $user->createToken('auth_token')->plainTextToken
        ]);
    }
    
   public function login(Request $request) {
    $validated = $request->validate([
        'email' => 'required|email',
        'password' => 'required'
    ]);

    $user = User::where('email', $validated['email'])->first();

    if (!$user || !Hash::check($validated['password'], $user->password)) {
        return response()->json(['message' => 'Invalid credentials'], 401);
    }
 
    return response()->json([
        'user' => $user,
        'token' => $user->createToken('auth_token')->plainTextToken
    ]);
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

 public function checkPhone(Request $request)
{
   $request->validate(['phone' => 'required']);
   $exists = User::where('phone', $request->phone)->exists();
   return response()->json(['exists' => $exists]); 
}


public function checkEmail(Request $request)
{
    $request->validate(['email' => 'required|email']);
    
    $exists = User::where('email', $request->email)->exists();
    return response()->json(['exists' => $exists]);
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