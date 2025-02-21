<?php

namespace App\Http\Controllers;

use App\Models\DailyMatch;
use App\Models\MatchRating;
use App\Models\UserStats;
use App\Models\DeviceToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MatchRatingController extends Controller
{
    public function showRatingScreen($matchId)
    {
        try {
            \Log::info("Iniciando showRatingScreen para matchId: $matchId, usuario: " . auth()->id());
            
            $match = DailyMatch::with(['teams.players.user'])->findOrFail($matchId);
            \Log::info("Partido encontrado: " . $match->id);
            
            // Extraer solo la fecha de schedule_date y combinar con end_time
            $dateOnly = \Carbon\Carbon::parse($match->schedule_date)->toDateString(); // Ej. '2025-02-18'
            $matchEndTime = \Carbon\Carbon::parse($dateOnly . ' ' . $match->end_time);
            \Log::info("Hora de fin del partido: " . $matchEndTime);
            
            if ($matchEndTime->isFuture()) {
                return response()->json(['message' => 'El partido aún no ha terminado'], 403);
            }
    
            // Verificar participación del usuario
            $userParticipated = $match->teams->flatMap->players
                ->contains('user_id', auth()->id());
            \Log::info("Usuario participó: " . ($userParticipated ? 'Sí' : 'No'));
            if (!$userParticipated) {
                return response()->json(['message' => 'No participaste en este partido'], 403);
            }
    
            // Obtener equipo del usuario
            $userTeam = $match->teams->first(function($team) {
                return $team->players->contains('user_id', auth()->id());
            });
            \Log::info("Equipo del usuario encontrado: " . ($userTeam ? $userTeam->id : 'Ninguno'));
            
            if (!$userTeam) {
                return response()->json(['message' => 'No se encontró el equipo del usuario'], 404);
            }
    
            // Verificar si ya calificó
            $alreadyRated = MatchRating::where([
                'match_id' => $matchId,
                'rater_user_id' => auth()->id()
            ])->exists();
            \Log::info("Ya calificó: " . ($alreadyRated ? 'Sí' : 'No'));
    
            return response()->json([
                'match' => $match,
                'team_players' => $userTeam->players,
                'already_rated' => $alreadyRated,
                'can_rate' => !$alreadyRated && $matchEndTime->isPast()
            ]);
        } catch (\Exception $e) {
            \Log::error('Error al mostrar pantalla de evaluación: ' . $e->getMessage() . ' | Stack: ' . $e->getTraceAsString());
            return response()->json([
                'message' => 'Error al cargar la pantalla de evaluación: ' . $e->getMessage()
            ], 500);
        }
    }

    public function submitRatings(Request $request, $matchId)
    {
        try {
            $validated = $request->validate([
                'ratings' => 'required|array',
                'ratings.*.user_id' => 'required|exists:users,id',
                'ratings.*.rating' => 'required|integer|between:1,5',
                'ratings.*.comment' => 'nullable|string',
                'mvp_vote' => 'required|exists:users,id'
            ]);

            // Verificar que no haya calificado antes
            $alreadyRated = MatchRating::where([
                'match_id' => $matchId,
                'rater_user_id' => auth()->id()
            ])->exists();

            if ($alreadyRated) {
                return response()->json([
                    'message' => 'Ya has calificado este partido'
                ], 422);
            }

            DB::transaction(function() use ($validated, $matchId) {
                foreach ($validated['ratings'] as $rating) {
                    MatchRating::create([
                        'match_id' => $matchId,
                        'rated_user_id' => $rating['user_id'],
                        'rater_user_id' => auth()->id(),
                        'rating' => $rating['rating'],
                        'comment' => $rating['comment'] ?? null,
                        'mvp_vote' => $rating['user_id'] == $validated['mvp_vote']
                    ]);
                }

                // Actualizar estadísticas
                $this->updateUserStats($matchId);

                // Notificar al MVP si ya recibió suficientes votos
                $this->checkAndNotifyMVP($matchId);
            });

            return response()->json([
                'message' => 'Evaluaciones guardadas exitosamente'
            ]);
        } catch (\Exception $e) {
            \Log::error('Error al guardar evaluaciones: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al guardar las evaluaciones'
            ], 500);
        }
    }

    private function updateUserStats($matchId)
    {
        $match = DailyMatch::find($matchId);
        $players = $match->teams->flatMap->players;

        foreach ($players as $player) {
            $stats = UserStats::firstOrCreate(['user_id' => $player->user_id]);
            
            // Calcular promedios y totales
            $userRatings = MatchRating::where('rated_user_id', $player->user_id)->get();
            $avgRating = $userRatings->avg('rating');
            $mvpCount = $userRatings->where('mvp_vote', true)->count();
            
            $stats->update([
                'total_matches' => DB::raw('total_matches + 1'),
                'average_rating' => round($avgRating, 2),
                'mvp_count' => $mvpCount
            ]);
        }
    }

    private function checkAndNotifyMVP($matchId)
    {
        $match = DailyMatch::find($matchId);
        $totalPlayers = $match->teams->sum('player_count');
        $requiredVotes = ceil($totalPlayers * 0.75);

        $mvpVotes = DB::table('match_ratings')
            ->where('match_id', $matchId)
            ->where('mvp_vote', true)
            ->select('rated_user_id', DB::raw('count(*) as vote_count'))
            ->groupBy('rated_user_id')
            ->having('vote_count', '>=', $requiredVotes)
            ->first();

        if ($mvpVotes) {
            $playerIds = DeviceToken::where('user_id', $mvpVotes->rated_user_id)
                ->pluck('player_id')
                ->toArray();

            if (!empty($playerIds)) {
                $notificationController = app(NotificationController::class);
                $notificationController->sendOneSignalNotification(
                    $playerIds,
                    "¡Felicidades! Has sido elegido como el MVP del partido",
                    "MVP del Partido"
                );
            }
        }
    }

    public function getPlayerStats($userId)
    {
        try {
            $stats = UserStats::where('user_id', $userId)->first();
            $recentRatings = MatchRating::with('match')
                ->where('rated_user_id', $userId)
                ->orderBy('created_at', 'desc')
                ->take(5)
                ->get();

            return response()->json([
                'stats' => $stats,
                'recent_ratings' => $recentRatings
            ]);
        } catch (\Exception $e) {
            \Log::error('Error al obtener estadísticas: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al obtener estadísticas'
            ], 500);
        }
    }
}