<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Equipo;
use App\Models\DailyMatch; // Añade el modelo DailyMatch

class HomeController extends Controller
{
    public function home()
    {
        $monthLabels = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
        
        $monthlyUsers = User::selectRaw('MONTH(created_at) as month, COUNT(*) as count')
            ->whereYear('created_at', now()->year)
            ->groupBy('month')
            ->pluck('count', 'month')
            ->toArray();
            
        $monthlyTeams = Equipo::selectRaw('MONTH(created_at) as month, COUNT(*) as count')
            ->whereYear('created_at', now()->year)
            ->groupBy('month')
            ->pluck('count', 'month')
            ->toArray();

        $userData = array_map(function($month) use ($monthlyUsers) {
            return $monthlyUsers[$month] ?? 0;
        }, range(1, 12));

        $teamData = array_map(function($month) use ($monthlyTeams) {
            return $monthlyTeams[$month] ?? 0;
        }, range(1, 12));

        // Cargar todos los partidos diarios
        $matches = DailyMatch::with(['field', 'teams'])
            ->orderBy('schedule_date', 'desc')
            ->orderBy('start_time', 'asc')
            ->get();

        return view('dashboard', [
            'newUsersCount' => User::whereMonth('created_at', now()->month)->count(),
            'newTeamsCount' => Equipo::whereMonth('created_at', now()->month)->count(),
            'monthLabels' => $monthLabels,
            'userData' => $userData,
            'teamData' => $teamData,
            'matches' => $matches // Añadir los partidos a la vista
        ]);
    }
}