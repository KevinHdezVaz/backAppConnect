<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\NotificationEvent;
use App\Models\DailyMatch;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\NotificationController;

class ProcessNotifications extends Command
{
    protected $signature = 'notifications:process';
    protected $description = 'Process scheduled notifications';

    public function handle()
    {
        $this->info('Iniciando proceso de notificaciones...');


        \Log::info('Verificando notificaciones programadas', [
            'current_time' => now(),
            'checking_before' => now()->addMinutes(5)
        ]);
        
      
        $pendingNotifications = NotificationEvent::where('is_sent', false)
            ->where('scheduled_at', '<=', now())
            ->with(['equipoPartido'])
            ->get();

        $this->info('Notificaciones pendientes encontradas: ' . $pendingNotifications->count());

        \Log::info('Notificaciones encontradas', [
            'count' => $pendingNotifications->count(),
            'notifications' => $pendingNotifications->toArray()
        ]);

        \Log::info('Notificaciones pendientes', [
            'count' => $pendingNotifications->count(),
            'notifications' => $pendingNotifications->toArray()
        ]);

        if ($pendingNotifications->isEmpty()) {
            $this->info('No hay notificaciones pendientes para procesar.');
            return;
        }

        foreach ($pendingNotifications as $notification) {
            $this->info('Procesando notificación ID: ' . $notification->id);
            
            $match = $notification->equipoPartido;
            if (!$match) {
                $this->error('Partido no encontrado para notificación ID: ' . $notification->id);
                continue;
            }

            $playerIds = DB::table('match_team_players')
                ->join('users', 'match_team_players.user_id', '=', 'users.id')
                ->join('device_tokens', 'users.id', '=', 'device_tokens.user_id')
                ->whereIn('match_team_players.match_team_id', function($query) use ($match) {
                    $query->select('id')
                          ->from('match_teams')
                          ->where('equipo_partido_id', $match->id);
                })
                ->pluck('device_tokens.player_id')
                ->toArray();

            $this->info('Jugadores encontrados: ' . count($playerIds));
            \Log::info('Player IDs encontrados', ['ids' => $playerIds]);

            if (!empty($playerIds)) {
                try {
                    $title = "Recordatorio de partido";
                    $message = "Tu partido {$match->name} comienza en 1 hora";
                    
                    $response = app(NotificationController::class)->sendOneSignalNotification(
                        $playerIds,
                        $message,
                        $title
                    );

                    $this->info('Respuesta de OneSignal: ' . $response);
                    \Log::info('Respuesta de OneSignal', ['response' => $response]);
                } catch (\Exception $e) {
                    $this->error('Error enviando notificación: ' . $e->getMessage());
                    \Log::error('Error en notificación', [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            }

            $notification->update(['is_sent' => true]);
            $this->info('Notificación marcada como enviada');
        }

        $this->info('Proceso completado');
    }
}