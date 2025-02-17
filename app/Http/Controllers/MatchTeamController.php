<?php

namespace App\Http\Controllers;

use App\Models\MatchTeam;
use App\Models\MatchPlayer;
use Illuminate\Http\Request;
use App\Models\EquipoPartido;
use App\Models\MatchTeamPlayer;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\PaymentController;

class MatchTeamController extends Controller
{
    public function getTeamsForMatch($matchId)
    {
        $teams = MatchTeam::with(['players.user'])
            ->where('equipo_partido_id', $matchId)
            ->get()
            ->map(function($team) {
                return [
                    'id' => $team->id,
                    'name' => $team->name,
                    'color' => $team->color,
                    'emoji' => $team->emoji,
                    'player_count' => $team->player_count,
                    'max_players' => $team->max_players,
                    'players' => $team->players->map(function($player) {
                        return [
                            'id' => $player->id,
                            'user' => [
                                'id' => $player->user->id,
                                'name' => $player->user->name,
                                'profile_image' => $player->user->profile_image
                            ],
                            'position' => $player->position
                        ];
                    })
                ];
            });

        return response()->json(['teams' => $teams]);
    }

    public function getMatchTeams(DailyMatch $match)
    {
        $teams = MatchTeam::where('equipo_partido_id', $match->id)
            ->with(['players' => function($query) {
                $query->join('users', 'match_players.player_id', '=', 'users.id')
                      ->select(
                          'match_players.*',
                          'users.name',
                          'users.profile_image',
                          'users.id as user_id'
                      );
            }])
            ->get();
    
        return response()->json([
            'teams' => $teams->map(function($team) {
                return [
                    'id' => $team->id,
                    'name' => $team->name,
                    'color' => $team->color,
                    'emoji' => $team->emoji,
                    'player_count' => $team->player_count,
                    'max_players' => $team->max_players,
                    'players' => $team->players->map(function($player) {
                        return [
                            'position' => $player->position,
                            'equipo_partido_id' => $player->equipo_partido_id,
                            'user' => [
                                'id' => $player->user_id,
                                'name' => $player->name,
                                'profile_image' => $player->profile_image
                            ]
                        ];
                    })
                ];
            })
        ]);
    }

    public function joinTeam(Request $request)
    {
        $request->validate([
            'team_id' => 'required|exists:match_teams,id',
            'position' => 'required|string',
            'payment_id' => 'required'  // ID del pago de MercadoPago
        ]);

        try {
            DB::beginTransaction();
            
            $team = MatchTeam::findOrFail($request->team_id);
            $match = DailyMatch::findOrFail($team->equipo_partido_id);

            // Verificar si el equipo está lleno
            if ($team->player_count >= $team->max_players) {
                return response()->json([
                    'message' => 'El equipo está lleno'
                ], 400);
            }

            // Verificar el pago
            $order = Order::where('payment_id', $request->payment_id)
                         ->where('status', 'completed')
                         ->first();

            if (!$order) {
                return response()->json([
                    'message' => 'Pago no encontrado o no completado'
                ], 400);
            }

            // Crear el jugador
            $player = MatchPlayer::create([
                'match_id' => $match->id,
                'player_id' => auth()->id(),
                'equipo_partido_id' => $team->id,
                'position' => $request->position,
                'payment_status' => 'completed',
                'payment_id' => $request->payment_id
            ]);

            // Incrementar contadores
            $team->increment('player_count');
            
            DB::commit();

            return response()->json([
                'message' => 'Te has unido al equipo exitosamente',
                'player' => $player
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error al unirse al equipo: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al unirse al equipo: ' . $e->getMessage()
            ], 500);
        }
    }


    public function leaveTeam(Request $request, $teamId)
    {
        $player = MatchTeamPlayer::where('match_team_id', $teamId)
            ->where('user_id', auth()->id())
            ->first();

        if (!$player) {
            return response()->json(['error' => 'No estás en este equipo'], 404);
        }

        $player->delete();
        $player->team->decrement('player_count');

        return response()->json(['message' => 'Has abandonado el equipo']);
    }
}