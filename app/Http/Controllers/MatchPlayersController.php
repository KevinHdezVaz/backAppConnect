<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Equipo;
use App\Models\MatchTeam;
use App\Models\DailyMatch;
use App\Models\DeviceToken;
use Illuminate\Http\Request;
use App\Models\EquipoPartido;
use App\Models\MatchTeamPlayer;
use App\Models\NotificationEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MatchPlayersController extends Controller
{
    public function getTeams($matchId)
    {
        try {
            \Log::info('Obteniendo equipos para partido: ' . $matchId);
    
            $equipos = MatchTeam::where('equipo_partido_id', $matchId)
                ->with(['players' => function($query) {
                    $query->join('users', 'match_team_players.user_id', '=', 'users.id')
                        ->select('match_team_players.*', 'users.name', 'users.profile_image');
                }])
                ->get()
                ->map(function($equipo) {
                    \Log::info('Equipo: ' . $equipo->name . ', Jugadores: ' . $equipo->players->count());
                    
                    return [
                        'id' => $equipo->id,
                        'name' => $equipo->name,
                        'player_count' => $equipo->player_count,
                        'color' => $equipo->color,
                        'emoji' => $equipo->emoji,
                        'max_players' => $equipo->max_players,
                        'players' => $equipo->players->map(function($player) {
                            \Log::info('Jugador encontrado:', [
                                'id' => $player->id,
                                'position' => $player->position,
                                'user' => [
                                    'id' => $player->user_id,
                                    'name' => $player->name,
                                    'profile_image' => $player->profile_image
                                ]
                            ]);
                            
                            return [
                                'id' => $player->id,
                                'position' => $player->position,
                                'equipo_partido_id' => $player->match_team_id,
                                'user' => [
                                    'id' => $player->user_id,
                                    'name' => $player->name,
                                    'profile_image' => $player->profile_image
                                ]
                            ];
                        })
                    ];
                });
    
            return response()->json(['equipos' => $equipos]);
    
        } catch (\Exception $e) {
            \Log::error('Error al obtener equipos: ' . $e->getMessage());
            \Log::error($e->getTraceAsString());
            return response()->json([
                'message' => 'Error al obtener equipos: ' . $e->getMessage()
            ], 500);
        }
    }
    public function registerPredefinedTeam(Request $request)
    {
        try {
            \Log::info('Registrando equipo predefinido para partido', ['request' => $request->all()]);
    
            $validated = $request->validate([
                'match_id' => 'required|exists:equipo_partidos,id',
                'predefined_team_id' => 'required|exists:equipos,id',
                'target_team_id' => 'required|exists:match_teams,id'
            ]);
    
            $match = EquipoPartido::findOrFail($validated['match_id']);
            if ($match->status !== 'open') {
                return response()->json(['message' => 'El partido no está disponible'], 400);
            }
    
            $predefinedTeam = Equipo::findOrFail($validated['predefined_team_id']);
            $user = auth()->user();
    
            // Verificar que el usuario sea el capitán del equipo
            if (!$predefinedTeam->esCapitan($user)) {
                return response()->json(['message' => 'Solo el capitán puede inscribir al equipo'], 403);
            }
    
            $activeMembers = $predefinedTeam->miembrosActivos()->get();
            if ($activeMembers->isEmpty()) {
                return response()->json(['message' => 'El equipo no tiene miembros activos'], 400);
            }
    
            return DB::transaction(function () use ($match, $predefinedTeam, $activeMembers, $validated) {
                // Verificar que el equipo destino esté disponible
                $targetTeam = MatchTeam::where('id', $validated['target_team_id'])
                    ->where('equipo_partido_id', $match->id)
                    ->where('player_count', 0)
                    ->first();
    
                if (!$targetTeam) {
                    return response()->json(['message' => 'El equipo seleccionado no está disponible'], 400);
                }
    
                // Verificar compatibilidad de tamaño
                if ($activeMembers->count() > $targetTeam->max_players) {
                    return response()->json(['message' => 'El equipo excede el límite de jugadores del partido'], 400);
                }
    
                // Registrar los miembros del equipo predefinido en el MatchTeam
                foreach ($activeMembers as $member) {
                    MatchTeamPlayer::create([
                        'match_team_id' => $targetTeam->id,
                        'user_id' => $member->id,
                        'position' => $member->pivot->posicion ?? 'Sin posición',
                    ]);
                }
    
                // Actualizar el contador y datos del equipo
                $targetTeam->player_count = $activeMembers->count();
                $targetTeam->name = $predefinedTeam->nombre;
                $targetTeam->color = $predefinedTeam->color_uniforme;
                $targetTeam->save();
    
                // Verificar si el partido está lleno
                $allTeams = MatchTeam::where('equipo_partido_id', $match->id)->get();
                $allTeamsFull = $allTeams->every(fn($t) => $t->player_count >= $t->max_players);
    
                if ($allTeamsFull) {
                    $match->status = 'full';
                    $match->save();
    
                    // Programar notificaciones
                    $matchStartTime = Carbon::createFromFormat('Y-m-d H:i:s', $match->schedule_date . ' ' . $match->start_time);
                    NotificationEvent::create([
                        'equipo_partido_id' => $match->id,
                        'event_type' => 'match_start',
                        'scheduled_at' => $matchStartTime,
                        'message' => 'Tu partido está por comenzar'
                    ]);
    
                    $matchEndTime = Carbon::createFromFormat('Y-m-d H:i:s', $match->schedule_date . ' ' . $match->end_time);
                    NotificationEvent::create([
                        'equipo_partido_id' => $match->id,
                        'event_type' => 'match_rating',
                        'scheduled_at' => $matchEndTime,
                        'message' => '¡El partido ha terminado! Califica a tus compañeros'
                    ]);
    
                    // Notificar a todos los jugadores
                    $playerIds = DeviceToken::whereHas('user', function($q) use ($match) {
                        $q->whereHas('matchTeamPlayers.team', function($q) use ($match) {
                            $q->where('equipo_partido_id', $match->id);
                        });
                    })->pluck('player_id')->toArray();
    
                    if (!empty($playerIds)) {
                        $notificationController = app(NotificationController::class);
                        $notificationController->sendOneSignalNotification(
                            $playerIds,
                            "¡El partido está completo! Nos vemos en la cancha",
                            "Partido Completo"
                        );
                    }
                } else {
                    // Notificar que un equipo se ha inscrito
                    $playerIds = DeviceToken::whereNotNull('user_id')
                        ->where('user_id', '!=', auth()->id())
                        ->pluck('player_id')
                        ->toArray();
    
                    if (!empty($playerIds)) {
                        $notificationController = app(NotificationController::class);
                        $notificationController->sendOneSignalNotification(
                            $playerIds,
                            "El equipo {$predefinedTeam->nombre} se ha inscrito en el partido {$match->name}",
                            "Nuevo equipo inscrito"
                        );
                    }
                }
    
                return response()->json([
                    'message' => 'Equipo predefinido inscrito exitosamente',
                    'match_team_id' => $targetTeam->id,
                ]);
            });
    
        } catch (\Exception $e) {
            Log::error('Error al inscribir equipo predefinido: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            return response()->json(['message' => 'Error al inscribir equipo: ' . $e->getMessage()], 500);
        }
    }
    // En MatchPlayersController.php
    public function isTeamCaptain(Request $request, $teamId)
    {
        try {
            $team = MatchTeam::with('players.user')->findOrFail($teamId);
            $userId = auth()->id();
    
            // Si es un equipo predefinido
            if ($team->name != "Equipo 1" && $team->name != "Equipo 2") {
                // Buscar en el equipo predefinido si el usuario es capitán
                $predefinedTeam = Equipo::where('nombre', $team->name)->first();
                if ($predefinedTeam) {
                    $isCaptain = $predefinedTeam->miembros()
                        ->where('user_id', $userId)
                        ->where('rol', 'capitan')
                        ->exists();
                    
                    return response()->json(['is_captain' => $isCaptain]);
                }
            }
            
            return response()->json(['is_captain' => false]);
        } catch (\Exception $e) {
            Log::error('Error checking captain status: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
    
    public function leaveTeamAsGroup(Request $request, $teamId)
    {
        try {
            return DB::transaction(function () use ($request, $teamId) {
                $team = MatchTeam::findOrFail($teamId);
                $match = EquipoPartido::findOrFail($team->equipo_partido_id);
                
                // Verificar si es capitán usando el método existente
                $captainResponse = $this->isTeamCaptain($request, $teamId);
                $isCaptain = $captainResponse->getData()->is_captain ?? false;
    
                if (!$isCaptain) {
                    throw new \Exception('Solo el capitán puede retirar al equipo completo');
                }
    
                // Eliminar todos los jugadores del equipo
                $team->players()->delete();
                
                // Restaurar el equipo a su estado original
                $team->update([
                    'player_count' => 0,
                    'name' => "Equipo " . ($team->id % 2 == 0 ? "2" : "1"),
                    'color' => 'Negro',
                    'emoji' => '⚫'
                ]);
    
                // Actualizar estado del partido si es necesario
                if ($match->status === 'full') {
                    $match->update(['status' => 'open']);
                }
    
                return response()->json([
                    'message' => 'Equipo retirado exitosamente'
                ]);
            });
        } catch (\Exception $e) {
            Log::error('Error leaving team as group: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    
    public function updatePlayerPosition(Request $request, $teamId, $playerId)
{
    try {
        \Log::info('Actualizando posición del jugador', [
            'team_id' => $teamId,
            'player_id' => $playerId,
            'position' => $request->position
        ]);

        $validated = $request->validate([
            'position' => 'required|string',
        ]);

        $player = MatchTeamPlayer::where('match_team_id', $teamId)
            ->where('id', $playerId)
            ->firstOrFail();

        $player->update([
            'position' => $validated['position']
        ]);

        return response()->json([
            'message' => 'Posición actualizada exitosamente'
        ]);

    } catch (\Exception $e) {
        \Log::error('Error al actualizar posición: ' . $e->getMessage());
        \Log::error($e->getTraceAsString());
        return response()->json([
            'message' => 'Error al actualizar posición: ' . $e->getMessage()
        ], 500);
    }
}


    public function getPredefinedTeams(Request $request) {
        $user = auth()->user();
        $teams = Equipo::whereHas('miembros', function ($query) use ($user) {
          $query->where('user_id', $user->id)->where('rol', 'capitan')->where('estado', 'activo');
        })->with('miembrosActivos')->get();
        return response()->json($teams);
      }


    public function joinTeam(Request $request)
    {
        try {
            \Log::info('Iniciando proceso de unión al equipo', [
                'request_data' => $request->all()
            ]);
    
            $validated = $request->validate([
                'match_id' => 'required|exists:equipo_partidos,id',
                'equipo_partido_id' => 'required|exists:match_teams,id',
                'position' => 'required|string',
            ]);
    
            // Verificar si el usuario ya está en algún equipo del partido
            $existingPlayer = MatchTeamPlayer::whereHas('team', function($query) use ($validated) {
                $query->where('equipo_partido_id', $validated['match_id']);
            })->where('user_id', auth()->id())->first();
    
            if ($existingPlayer) {
                return response()->json([
                    'message' => 'Ya estás registrado en este partido'
                ], 422);
            }
    
            // Verificar si el equipo está lleno
            $team = MatchTeam::find($validated['equipo_partido_id']);
            if ($team->player_count >= $team->max_players) {
                return response()->json([
                    'message' => 'El equipo está lleno'
                ], 422);
            }
    
            DB::transaction(function() use ($validated, $team) {
                // Crear el jugador
                MatchTeamPlayer::create([
                    'match_team_id' => $validated['equipo_partido_id'],
                    'user_id' => auth()->id(),
                    'position' => $validated['position'],
                ]);
    
                // Actualizar contador de jugadores
                $team->increment('player_count');
    
                // Verificar si el partido está lleno después de este jugador
                $match = DailyMatch::find($validated['match_id']);
                $allTeams = MatchTeam::where('equipo_partido_id', $match->id)->get();
                
                $allTeamsFull = $allTeams->every(function($team) {
                    return $team->player_count >= $team->max_players;
                });
    
                if ($allTeamsFull) {
                    // Actualizar estado del partido a lleno
                    $match->update(['status' => 'full']);
     
                    // Programar notificación para inicio del partido
                    $matchStartTime = Carbon::createFromFormat('Y-m-d H:i:s', $match->schedule_date->toDateString() . ' ' . $match->start_time);
                    NotificationEvent::create([
                        'equipo_partido_id' => $match->id,
                        'event_type' => 'match_start',
                        'scheduled_at' => $matchStartTime,
                        'message' => 'Tu partido está por comenzar'
                    ]);
    
                    // Programar notificación para evaluaciones
                    $matchEndTime = Carbon::createFromFormat('Y-m-d H:i:s', $match->schedule_date->toDateString() . ' ' . $match->end_time);
                    NotificationEvent::create([
                        'equipo_partido_id' => $match->id,
                        'event_type' => 'match_rating',
                        'scheduled_at' => $matchEndTime,
                        'message' => '¡El partido ha terminado! Califica a tus compañeros'
                    ]);
    
                    // Notificar a todos los jugadores que el partido está lleno
                    $playerIds = DeviceToken::whereHas('user', function($query) use ($match) {
                        $query->whereHas('matchTeamPlayers.team', function($q) use ($match) {
                            $q->where('equipo_partido_id', $match->id);
                        });
                    })->pluck('player_id')->toArray();
    
                    if (!empty($playerIds)) {
                        $notificationController = app(NotificationController::class);
                        $notificationController->sendOneSignalNotification(
                            $playerIds,
                            "¡El partido está completo! Nos vemos en la cancha",
                            "Partido Completo"
                        );
                    }
                } else {
                    // Enviar notificación normal de nuevo jugador
                    $playerIds = DeviceToken::whereNotNull('user_id')
                        ->where('user_id', '!=', auth()->id())
                        ->pluck('player_id')
                        ->toArray();
    
                    if (!empty($playerIds)) {
                        $notificationController = app(NotificationController::class);
                        $response = $notificationController->sendOneSignalNotification(
                            $playerIds,
                            "Un nuevo jugador se ha unido al " . $team->name . " en el partido " . $match->name,
                            "Nuevo jugador en partido"
                        );
    
                        \Log::info('Respuesta de OneSignal', [
                            'response' => json_decode($response, true)
                        ]);
                    }
                }
            });
    
            return response()->json(['message' => 'Te has unido al equipo exitosamente']);
    
        } catch (\Exception $e) {
            Log::error('Error al unirse al equipo: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            return response()->json([
                'message' => 'Error al unirse al equipo: ' . $e->getMessage()
            ], 500);
        }
    }
}