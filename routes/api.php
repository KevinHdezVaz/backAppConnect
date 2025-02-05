<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\API\EquipoController;
use App\Http\Controllers\API\FieldController;
use App\Http\Controllers\API\BookingController;
use App\Http\Controllers\API\TorneoAPIController;

 //ruta para equipo 
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/equipos', [EquipoController::class, 'index']);
    Route::post('/equipos', [EquipoController::class, 'store']);
    Route::post('/equipos/{equipo}/invitar', [EquipoController::class, 'invitarMiembro']);
    Route::post('/equipos/{equipo}/aceptar', [EquipoController::class, 'aceptarInvitacion']);
    Route::post('/equipos/{equipo}/abandonar', [EquipoController::class, 'abandonarEquipo']);
    Route::post('/equipos/{equipo}/torneos', [EquipoController::class, 'unirseATorneo']);
});
  
    // Rutas para torneos
    Route::group(['prefix' => 'torneos'], function () {
        Route::get('/', [TorneoAPIController::class, 'index']);
        Route::get('/{id}', [TorneoAPIController::class, 'show']);
        Route::get('/status/{status}', [TorneoAPIController::class, 'getTorneosByStatus']);
        Route::get('/filter/active', [TorneoAPIController::class, 'getActiveTournaments']);
    });
 
Route::get('/fields/{field}/available-hours', [BookingController::class, 'getAvailableHours'])
    ->middleware('auth:sanctum');

    Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
        return $request->user();
    });
    
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/profile', [AuthController::class, 'profile']);
    Route::post('/logout', [AuthController::class, 'logout']);
    
    Route::get('/fields', [FieldController::class, 'index']);
    Route::get('/fields/{field}', [FieldController::class, 'show']);
    Route::get('/fields/{field}/availability', [FieldController::class, 'checkAvailability']);
    Route::post('/bookings', [BookingController::class, 'store']);
   
 
    Route::get('/bookings', [BookingController::class, 'index']);
    Route::post('/bookings', [BookingController::class, 'store']);
    Route::put('/bookings/{booking}/cancel', [BookingController::class, 'cancel']);
    Route::get('/active-reservations', [BookingController::class, 'getActiveReservations']);
    Route::get('/reservation-history', [BookingController::class, 'getReservationHistory']);


});
Route::post('/check-email', [AuthController::class, 'checkEmail']);
Route::post('/check-phone', [AuthController::class, 'checkPhone']);

Route::get('/auth/google', [AuthController::class, 'redirectToGoogle']);
Route::get('/auth/google/callback', [AuthController::class, 'handleGoogleCallback']);
Route::post('login/google', [AuthController::class, 'loginWithGoogle']);
 