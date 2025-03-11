<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Password; // Asegúrate de que esta línea esté presente

class AuthController extends Controller
{
    
    public function register(Request $request)
    {
        // Validar los datos de entrada
        $validated = $request->validate([
            'name' => 'required|string',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:6',
            'phone' => 'required|string',
            'codigo_postal' => 'required|string',
            'profile_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:10240',
            'referral_code' => 'nullable|string', // Código de referido opcional
        ]);

        // Generar un invite_code único (para unirse a equipos)
        do {
            $inviteCode = substr(md5($validated['email'] . time()), 0, 8);
        } while (User::where('invite_code', $inviteCode)->exists());
        $validated['invite_code'] = $inviteCode;

        // Generar un referral_code único (para referidos)
        do {
            $referralCode = substr(md5($validated['email'] . rand()), 0, 8);
        } while (User::where('referral_code', $referralCode)->exists());
        $validated['referral_code'] = $referralCode;

        // Verificar si se proporcionó un referral_code y buscar al referrer
        $referrer = null;
        if ($request->has('referral_code') && !empty($request->referral_code)) {
            $referrer = User::where('referral_code', $request->referral_code)->first();
            if (!$referrer) {
                return response()->json(['message' => 'Código de referido inválido'], 400);
            }
            $validated['referred_by'] = $referrer->id;
        }

        // Manejar la subida de la imagen de perfil
        if ($request->hasFile('profile_image')) {
            try {
                $path = $request->file('profile_image')->store('profiles', 'public');
                $validated['profile_image'] = $path;
                \Log::info('Image uploaded:', ['path' => $path]);
            } catch (\Exception $e) {
                \Log::error('Error uploading image:', ['error' => $e->getMessage()]);
                return response()->json(['message' => 'Error uploading image'], 500);
            }
        }

        // Hashear la contraseña
        $validated['password'] = Hash::make($validated['password']);

        // Crear el usuario
        $user = User::create($validated);

        // Crear un token de autenticación
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token
        ], 201);
    }
    

    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        // Enviar el enlace de restablecimiento de contraseña
        $status = Password::sendResetLink(
            $request->only('email')
        );

        return $status === Password::RESET_LINK_SENT
            ? response()->json(['message' => 'Reset link sent to your email'], 200)
            : response()->json(['message' => 'Unable to send reset link'], 400);
    }


    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:6|confirmed',
        ]);

        // Intentar restablecer la contraseña
        $status = Password::reset(
            $request->only('email', 'token', 'password', 'password_confirmation'),
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password)
                ])->save();
            }
        );

        return $status === Password::PASSWORD_RESET
            ? response()->json(['message' => 'Password reset successfully'], 200)
            : response()->json(['message' => 'Unable to reset password'], 400);
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



public function updateProfile(Request $request)
{
    \Log::info('Request completo:', $request->all());
    \Log::info('Files:', $request->allFiles());
    
    // Obtener el usuario autenticado
    $user = $request->user();

    // Validar los datos enviados desde el frontend
    $validated = $request->validate([
        'name' => 'sometimes|string|max:255',
        'phone' => 'sometimes|string|max:255',
        'codigo_postal' => 'sometimes|string|max:255',
        'posicion' => 'sometimes|string|max:255', // Validar la posición
        'profile_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:10240', // Validar la imagen si se envía
    ]);

    // Actualizar campos si existen en la solicitud
    if ($request->has('name')) {
        $user->name = $request->input('name');
    }
    if ($request->has('phone')) {
        $user->phone = $request->input('phone');
    }
    if ($request->has('codigo_postal')) {
        $user->codigo_postal = $request->input('codigo_postal');
    }
    if ($request->has('posicion')) {
        $user->posicion = $request->input('posicion'); // Actualizar la posición
    }

    // Manejar la subida de la imagen de perfil si se envía
    if ($request->hasFile('profile_image')) {
        try {
            $path = $request->file('profile_image')->store('profiles', 'public');
            $user->profile_image = $path;
            \Log::info('Imagen subida:', ['path' => $path]);
        } catch (\Exception $e) {
            \Log::error('Error al subir la imagen:', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Error al subir la imagen'], 500);
        }
    }

    // Guardar los cambios en la base de datos
    $user->save();
    
    \Log::info('Usuario actualizado:', $user->toArray());

    return response()->json([
        'message' => 'Perfil actualizado correctamente',
        'user' => $user
    ]);
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