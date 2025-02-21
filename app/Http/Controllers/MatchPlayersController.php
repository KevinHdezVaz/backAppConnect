<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
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