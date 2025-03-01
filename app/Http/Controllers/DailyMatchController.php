<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Field;
use App\Models\Booking;
use App\Models\MatchTeam;
use App\Models\DailyMatch;
use App\Models\DeviceToken;
use App\Models\MatchPlayer;
use App\Models\MatchRating;
use Illuminate\Http\Request;
use App\Services\WalletService;
use Illuminate\Support\Facades\DB;
use App\Models\NotificationEvent; // Agregar esta línea

class DailyMatchController extends Controller
{
    public function store(Request $request)
    {
        \Log::info('Iniciando creación de partidos diarios', ['request' => $request->all()]);
        
        $request->validate([
            'name' => 'required|string|max:255',
            'field_id' => 'required|exists:fields,id',
'game_type' => 'required|in:fut5,fut7,fut11',  // Ajustado para coincidir con el ENUM en equipo_partidos
            'price' => 'required|numeric|min:0',
            'week_selection' => 'required|in:current,next',
            'days' => 'required|array',
            'days.*' => 'array'
        ]);
 
        \Log::info('Validación pasada correctamente', ['game_type' => $request->game_type]);
        DB::beginTransaction();
        
        try {
            // Determinar la semana seleccionada

            $playersPerTeam = $request->game_type === 'fut5' ? 5 : ($request->game_type === 'fut7' ? 7 : 11);
                                                            $totalPlayers = $playersPerTeam ;

            $startOfWeek = now()->startOfWeek();
            if ($request->week_selection === 'next') {
                $startOfWeek->addWeek();
            }
            $endOfWeek = $startOfWeek->copy()->endOfWeek();
            
            \Log::info('Periodo seleccionado', [
                'semana' => $request->week_selection,
                'inicio' => $startOfWeek->format('Y-m-d'),
                'fin' => $endOfWeek->format('Y-m-d')
            ]);

            $partidos_creados = 0;
            $errores = [];

            // Mapeo de días en español
            $dayMapping = [
                'lunes' => 0,
                'martes' => 1,
                'miercoles' => 2,
                'jueves' => 3,
                'viernes' => 4,
                'sabado' => 5,
                'domingo' => 6
            ];

            foreach ($request->days as $dayName => $dayData) {
                \Log::info('Procesando día', [
                    'dia' => $dayName,
                    'datos' => $dayData
                ]);

                if (!isset($dayData['hours']) || empty($dayData['hours'])) {
                    \Log::info('No hay horas seleccionadas para', ['dia' => $dayName]);
                    continue;
                }

                if (!isset($dayMapping[$dayName])) {
                    \Log::warning('Día no reconocido', ['dia' => $dayName]);
                    continue;
                }

                $dayDate = $startOfWeek->copy()->addDays($dayMapping[$dayName]);

                foreach ($dayData['hours'] as $hour) {
                    $startTime = Carbon::parse($dayDate->format('Y-m-d') . ' ' . $hour);
                    $endTime = $startTime->copy()->addHour();

                    \Log::info('Verificando disponibilidad', [
                        'fecha' => $dayDate->format('Y-m-d'),
                        'hora_inicio' => $startTime->format('H:i'),
                        'hora_fin' => $endTime->format('H:i'),
                        'game_type' => $request->game_type
                    ]);

                    // Verificar si ya existe una reserva
                    $existingBooking = Booking::where('field_id', $request->field_id)
                        ->where('status', 'confirmed')
                        ->where(function($query) use ($startTime, $endTime) {
                            $query->whereBetween('start_time', [$startTime, $endTime])
                                ->orWhereBetween('end_time', [$startTime, $endTime])
                                ->orWhere(function($q) use ($startTime, $endTime) {
                                    $q->where('start_time', '<=', $startTime)
                                      ->where('end_time', '>=', $endTime);
                                });
                        })
                        ->first();

                    if ($existingBooking) {
                        \Log::warning('Horario ocupado', [
                            'fecha' => $dayDate->format('Y-m-d'),
                            'hora' => $hour,
                            'booking_id' => $existingBooking->id
                        ]);
                        $errores[] = "La cancha está ocupada el {$dayDate->format('d/m/Y')} a las {$hour}";
                        continue;
                    }

                    // Verificar si ya existe un partido en ese horario
                    $existingMatch = DailyMatch::where('field_id', $request->field_id)
                        ->where('schedule_date', $dayDate->format('Y-m-d'))
                        ->where('start_time', $hour)
                        ->first();

                    if ($existingMatch) {
                        \Log::warning('Ya existe un partido en este horario', [
                            'fecha' => $dayDate->format('Y-m-d'),
                            'hora' => $hour,
                            'partido_id' => $existingMatch->id
                        ]);
                        $errores[] = "Ya existe un partido el {$dayDate->format('d/m/Y')} a las {$hour}";
                        continue;
                    }

                    // Obtener los tipos de la cancha seleccionada
                    $field = Field::findOrFail($request->field_id);
                    \Log::debug('Tipos de la cancha:', ['types' => $field->types]);

                    $fieldTypes = json_decode($field->types, true) ?? [];
                    \Log::debug('Field types decodificados:', ['fieldTypes' => $fieldTypes]);

                    // Verificar si el game_type es válido para esta cancha
                    if (!in_array($request->game_type, $fieldTypes)) {
                        \Log::error('Tipo de partido inválido para la cancha', [
                            'game_type' => $request->game_type,
                            'field_types' => $fieldTypes
                        ]);
                        throw new \Exception('El tipo de partido seleccionado no es válido para esta cancha.');
                    }
                    

                    try {
                        $partido = DailyMatch::create([
                            'name' => $request->name,
                            'field_id' => $request->field_id,
                            'max_players' => $totalPlayers,  
                            'game_type' => $request->game_type, 
                            'player_count' => 0,
                            'schedule_date' => $dayDate->format('Y-m-d'),
                            'start_time' => $hour,
                            'end_time' => $endTime->format('H:i'),
                            'price' => $request->price,
                            'status' => 'open'
                        ]);

                         // Crear recordatorio 1 hora antes
            $matchDateTime = Carbon::parse($dayDate->format('Y-m-d') . ' ' . $hour);
            $reminderTime = $matchDateTime->copy()->subHour();
            
            NotificationEvent::create([
                'equipo_partido_id' => $partido->id,
                'event_type' => 'match_reminder',
                'scheduled_at' => $reminderTime,
            ]); 

// Crear equipos automáticamente
$colores = ['Rojo', 'Azul', 'Verde', 'Amarillo', 'Negro', 'Naranja'];  
$emojis = ['🔴', '🔵', '🟢', '🟡', '⚫', '🟠'];  

// Obtener dos índices aleatorios únicos
$coloresAleatorios = array_rand($colores, 2);
$numEquipos = 2;

foreach (range(1, $numEquipos) as $index) {
    MatchTeam::create([
        'equipo_partido_id' => $partido->id,
        'name' => "Equipo " . $index,
        'color' => $colores[$coloresAleatorios[$index - 1]],
        'emoji' => $emojis[$coloresAleatorios[$index - 1]], // Usamos el mismo índice para mantener la correspondencia color-emoji
        'player_count' => 0,
        'max_players' => $playersPerTeam // Usamos playersPerTeam para cada equipo
    ]);
}
                        \Log::info('Partido y equipos creados exitosamente', [
                            'id' => $partido->id,
                            'fecha' => $dayDate->format('Y-m-d'),
                            'hora' => $hour
                        ]);
                        $partidos_creados++;
                    } catch (\Exception $e) {
                        \Log::error('Error al crear partido individual', [
                            'error' => $e->getMessage(),
                            'stack' => $e->getTraceAsString(),
                            'datos' => [
                                'fecha' => $dayDate->format('Y-m-d'),
                                'hora' => $hour
                            ]
                        ]);
                        throw $e;
                    }
                }
            }

            DB::commit();

            if ($partidos_creados > 0) {
                $mensaje = "Se crearon {$partidos_creados} partidos exitosamente.";
                if (!empty($errores)) {
                    $mensaje .= " Algunos horarios no estaban disponibles: " . implode(", ", $errores);
                }
                return redirect()->route('daily-matches.index')
                    ->with('success', $mensaje);
            } else {
                return back()
                    ->with('warning', 'No se pudieron crear partidos. ' . implode(", ", $errores))
                    ->withInput();
            }

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error en la transacción principal', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return back()
                ->with('error', 'Error al crear los partidos: ' . $e->getMessage())
                ->withInput();
        }
    }

