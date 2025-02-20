<?php

namespace App\Http\Controllers;

use App\Models\MatchTeam;
use App\Models\DailyMatch;
use App\Models\DeviceToken;
use Illuminate\Http\Request;
use App\Models\EquipoPartido;
use App\Models\MatchTeamPlayer;
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
    
                // Enviar notificación
                try {
                    $playerIds = DeviceToken::whereNotNull('user_id')
                        ->where('user_id', '!=', auth()->id())
                        ->pluck('player_id')
                        ->toArray();
    
                    \Log::info('Tokens encontrados para notificación', [
                        'count' => count($playerIds)
                    ]);
    
                    if (!empty($playerIds)) {
                        $match = DailyMatch::find($validated['match_id']);
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
                } catch (\Exception $e) {
                    \Log::error('Error enviando notificación', [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
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