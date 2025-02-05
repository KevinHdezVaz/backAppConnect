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
        $equipos = Equipo::with('miembros')->get();
        return response()->json($equipos);
    }

 
    // app/Http/Controllers/API/EquipoController.php
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