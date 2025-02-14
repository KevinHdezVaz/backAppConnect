<?php

namespace App\Http\Controllers;

use App\Models\DailyMatch;
use App\Models\Field;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DailyMatchController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'field_id' => 'required|exists:fields,id',
            'max_players' => 'required|integer|min:5|max:11',
            'price' => 'required|numeric|min:0',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'days' => 'required|array',
            'days.*' => 'array'
        ]);

        DB::beginTransaction();
        
        try {
            $startDate = Carbon::parse($request->start_date);
            $endDate = $request->end_date ? Carbon::parse($request->end_date) : $startDate->copy()->addMonths(1);
            $currentDate = $startDate->copy();
            $partidos_creados = 0;

            while ($currentDate <= $endDate) {
                $dayName = strtolower($currentDate->locale('es')->dayName);
                $dayName = str_replace(
                    ['á','é','í','ó','ú'], 
                    ['a','e','i','o','u'], 
                    $dayName
                );

                if (isset($request->days[$dayName]) && $request->days[$dayName]['active'] === 'on') {
                    $partido = DailyMatch::create([
                        'name' => $request->name,
                        'field_id' => $request->field_id,
                        'max_players' => $request->max_players,
                        'player_count' => 0,
                        'schedule_date' => $currentDate->format('Y-m-d'),
                        'start_time' => $request->days[$dayName]['start_time'],
                        'end_time' => $request->days[$dayName]['end_time'],
                        'price' => $request->price,
                        'status' => 'open'
                    ]);

                    $partidos_creados++;
                }

                $currentDate->addDay();
            }

            DB::commit();

            if ($partidos_creados > 0) {
                return redirect()->route('daily-matches.index')
                    ->with('success', "Se crearon {$partidos_creados} partidos exitosamente");
            } else {
                return back()
                    ->with('warning', 'No se crearon partidos. Verifica los días seleccionados.');
            }

        } catch (\Exception $e) {
            DB::rollBack();
            return back()
                ->with('error', 'Error al crear los partidos: ' . $e->getMessage())
                ->withInput();
        }
    }

    public function index()
    {
        $matches = DailyMatch::with(['field'])
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


public function getAvailableMatches()
{
    $matches = DailyMatch::with(['field'])
        ->where('schedule_date', '>=', now()->format('Y-m-d'))
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
    if ($match->player_count >= $match->max_players) {
        return response()->json([
            'message' => 'El partido está lleno'
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

    DB::transaction(function () use ($match, $request) {
        MatchPlayer::create([
            'match_id' => $match->id,
            'player_id' => $request->user()->id,
            'position' => $request->position
        ]);

        $match->increment('player_count');
    });

    return response()->json([
        'message' => 'Te has unido al partido exitosamente'
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
        $player->delete();
        $match->decrement('player_count');
    });

    return response()->json([
        'message' => 'Has abandonado el partido'
    ]);
}

public function getMatchPlayers(DailyMatch $match)
{
    $players = $match->players()->with('user')->get();

    return response()->json([
        'players' => $players
    ]);
}

}