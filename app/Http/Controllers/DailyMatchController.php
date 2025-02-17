<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Field;
use App\Models\Booking;
use App\Models\DailyMatch;
use App\Models\MatchTeam;
use App\Models\MatchPlayer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DailyMatchController extends Controller
{
    public function store(Request $request)
    {
        \Log::info('Iniciando creación de partidos diarios', ['request' => $request->all()]);
        
        $request->validate([
            'name' => 'required|string|max:255',
            'field_id' => 'required|exists:fields,id',
            'max_players' => 'required|integer|min:5|max:11',
            'price' => 'required|numeric|min:0',
            'week_selection' => 'required|in:current,next',
            'days' => 'required|array',
            'days.*' => 'array'
        ]);

        \Log::info('Validación pasada correctamente');
        DB::beginTransaction();
        
        try {
            // Determinar la semana seleccionada
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
                        'hora_fin' => $endTime->format('H:i')
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

                    try {
                        $partido = DailyMatch::create([
                            'name' => $request->name,
                            'field_id' => $request->field_id,
                            'max_players' => $request->max_players,
                            'player_count' => 0,
                            'schedule_date' => $dayDate->format('Y-m-d'),
                            'start_time' => $hour,
                            'end_time' => $endTime->format('H:i'),
                            'price' => $request->price,
                            'status' => 'open'
                        ]);

                        // Crear equipos automáticamente
                        $colores = ['Rojo', 'Azul', 'Verde', 'Amarillo', 'Blanco', 'Negro', 'Naranja'];  
                        $emojis = ['🔴', '🔵', '🟢', '🟡', '⚪', '⚫', '🟠'];  
                        
                        foreach (range(1, 7) as $index) {
                            MatchTeam::create([
                                'equipo_partido_id' => $partido->id,
                                'name' => "Equipo " . $index,
                                'color' => $colores[$index - 1],
                                'emoji' => $emojis[$index - 1],
                                'player_count' => 0,
                                'max_players' => ceil($request->max_players / 7)
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

    public function index()
    {
        $matches = DailyMatch::with(['field', 'teams'])
            ->orderBy('schedule_date', 'asc')
            ->orderBy('start_time', 'asc')
            ->get();
            
        return view('laravel-examples.field-listPartidosDiarios', compact('matches'));
    }

    public function create()
    {
        $fields = Field::all();
        return view('laravel-examples.field-addPartidosDiarios', compact('fields'));
    }

    // Métodos para la API (consumidos por Flutter)
    public function getAvailableMatches()
    {
        \Log::info('Accediendo a getAvailableMatches');
    
        $matches = DailyMatch::with(['field', 'teams'])
            ->where('schedule_date', '>=', now()->format('Y-m-d'))
            ->where('status', 'open')
            ->orderBy('schedule_date')
            ->orderBy('start_time')
            ->get();
    
        \Log::info('Partidos encontrados:', ['count' => $matches->count()]);
    
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
            MatchPlayer::create([
                'match_id' => $match->id,
                'player_id' => $request->user()->id,
                'equipo_partido_id' => $team->id, // Cambiado de team_id a equipo_partido_id
                'position' => $request->position
            ]);
            
            $match->increment('player_count');
            $team->increment('player_count');
        });

        return response()->json([
            'message' => 'Te has unido al equipo exitosamente'
        ]);
    }

    public function leaveMatch(DailyMatch $match)
    {
        $player = MatchPlayer::where('match_id', $match->id)
            ->where('player_id', auth()->id())
            ->first();

        if (!$player) {
            return response()->json([
                'message' => 'No estás inscrito en este partido'
            ], 400);
        }

        DB::transaction(function () use ($match, $player) {
            // Obtener el equipo antes de eliminar al jugador
            $team = MatchTeam::find($player->team_id);
            
            $player->delete();
            $match->decrement('player_count');
            
            // Decrementar el contador del equipo si existe
            if ($team) {
                $team->decrement('player_count');
            }
        });

        return response()->json([
            'message' => 'Has abandonado el partido'
        ]);
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