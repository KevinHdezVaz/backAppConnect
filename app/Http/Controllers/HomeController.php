<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Equipo;

class HomeController extends Controller
{
  // HomeController.php
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

    return view('dashboard', [
        'newUsersCount' => User::whereMonth('created_at', now()->month)->count(),
        'newTeamsCount' => Equipo::whereMonth('created_at', now()->month)->count(),
        'monthLabels' => $monthLabels,
        'userData' => $userData,
        'teamData' => $teamData
    ]);
}
}