    public function destroy(DailyMatch $match)
{
    $match->delete();
    return redirect()->route('daily-matches.index')
        ->with('success', 'Partido eliminado exitosamente');
}

public function show($id)
{
    $match = DailyMatch::with('field')->find($id);
    
    if (!$match) {
        return response()->json(['message' => 'Partido no encontrado'], 404);
    }

    \Log::info('Mostrando partido', [
        'id' => $id,
        'field_id' => $match->field_id,
        'match' => $match->toArray()
    ]);

    return response()->json([
        'id' => $match->id,
        'name' => $match->name,
        'schedule_date' => $match->schedule_date->toIso8601String(),
        'start_time' => $match->start_time,
        'end_time' => $match->end_time,
        'game_type' => $match->game_type,
        'price' => $match->price,
        'field_id' => $match->field_id,
        'field' => $match->field
    ]);
}

public function index()
{
    $matches = DailyMatch::with(['field', 'teams'])
        ->orderByDesc('schedule_date')  // Ordena por fecha de partido (recientes primero)
        ->orderBy('start_time', 'asc')  // Dentro de la misma fecha, ordena por hora de inicio (más temprano primero)
        ->get();

    return view('laravel-examples.field-listPartidosDiarios', compact('matches'));
}


    public function create()
    {
        $fields = Field::all();
        return view('laravel-examples.field-addPartidosDiarios', compact('fields'));
    }
 
