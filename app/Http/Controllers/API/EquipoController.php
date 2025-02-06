<?php
namespace App\Http\Controllers\API;

use App\Models\Equipo;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\Controller;

class EquipoController extends Controller
{
    public function index()
    {
        $userId = auth()->id();
        $equipos = Equipo::whereHas('miembros', function($query) use ($userId) {
            $query->where('user_id', $userId);
        })->with('miembros')->get();
        
        return response()->json($equipos);
    }
 
 public function store(Request $request)
{
    $request->validate([
        'nombre' => 'required|string|max:255',
        'color_uniforme' => 'required|string|max:255',
        'logo' => 'nullable|image|mimes:jpeg,png,jpg|max:2048'
    ]);

    try {
        // Importante: DB::transaction debe retornar el resultado
        return DB::transaction(function () use ($request) {
            $logoPath = null;
            if ($request->hasFile('logo')) {
                $logoPath = $request->file('logo')->store('equipos', 'public');
            }

            $equipo = Equipo::create([
                'nombre' => $request->nombre,
                'color_uniforme' => $request->color_uniforme,
                'logo' => $logoPath
            ]);

            $equipo->miembros()->attach(auth()->id(), [
                'rol' => 'capitan',
                'estado' => 'activo'
            ]);

            // Cargar los miembros después de crear la relación
            $equipo->load('miembros');

            return response()->json([
                'message' => 'Equipo creado exitosamente',
                'equipo' => $equipo
            ], 201);
        });
    } catch (\Exception $e) {
        \Log::error('Error al crear equipo: ' . $e->getMessage());
        \Log::error($e->getTraceAsString());
        
        return response()->json([
            'message' => 'Error al crear el equipo',
            'error' => $e->getMessage()
        ], 500);
    }
}

public function getInvitacionesPendientesCount()
{
    $count = DB::table('equipo_usuarios')
        ->where('user_id', auth()->id())
        ->where('estado', 'pendiente')
        ->count();

    return response()->json(['count' => $count]);
}



public function getInvitacionesPendientes()
{
    $userId = auth()->id();
    
    $equipos = Equipo::whereHas('miembros', function($query) use ($userId) {
        $query->where('user_id', $userId)
              ->where('estado', 'pendiente');
    })->with('miembros')->get();
    
    return response()->json($equipos);
}

public function rechazarInvitacion(Equipo $equipo)
{
    $equipo->miembros()->detach(auth()->id());
    
    return response()->json([
        'message' => 'Invitación rechazada exitosamente'
    ]);
}

 
public function invitarPorCodigo(Request $request, Equipo $equipo)
{
    $request->validate([
        'codigo' => 'required|string|size:8'
    ]);

    // Verificar si el usuario actual es capitán
    if (!$equipo->esCapitan(auth()->user())) {
        return response()->json([
            'message' => 'Solo el capitán puede invitar miembros'
        ], 403);
    }

    // Buscar usuario por código
    $userToInvite = User::where('invite_code', $request->codigo)->first();

    if (!$userToInvite) {
        return response()->json([
            'message' => 'Código de usuario inválido'
        ], 404);
    }

    // Verificar si ya es miembro del equipo
    if ($equipo->miembros()->where('user_id', $userToInvite->id)->exists()) {
        return response()->json([
            'message' => 'El usuario ya es miembro del equipo'
        ], 400);
    }

    // Verificar si el usuario ya está en otro equipo
    $userInOtherTeam = DB::table('equipo_usuarios')
        ->where('user_id', $userToInvite->id)
        ->where('estado', 'activo')
        ->exists();

    if ($userInOtherTeam) {
        return response()->json([
            'message' => 'El usuario ya pertenece a otro equipo'
        ], 400);
    }

    try {
        // Agregar al usuario como miembro pendiente
        $equipo->miembros()->attach($userToInvite->id, [
            'rol' => 'miembro',
            'estado' => 'pendiente'
        ]);

        return response()->json([
            'message' => 'Invitación enviada exitosamente'
        ]);
    } catch (\Exception $e) {
        \Log::error('Error al invitar usuario: ' . $e->getMessage());
        return response()->json([
            'message' => 'Error al procesar la invitación'
        ], 500);
    }
}


public function invitarMiembro(Request $request, Equipo $equipo)
{
    $request->validate([
        'user_id' => 'required|exists:users,id'
    ]);

    if (!$equipo->esCapitan(auth()->user())) {
        return response()->json(['error' => 'No autorizado'], 403);
    }

    try {
        // Verificar si el usuario ya está en algún equipo
        $userInOtherTeam = DB::table('equipo_usuarios')
            ->where('user_id', $request->user_id)
            ->where('estado', 'activo')
            ->exists();

        if ($userInOtherTeam) {
            return response()->json([
                'message' => 'El jugador ya pertenece a otro equipo',
                'error' => 'PLAYER_IN_TEAM'
            ], 400);
        }

        // Verificar si ya tiene una invitación pendiente
        $pendingInvitation = DB::table('equipo_usuarios')
            ->where('user_id', $request->user_id)
            ->where('estado', 'pendiente')
            ->exists();

        if ($pendingInvitation) {
            return response()->json([
                'message' => 'El jugador ya tiene una invitación pendiente',
                'error' => 'PENDING_INVITATION'
            ], 400);
        }

        $equipo->miembros()->attach($request->user_id, [
            'rol' => 'miembro',
            'estado' => 'pendiente'
        ]);

        return response()->json([
            'message' => 'Invitación enviada exitosamente'
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'message' => 'Error al enviar la invitación',
            'error' => $e->getMessage()
        ], 500);
    }
}


public function buscarUsuarioPorCodigo($codigo)
{
    $user = User::where('invite_code', $codigo)->first();
    
    if (!$user) {
        return response()->json(['message' => 'Usuario no encontrado'], 404);
    }

    return response()->json($user);
}

public function eliminarMiembro(Equipo $equipo, User $user)
{
    if (!$equipo->esCapitan(auth()->user())) {
        return response()->json(['error' => 'No autorizado'], 403);
    }

    if ($equipo->esCapitan($user)) {
        return response()->json([
            'error' => 'No se puede eliminar al capitán'
        ], 400);
    }

    $equipo->miembros()->detach($user->id);
    
    return response()->json([
        'message' => 'Miembro eliminado exitosamente'
    ]);
}

public function aceptarInvitacion(Equipo $equipo)
{
    try {
        // Verificar si el usuario ya está en otro equipo
        $userInOtherTeam = DB::table('equipo_usuarios')
            ->where('user_id', auth()->id())
            ->where('estado', 'activo')
            ->exists();

        if ($userInOtherTeam) {
            return response()->json([
                'message' => 'Ya perteneces a otro equipo',
                'error' => 'PLAYER_IN_TEAM'
            ], 400);
        }

        $equipo->miembros()
               ->wherePivot('user_id', auth()->id())
               ->wherePivot('estado', 'pendiente')
               ->updateExistingPivot(auth()->id(), ['estado' => 'activo']);

        return response()->json([
            'message' => 'Invitación aceptada exitosamente'
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'message' => 'Error al aceptar la invitación',
            'error' => $e->getMessage()
        ], 500);
    }
}

    public function abandonarEquipo(Equipo $equipo)
    {
        try {
            if ($equipo->esCapitan(auth()->user())) {
                return response()->json([
                    'error' => 'El capitán no puede abandonar el equipo'
                ], 400);
            }

            $equipo->miembros()->detach(auth()->id());
            return response()->json(['message' => 'Has abandonado el equipo']);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al abandonar el equipo',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function unirseATorneo(Request $request, Equipo $equipo)
    {
        $request->validate([
            'torneo_id' => 'required|exists:torneos,id'
        ]);

        if (!$equipo->esCapitan(auth()->user())) {
            return response()->json(['error' => 'Solo el capitán puede inscribir al equipo'], 403);
        }

        try {
            $equipo->torneos()->attach($request->torneo_id, [
                'estado' => 'pendiente',
                'pago_confirmado' => false
            ]);

            return response()->json(['message' => 'Solicitud de inscripción enviada']);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error al inscribir al equipo',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}