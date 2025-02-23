<?php

namespace App\Http\Controllers;

use App\Models\DailyMatch;
use App\Models\MatchRating;
use App\Models\UserStats;
use App\Models\DeviceToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\User;  

class MatchRatingController extends Controller
{
    public function showRatingScreen($matchId)
{
    try {
        \Log::info("Iniciando showRatingScreen para matchId: $matchId, usuario: " . (auth()->check() ? auth()->id() : 'No autenticado'));
        
        if (!auth()->check()) {
            return response()->json(['message' => 'Usuario no autenticado'], 401);
        }

        $match = DailyMatch::with(['teams.players.user'])->find($matchId);
        
        if (!$match) {
            \Log::error("Partido no encontrado para matchId: $matchId");
            return response()->json(['message' => 'Partido no encontrado'], 404);
        }

        \Log::info("Partido encontrado: " . $match->id);
        
        $dateOnly = \Carbon\Carbon::parse($match->schedule_date)->toDateString();
        $matchEndTime = \Carbon\Carbon::parse($dateOnly . ' ' . $match->end_time);
        \Log::info("Hora de fin del partido: " . $matchEndTime);
        
        if ($matchEndTime->isFuture()) {
            return response()->json(['message' => 'El partido aún no ha terminado'], 403);
        }
    
        $userId = auth()->id();
        $userParticipated = $match->teams->flatMap->players
            ->contains('user_id', $userId);
        \Log::info("Usuario participó: " . ($userParticipated ? 'Sí' : 'No'));
        if (!$userParticipated) {
            return response()->json(['message' => 'No participaste en este partido'], 403);
        }
    
        $userTeam = $match->teams->first(function($team) use ($userId) {
            return $team->players->contains('user_id', $userId);
        });
        \Log::info("Equipo del usuario encontrado: " . ($userTeam ? $userTeam->id : 'Ninguno'));
        
        if (!$userTeam) {
            return response()->json(['message' => 'No se encontró el equipo del usuario'], 404);
        }
    
        $alreadyRated = MatchRating::where([
            'match_id' => $matchId,
            'rater_user_id' => $userId
        ])->exists();
        \Log::info("Ya calificó: " . ($alreadyRated ? 'Sí' : 'No'));
    
        $teamPlayers = $userTeam->players->map(function ($player) use ($userId) {
            // Excluir al usuario actual
            if ($player->user_id == $userId) {
                return null;
            }
            return [
                'user_id' => $player->user_id,
                'user' => [
                    'id' => $player->user->id,
                    'name' => $player->user->name ?? 'Jugador desconocido',
                    'profile_image' => $player->user->profile_image ?? null,
                ],
                'position' => $player->position, // Opcional: incluir posición si es necesario
            ];
        })->filter()->values()->all();

        \Log::info("Team players encontrados (como array): " . json_encode($teamPlayers, JSON_PRETTY_PRINT));

        return response()->json([
            'match' => $match,
            'team_players' => $teamPlayers, // Ahora incluye el campo 'user'
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
        \Log::info("Datos recibidos en submitRatings para matchId: $matchId", $request->all());

        $validated = $request->validate([
            'ratings' => 'required|array',
            'ratings.*.user_id' => 'required|exists:users,id',
            'ratings.*.attitude_rating' => 'required|integer|between:1,5',
            'ratings.*.participation_rating' => 'required|integer|between:1,5',
            'ratings.*.comment' => 'nullable|string|max:500',
            'mvp_vote' => 'required|exists:users,id'
        ]);

        \Log::info("Datos validados en submitRatings", $validated);

        $alreadyRated = MatchRating::where([
            'match_id' => $matchId,
            'rater_user_id' => auth()->id()
        ])->exists();

        if ($alreadyRated) {
            return response()->json(['message' => 'Ya has calificado este partido'], 422);
        }

        DB::transaction(function() use ($validated, $matchId) {
            foreach ($validated['ratings'] as $rating) {
                \Log::info("Guardando calificación para rated_user_id: " . $rating['user_id'], $rating);
                // Calcular la calificación general como promedio de actitud y participación
                $generalRating = round(($rating['attitude_rating'] + $rating['participation_rating']) / 2);

                MatchRating::create([
                    'match_id' => $matchId,
                    'rated_user_id' => $rating['user_id'],
                    'rater_user_id' => auth()->id(),
                    'rating' => $generalRating, // Calificación calculada
                    'attitude_rating' => $rating['attitude_rating'],
                    'participation_rating' => $rating['participation_rating'],
                    'comment' => $rating['comment'] ?? null,
                    'mvp_vote' => $rating['user_id'] == $validated['mvp_vote']
                ]);
            }

            $this->updateUserStats($matchId);
            $this->checkAndNotifyMVP($matchId);
        });

        return response()->json(['message' => 'Evaluaciones guardadas exitosamente']);
    } catch (\Exception $e) {
        \Log::error('Error al guardar evaluaciones: ' . $e->getMessage() . ' | Stack: ' . $e->getTraceAsString());
        return response()->json(['message' => 'Error al guardar las evaluaciones: ' . $e->getMessage()], 500);
    }
}

  
    
private function updateUserStats($matchId)
{
    try {
        $match = DailyMatch::findOrFail($matchId);
        $players = $match->teams->flatMap->players;

        foreach ($players as $player) {
            $stats = UserStats::firstOrCreate(['user_id' => $player->user_id]);
            
            $userRatings = MatchRating::where('rated_user_id', $player->user_id)->get();
            
            // Depuración de los ratings para el jugador
            \Log::info("Ratings para user_id: " . $player->user_id, $userRatings->toArray());

            // Calcular promedios, manejando NULL y asegurándonos de que siempre haya un valor
            $avgRating = $userRatings->isEmpty() ? 0 : $userRatings->avg('rating') ?? 0;
            $attitudeRatings = $userRatings->whereNotNull('attitude_rating')->pluck('attitude_rating')->all();
            $participationRatings = $userRatings->whereNotNull('participation_rating')->pluck('participation_rating')->all();

            $avgAttitude = empty($attitudeRatings) ? 0 : round(array_sum($attitudeRatings) / count($attitudeRatings), 2);
            $avgParticipation = empty($participationRatings) ? 0 : round(array_sum($participationRatings) / count($participationRatings), 2);
            $mvpCount = $userRatings->where('mvp_vote', true)->count();

            // Incrementar total_matches y actualizar otros campos por separado
            $stats->increment('total_matches');
            $stats->update([
                'average_rating' => round($avgRating, 2),
                'average_attitude' => $avgAttitude,
                'average_participation' => $avgParticipation,
                'mvp_count' => $mvpCount
            ]);

            \Log::info("Actualizando estadísticas para user_id: " . $player->user_id, [
                'total_matches' => $stats->total_matches,
                'average_rating' => round($avgRating, 2),
                'average_attitude' => $avgAttitude,
                'average_participation' => $avgParticipation,
                'mvp_count' => $mvpCount
            ]);
        }
    } catch (\Exception $e) {
        \Log::error('Error actualizando estadísticas: ' . $e->getMessage());
        throw $e; // Re-lanzar para que sea capturado por el transaction
    }
}


    private function checkAndNotifyMVP($matchId)
    {
        try {
            $match = DailyMatch::findOrFail($matchId);
            $totalPlayers = $match->teams->sum(function($team) {
                return $team->players->count();
            });
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
        } catch (\Exception $e) {
            \Log::error('Error notificando MVP: ' . $e->getMessage());
        }
    }

    public function getTopMvpPlayers()
    {
        try {
            $topPlayers = UserStats::with('user') // Relacionamos con la tabla users
                ->where('mvp_count', '>', 0) // Solo jugadores con al menos 1 MVP
                ->orderBy('mvp_count', 'desc')
                ->take(15) // Limitamos a los 10 mejores
                ->get()
                ->map(function ($stat) {
                    return [
                        'user_id' => $stat->user_id,
                        'name' => $stat->user->name ?? 'Jugador desconocido',
                        'profile_image' => $stat->user->profile_image ?? null,
                        'mvp_count' => $stat->mvp_count,
                    ];
                });

            return response()->json([
                'status' => 'success',
                'data' => $topPlayers
            ]);
        } catch (\Exception $e) {
            \Log::error('Error al obtener top MVP: ' . $e->getMessage());
            return response()->json(['message' => 'Error al obtener top MVP'], 500);
        }
    }public function getPlayerStats($userId)
    {
        try {
            $stats = UserStats::with(['user' => function ($query) {
                $query->select('id', 'name', 'profile_image', 'posicion');
            }])
                ->where('user_id', $userId)
                ->first();

            if (!$stats) {
                return response()->json([
                    'stats' => null,
                    'recent_matches' => [],
                    'match_history' => []
                ]);
            }

            $recentMatches = MatchRating::with('match')
                ->where(function ($query) use ($userId) {
                    $query->where('rater_user_id', $userId)->orWhere('rated_user_id', $userId);
                })
                ->orderBy('created_at', 'desc')
                ->distinct('match_id')
                ->take(5)
                ->get()
                ->map(function ($rating) {
                    // Calcular la calificación general como promedio de actitud y participación
                    $generalRating = $rating->attitude_rating && $rating->participation_rating
                        ? round(($rating->attitude_rating + $rating->participation_rating) / 2)
                        : 0;

                    return [
                        'match_id' => $rating->match_id,
                        'match_name' => $rating->match->name ?? 'Partido desconocido',
                        'rating' => $generalRating, // Calificación calculada
                        'attitude_rating' => $rating->attitude_rating, // Mostrar para referencia
                        'participation_rating' => $rating->participation_rating, // Mostrar para referencia
                        'comment' => $rating->comment ?? 'Sin comentario',
                        'created_at' => $rating->created_at,
                    ];
                });

            $matchesByMonth = MatchRating::with('match')
                ->where(function ($query) use ($userId) {
                    $query->where('rater_user_id', $userId)->orWhere('rated_user_id', $userId);
                })
                ->where('created_at', '>=', now()->subMonths(5))
                ->selectRaw('MONTH(created_at) as month, COUNT(DISTINCT match_id) as count')
                ->groupBy('month')
                ->orderBy('month', 'asc')
                ->get()
                ->map(function ($item) {
                    $months = ['Octubre', 'Noviembre', 'Diciembre', 'Enero', 'Febrero'];
                    return [
                        'month' => $months[$item->month - 1],
                        'count' => $item->count,
                    ];
                })->all();

            return response()->json([
                'stats' => [
                    'user_id' => $stats->user_id,
                    'name' => $stats->user->name ?? 'Jugador desconocido',
                    'profile_image' => $stats->user->profile_image ?? null,
                    'total_matches' => $stats->total_matches,
                    'average_rating' => $stats->average_rating,
                    'average_attitude' => $stats->average_attitude,
                    'average_participation' => $stats->average_participation,
                    'mvp_count' => $stats->mvp_count,
                    'posicion' => $stats->user->posicion ?? 'Sin especificar',
                   
                ],
                'recent_matches' => $recentMatches,
                'match_history' => $matchesByMonth
            ]);
        } catch (\Exception $e) {
            \Log::error('Error al obtener estadísticas: ' . $e->getMessage());
            return response()->json(['message' => 'Error al obtener estadísticas'], 500);
        }
    }
}