    public function getAvailableMatches()
    {
        \Log::info('Accediendo a getAvailableMatches');
        
        $now = now(); // Fecha y hora actual, ej. 2025-02-21 00:25
        $today = $now->format('Y-m-d');
        \Log::info('Fecha y hora actual', ['now' => $now->toDateTimeString()]);
        
        $matches = DailyMatch::with(['field', 'teams'])
            ->where(function ($query) use ($now) {
                $query->where('schedule_date', '>', $now->format('Y-m-d')) // Días futuros
                      ->orWhere(function ($query) use ($now) {
                          $query->where('schedule_date', $now->format('Y-m-d')) // Día actual
                                ->where('start_time', '>=', $now->format('H:i:s')); // Partidos no iniciados
                      });
            })
            ->where('status', 'open')
            ->orderBy('schedule_date')
            ->orderBy('start_time')
            ->get();
        
 
        
        return response()->json([
            'matches' => $matches
        ]);
    }
    public function joinMatch(Request $request, DailyMatch $match)
    {
        $request->validate([
            'team_id' => 'required|exists:match_teams,id'
        ]);

        $team = MatchTeam::findOrFail($request->team_id);

        if ($match->player_count >= $match->max_players) {
            return response()->json([
                'message' => 'El partido está lleno'
            ], 400);
        }

        if ($team->player_count >= $team->max_players) {
            return response()->json([
                'message' => 'El equipo está lleno'
            ], 400);
        }

        // Verificar si el jugador ya está inscrito
        $existingPlayer = MatchPlayer::where('match_id', $match->id)
            ->where('player_id', $request->user()->id)
            ->exists();

        if ($existingPlayer) {
            return response()->json([
                'message' => 'Ya estás inscrito en este partido'
            ], 400);
        }

        DB::transaction(function () use ($match, $request, $team) {
            try {
                \Log::info('Iniciando proceso de unión al equipo', [
                    'match_id' => $match->id,
                    'team_id' => $team->id,
                    'user_id' => $request->user()->id
                ]);
        
                MatchPlayer::create([
                    'match_id' => $match->id,
                    'player_id' => $request->user()->id,
                    'equipo_partido_id' => $team->id,
                    'position' => $request->position
                ]);
                
                $match->increment('player_count');
                $team->increment('player_count');
        
                // Crear recordatorio para este jugador
                $matchDateTime = Carbon::parse($match->schedule_date . ' ' . $match->start_time);
                $reminderTime = $matchDateTime->copy()->subHour();

             
        // Crear o actualizar el recordatorio
        NotificationEvent::updateOrCreate(
            [
                'equipo_partido_id' => $match->id,
                'event_type' => 'match_reminder'
            ],
            [
                'scheduled_at' => $reminderTime,
                'is_sent' => false
            ]
        );

        \Log::info('Recordatorio creado para el partido', [
            'match_id' => $match->id,
            'scheduled_at' => $reminderTime
        ]);

      // Esta parte envía la notificación inmediata a otros jugadores (NO al que se une)
$playerIds = DeviceToken::where('user_id', '!=', $request->user()->id)
->whereNotNull('user_id')
->pluck('player_id')
->toArray();


    \Log::info('Enviando notificación a jugadores', [
        'total_tokens' => count($playerIds),
        'current_user' => $request->user()->id
    ]);

    if (!empty($playerIds)) {
        $message = "Un nuevo jugador se ha unido al {$team->name} en el partido {$match->name}";
        $title = "Nuevo jugador en partido";

        $notificationController = app(NotificationController::class);
        $response = $notificationController->sendOneSignalNotification(
            $playerIds,
            $message,
            $title
        );

        \Log::info('Respuesta de OneSignal', [
            'response' => json_decode($response, true)
        ]);
    } else {
        \Log::warning('No se encontraron dispositivos para notificar');
    }
} catch (\Exception $e) {
    \Log::error('Error al enviar notificación', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}
        });
        return response()->json([
            'message' => 'Te has unido al equipo exitosamente'
        ]);
    }

    private function sendTeamNotification($match, $title, $message)
    {
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
    
        if (!empty($playerIds)) {
            app(NotificationController::class)->sendOneSignalNotification(
                $playerIds,
                $message,
                $title
            );
        }
    }

    public function getMatchesByField($fieldId)
{
    $now = now();
    
    $matches = DailyMatch::where('field_id', $fieldId)
        ->where(function ($query) use ($now) {
            $query->where('schedule_date', '>', $now->format('Y-m-d'))
                  ->orWhere(function ($q) use ($now) {
                      $q->where('schedule_date', $now->format('Y-m-d'))
                        ->where('start_time', '>=', $now->format('H:i:s'));
                  });
        })
        ->where('status', 'open')
        ->orderBy('schedule_date')
        ->orderBy('start_time')
        ->with(['teams'])
        ->get();

    return response()->json(['matches' => $matches]);
}

    public function getMatchesToRate()
    {
        try {
            $userId = auth()->id();
            $now = now();
            
            \Log::info('Iniciando búsqueda de partidos para calificar', [
                'user_id' => $userId,
                'current_time' => $now->format('Y-m-d H:i:s')
            ]);
    
            // Obtener partidos donde el usuario participó
            $matches = DailyMatch::query()
                ->where(function($query) use ($now) {
                    $query->where(function($q) use ($now) {
                        // Partidos de días anteriores
                        $q->where('schedule_date', '<', $now->format('Y-m-d'));
                    })->orWhere(function($q) use ($now) {
                        // Partidos del día actual que ya terminaron
                        $q->where('schedule_date', '=', $now->format('Y-m-d'))
                          ->where(\DB::raw("CONCAT(schedule_date, ' ', end_time)"), '<', $now->format('Y-m-d H:i:s'));
                    });
                })
                ->whereHas('teams.players', function($query) use ($userId) {
                    $query->where('user_id', $userId);
                })
                // Excluir partidos que ya fueron calificados por el usuario
                ->whereDoesntHave('ratings', function($query) use ($userId) {
                    $query->where('rater_user_id', $userId);
                })
                ->with(['teams.players.user', 'field'])
                ->get();
    
            \Log::info('Partidos encontrados para calificar', [
                'user_id' => $userId,
                'count' => $matches->count(),
                'matches' => $matches->map(function($match) {
                    return [
                        'id' => $match->id,
                        'date' => $match->schedule_date,
                        'end_time' => $match->end_time,
                        'current_time' => now()->format('Y-m-d H:i:s')
                    ];
                })
            ]);
    
            if ($matches->isEmpty()) {
                \Log::info('No se encontraron partidos para calificar', [
                    'user_id' => $userId
                ]);
            }
    
            return response()->json([
                'matches' => $matches->map(function($match) {
                    return [
                        'id' => $match->id,
                        'name' => $match->name,
                        'schedule_date' => $match->schedule_date,
                        'start_time' => $match->start_time,
                        'end_time' => $match->end_time,
                        'field' => [
                            'id' => $match->field->id,
                            'name' => $match->field->name
                        ],
                        'teams' => $match->teams->map(function($team) {
                            return [
                                'id' => $team->id,
                                'name' => $team->name,
                                'players' => $team->players->map(function($player) {
                                    return [
                                        'id' => $player->user->id,
                                        'name' => $player->user->name,
                                        'position' => $player->position,
                                        'profile_image' => $player->user->profile_image
                                    ];
                                })
                            ];
                        })
                    ];
                })
            ]);
    
        } catch (\Exception $e) {
            \Log::error('Error obteniendo partidos por calificar', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'message' => 'Error al obtener partidos por calificar',
                'error' => $e->getMessage()
            ], 500);
        }
    }

public function getMatchRatings($matchId)
{
    try {
        $userId = auth()->id();
        $match = DailyMatch::findOrFail($matchId);

        // Verificar que el usuario participó en el partido
        $participated = $match->teams()
            ->whereHas('players', function($query) use ($userId) {
                $query->where('user_id', $userId);
            })
            ->exists();

        if (!$participated) {
            return response()->json([
                'message' => 'No participaste en este partido'
            ], 403);
        }

        // Obtener el equipo del usuario
        $userTeam = $match->teams()
            ->whereHas('players', function($query) use ($userId) {
                $query->where('user_id', $userId);
            })
            ->with(['players.user'])
            ->first();

        // Verificar si ya calificó
        $hasRated = MatchRating::where([
            'match_id' => $matchId,
            'rater_user_id' => $userId
        ])->exists();

        return response()->json([
            'match' => [
                'id' => $match->id,
                'name' => $match->name,
                'date' => $match->schedule_date,
                'start_time' => $match->start_time,
                'end_time' => $match->end_time
            ],
            'team' => [
                'id' => $userTeam->id,
                'name' => $userTeam->name,
                'players' => $userTeam->players->map(function($player) {
                    return [
                        'id' => $player->user->id,
                        'name' => $player->user->name,
                        'position' => $player->position,
                        'profile_image' => $player->user->profile_image
                    ];
                })
            ],
            'has_rated' => $hasRated
        ]);

    } catch (\Exception $e) {
        \Log::error('Error obteniendo detalles de calificación', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

        return response()->json([
            'message' => 'Error al obtener detalles de calificación'
        ], 500);
    }
}



    public function leaveMatch(DailyMatch $match)
{
    \Log::info('Iniciando proceso de abandono de partido', [
        'match_id' => $match->id,
        'user_id' => auth()->id()
    ]);

    try {
        // Buscar el jugador usando las tablas correctas
        $playerRecord = DB::table('match_team_players')
            ->join('match_teams', 'match_teams.id', '=', 'match_team_players.match_team_id')
            ->where('match_teams.equipo_partido_id', $match->id)
            ->where('match_team_players.user_id', auth()->id())
            ->select(
                'match_team_players.id as player_id',
                'match_team_players.match_team_id',
                'match_teams.equipo_partido_id'
            )
            ->first();

        \Log::info('Búsqueda de jugador', [
            'player_record' => $playerRecord
        ]);

        if (!$playerRecord) {
            \Log::warning('Jugador no encontrado en el partido');
            return response()->json([
                'message' => 'No estás inscrito en este partido'
            ], 400);
        }

        DB::transaction(function () use ($playerRecord) {
            // 1. Eliminar el registro del jugador
            DB::table('match_team_players')
                ->where('id', $playerRecord->player_id)
                ->delete();

            \Log::info('Jugador eliminado de match_team_players');

            // 2. Decrementar el contador del equipo
            DB::table('match_teams')
                ->where('id', $playerRecord->match_team_id)
                ->decrement('player_count');

            \Log::info('Counter decrementado en match_teams');

            // 3. Decrementar el contador del partido
            DB::table('equipo_partidos')
                ->where('id', $playerRecord->equipo_partido_id)
                ->decrement('player_count');

            \Log::info('Counter decrementado en equipo_partidos');
        });

        \Log::info('Proceso de abandono completado exitosamente');

        return response()->json([
            'message' => 'Has abandonado el partido exitosamente'
        ]);

    } catch (\Exception $e) {
        \Log::error('Error al abandonar el partido', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

        return response()->json([
            'message' => 'Error al abandonar el partido: ' . $e->getMessage()
        ], 500);
    }
}

    public function getMatchPlayers(DailyMatch $match)
    {
        $teams = MatchTeam::where('equipo_partido_id', $match->id)
            ->with(['players.user'])
            ->get();

        return response()->json([
            'teams' => $teams
        ]);
    }

    public function getMatchTeams(DailyMatch $match)
    {
        $teams = MatchTeam::where('equipo_partido_id', $match->id)
            ->withCount('players')
            ->get();

        return response()->json([
            'teams' => $teams
        ]);
    }
}