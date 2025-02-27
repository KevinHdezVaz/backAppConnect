<?php

namespace App\Http\Controllers;

use App\Models\MatchTeam;
use App\Models\MatchPlayer;
use Illuminate\Http\Request;
use App\Models\EquipoPartido;
use App\Models\MatchTeamPlayer;
use App\Services\WalletService;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\PaymentController;

class MatchTeamController extends Controller
{

    protected $walletService;

  
    public function __construct( WalletService $walletService)    {
        $this->walletService = $walletService;  
    }

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
        try {
            \Log::info('Iniciando proceso de unión al equipo', [
                'request_data' => $request->all()
            ]);
    
            $validated = $request->validate([
                'match_id' => 'required|exists:equipo_partidos,id',
                'equipo_partido_id' => 'required|exists:match_teams,id',
                'position' => 'required|string',
                'use_wallet' => 'boolean',
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
    
            $match = DailyMatch::find($validated['match_id']);
            $price = floatval($match->price);
            $useWallet = $request->input('use_wallet', false);
            $amountToPay = $price;

            return DB::transaction(function() use ($validated, $team, $match, $price, $useWallet) {
                $paymentId = null;

                if ($price > 0) {
                    if ($useWallet) {
                        $wallet = auth()->user()->wallet;
                        if (!$wallet || $wallet->balance < $price) {
                            throw new \Exception('Saldo insuficiente en el monedero');
                        }

                        // Debitar del monedero
                        $this->walletService->withdraw(
                            auth()->user(),
                            $price,
                            "Pago para unirse al equipo {$team->name} en partido #{$match->id}"
                        );
                        $amountToPay = 0;
                    } else {
                        // Aquí deberías implementar la lógica de MercadoPago si no usas monedero
                        // Por ahora, asumimos que el pago ya fue procesado en el frontend
                        throw new \Exception('Pago con MercadoPago no implementado en este ejemplo');
                    }
                }

                // Crear el jugador
                $player = MatchTeamPlayer::create([
                    'match_team_id' => $validated['equipo_partido_id'],
                    'user_id' => auth()->id(),
                    'position' => $validated['position'],
                    'payment_id' => $paymentId, // Null si se usa monedero o no hay pago
                ]);
    
                // Actualizar contador de jugadores
                $team->increment('player_count');
    
                // Verificar si el partido está lleno después de este jugador
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

                return response()->json([
                    'message' => 'Te has unido al equipo exitosamente',
                    'used_wallet' => $useWallet && $price > 0,
                    'amount_paid' => $price,
                ]);
            });
    
        } catch (\Exception $e) {
            Log::error('Error al unirse al equipo: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            return response()->json([
                'message' => 'Error al unirse al equipo: ' . $e->getMessage()
            ], 500);
        }
    }

    public function leaveTeam(Request $request, $teamId)
    {
        try {
            return DB::transaction(function () use ($request, $teamId) {
                $player = MatchTeamPlayer::where('match_team_id', $teamId)
                    ->where('user_id', auth()->id())
                    ->first();

                if (!$player) {
                    return response()->json(['error' => 'No estás en este equipo'], 404);
                }

                $team = MatchTeam::findOrFail($teamId);
                $match = EquipoPartido::findOrFail($team->equipo_partido_id);

                // Verificar si el usuario pagó para unirse
                $paymentId = $player->payment_id;
                $price = $match->price; // Asumiendo que el costo está en DailyMatch

                if ($paymentId && $price > 0) {
                    // Registrar reembolso al monedero
                    $this->walletService->refundBooking(
                        auth()->user(),
                        floatval($price),
                        "Reembolso por abandonar equipo en partido #{$match->id}"
                    );
                }

                // Eliminar al jugador del equipo
                $player->delete();
                $team->decrement('player_count');

                // Si el partido estaba lleno, reabrirlo
                if ($match->status === 'full') {
                    $match->update(['status' => 'open']);
                }

                \Log::info('Jugador abandonó equipo exitosamente', [
                    'user_id' => auth()->id(),
                    'team_id' => $teamId,
                    'match_id' => $match->id,
                    'refunded_amount' => $price > 0 ? $price : null
                ]);

                return response()->json([
                    'message' => 'Has abandonado el equipo exitosamente',
                    'refunded' => $paymentId && $price > 0,
                    'refunded_amount' => $price > 0 ? $price : null
                ]);
            });
        } catch (\Exception $e) {
            \Log::error('Error al abandonar equipo: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}