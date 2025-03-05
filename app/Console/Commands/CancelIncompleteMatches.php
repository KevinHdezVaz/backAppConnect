<?php

namespace App\Console\Commands;

use App\Models\DailyMatch;
use App\Models\Booking;
use App\Models\User;
use App\Services\WalletService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\NotificationController;

class CancelIncompleteMatches extends Command
{
    protected $signature = 'matches:cancel-incomplete';
    protected $description = 'Cancela partidos incompletos una hora antes de empezar y reembolsa a los usuarios';

    protected $walletService;

    public function __construct(WalletService $walletService)
    {
        parent::__construct();
        $this->walletService = $walletService;
    }

    public function handle()
    {
        Log::info('Ejecutando comando matches:cancel-incomplete');

        $now = Carbon::now();
        $oneHourFromNow = $now->copy()->addHour();

        $matches = DailyMatch::where('status', 'open')
            ->where('schedule_date', $now->toDateString())
            ->whereBetween('start_time', [$now->toTimeString(), $oneHourFromNow->toTimeString()])
            ->with('players.user') // Cargar jugadores y sus usuarios
            ->get();

        foreach ($matches as $match) {
            if ($match->player_count < $match->max_players) {
                DB::transaction(function () use ($match) {
                    // Cambiar estado a 'cancelled'
                    $match->update(['status' => 'cancelled']);
                    Log::info("Partido {$match->id} cancelado por estar incompleto", [
                        'player_count' => $match->player_count,
                        'max_players' => $match->max_players,
                    ]);

                    // Cancelar la reserva asociada (si existe)
                    $booking = Booking::where('daily_match_id', $match->id)->first();
                    if ($booking) {
                        $booking->update(['status' => 'cancelled']);
                        Log::info("Reserva {$booking->id} cancelada");
                    }

                    // Reembolsar a los jugadores
                    $players = $match->players;
                    foreach ($players as $player) {
                        $user = $player->user;
                        $amount = $match->price; // Precio por jugador

                        // Usar WalletService para depositar el reembolso
                        $this->walletService->deposit($user, $amount, "Reembolso por cancelación del partido {$match->name} (ID: {$match->id})");

                        Log::info("Reembolsado $amount a usuario {$user->id} por partido {$match->id}");
                    }

                    // Notificar a los jugadores sobre la cancelación del partido
                    $this->notifyPlayersAboutMatchCancellation($match, $players);
                });
            }
        }

        $this->info('Comando ejecutado con éxito');
    }

    private function notifyPlayersAboutMatchCancellation(DailyMatch $match, $players)
    {
        $playerIds = $players->pluck('player_id')->toArray();
        if (!empty($playerIds)) {
            $message = "El partido {$match->name} del {$match->schedule_date} a las {$match->start_time} fue cancelado por no completarse. Se ha reembolsado el monto a tu monedero.";
            $title = "Partido Cancelado";

            $notificationController = app(NotificationController::class);
            $notificationController->sendOneSignalNotification(
                $playerIds,
                $message,
                $title,
                ['type' => 'match_cancellation', 'match_id' => $match->id] // Añadir datos adicionales
            );

            Log::info('Notificación enviada a jugadores por cancelación', ['match_id' => $match->id]);
        }
    }
}