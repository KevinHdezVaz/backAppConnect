<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ResetController;
use App\Http\Controllers\StoryController;
use App\Http\Controllers\InfoUserController;
use App\Http\Controllers\RegisterController;
use App\Http\Controllers\SessionsController;
use App\Http\Controllers\API\FieldController;
use App\Http\Controllers\DailyMatchController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Torneo\TorneoController;
use App\Http\Controllers\ChangePasswordController;
use App\Http\Controllers\UserManagementController;
use App\Http\Controllers\FieldManagementController;

// Rutas públicas/guest
Route::middleware(['guest'])->group(function () {
    Route::get('/login', [SessionsController::class, 'create'])->name('login');
    Route::post('/login', [SessionsController::class, 'store']);
    Route::get('/register', [RegisterController::class, 'create']);
    Route::post('/register', [RegisterController::class, 'store']);
    Route::get('/login/forgot-password', [ResetController::class, 'create']);
    Route::post('/forgot-password', [ResetController::class, 'sendEmail']);
    Route::get('/reset-password/{token}', [ResetController::class, 'resetPass'])->name('password.reset');
    Route::post('/reset-password', [ChangePasswordController::class, 'changePassword'])->name('password.update');
});

 
// Rutas protegidas para admin
Route::middleware(['auth:admin'])->group(function () {
 
    Route::get('/', [HomeController::class, 'home']);
    Route::get('/dashboard', [HomeController::class, 'home'])->name('dashboard');   

  
    Route::get('/stories', [StoryController::class, 'index'])->name('admin.stories.index');
    Route::get('/stories/create', [StoryController::class, 'create'])->name('admin.stories.create');
    Route::post('/stories', [StoryController::class, 'store'])->name('admin.stories.store');
    Route::delete('/stories/{story}', [StoryController::class, 'destroy'])->name('admin.stories.destroy');


    Route::get('/daily-matches', [DailyMatchController::class, 'index']);
    Route::delete('/daily-matches/{match}', [DailyMatchController::class, 'destroy'])->name('daily-matches.destroy');
     Route::get('/daily-matches/create', [DailyMatchController::class, 'create'])->name('daily-matches.create');
    Route::post('/daily-matches', [DailyMatchController::class, 'store'])->name('daily-matches.store');
    Route::get('/daily-matches', [DailyMatchController::class, 'index'])->name('daily-matches.index');

    Route::get('/billing', function () {
        return view('billing');
    })->name('billing');
    
    Route::get('/profile', function () {
        return view('profile');
    })->name('profile');
    
    Route::get('/rtl', function () {
        return view('rtl');
    })->name('rtl');
    
    Route::get('/tables', function () {
        return view('tables');
    })->name('tables');
    
    Route::get('/virtual-reality', function () {
        return view('virtual-reality');
    })->name('virtual-reality');


//    Route::resource('field', FieldController::class);


//Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications');
 
// Ruta para mostrar el historial de notificaciones
Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
  Route::post('/notifications', [NotificationController::class, 'store'])->name('notifications.store');
 Route::delete('/notifications/{notification}', [NotificationController::class, 'destroy'])->name('notifications.destroy');
 Route::get('/notifications/create', [NotificationController::class, 'create'])->name('notifications.create');

 //torneo
 Route::get('/tournament', [TorneoController::class, 'index'])->name('torneos.index');
 Route::get('/tournament/create', [TorneoController::class, 'create'])->name('torneos.create');
Route::post('/tournament', [TorneoController::class, 'store'])->name('torneos.store');
Route::get('/tournament/{id}/edit', [TorneoController::class, 'edit'])->name('torneos.edit');
Route::put('/tournament/{id}', [TorneoController::class, 'update'])->name('torneos.update');
Route::delete('/tournament/{id}', [TorneoController::class, 'destroy'])->name('torneos.destroy');
Route::post('tournament/{id}/iniciar', [TorneoController::class, 'iniciarTorneo'])->name('torneos.iniciar');

Route::get('/user-management', [UserManagementController::class, 'index'])->name('user-management');
Route::get('/user-management/{id}/edit', [UserManagementController::class, 'edit'])->name('user.edit');
Route::put('/user-management/{id}', [UserManagementController::class, 'update'])->name('user.update');
Route::delete('/user-management/{id}', [UserManagementController::class, 'destroy'])->name('user.destroy');



Route::get('/field-management', [FieldManagementController::class, 'index'])->name('field-management');
Route::get('/field-management/create', [FieldManagementController::class, 'create'])->name('field-management.create');
Route::post('/field-management', [FieldManagementController::class, 'store'])->name('field-management.store');
Route::get('/field-management/{id}/edit', [FieldManagementController::class, 'edit'])->name('field-management.edit');
Route::put('/field-management/{id}', [FieldManagementController::class, 'update'])->name('field-management.update');
Route::delete('/field-management/{id}', [FieldManagementController::class, 'destroy'])->name('field-management.destroy');


    Route::get('/logout', [SessionsController::class, 'destroy']);
    Route::get('/user-profile', [InfoUserController::class, 'create']);
    Route::post('/user-profile', [InfoUserController::class, 'store']);
});