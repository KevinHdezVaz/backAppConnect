<?php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\StoryController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\WebhookController;
use App\Http\Controllers\API\FieldController;
use App\Http\Controllers\MatchTeamController;
use App\Http\Controllers\API\EquipoController;
use App\Http\Controllers\DailyMatchController;
use App\Http\Controllers\API\BookingController;
use App\Http\Controllers\MercadoPagoController;
use App\Http\Controllers\MatchPlayersController;
use App\Http\Controllers\API\TorneoAPIController;
use App\Http\Controllers\API\ChatMensajeController;
use App\Http\Middleware\ValidateMercadoPagoWebhook;
use App\Http\Controllers\API\NotificationApiController;


// Ruta para crear la preferencia de pago
Route::post('/payments/create-preference', [MercadoPagoController::class, 'createPreference'])
    ->middleware('auth:sanctum');

// Rutas para los callbacks de MP
Route::get('/payments/success', [MercadoPagoController::class, 'handleSuccess']);
Route::get('/payments/failure', [MercadoPagoController::class, 'handleFailure']);
Route::get('/payments/pending', [MercadoPagoController::class, 'handlePending']);

// Ruta para el webhook de MP
Route::post('/payments/webhook', [MercadoPagoController::class, 'handleWebhook']);
 
 // Rutas públicas para webhooks
Route::get('webhook/test', [WebhookController::class, 'test']);
Route::post('webhook/mercadopago', [WebhookController::class, 'handleMercadoPago']);



 //ruta para equipo
Route::middleware('auth:sanctum')->group(function () {

    Route::prefix('payments')->group(function () {
        Route::post('create-preference', [PaymentController::class, 'createPreference']);
        Route::get('success', [PaymentController::class, 'success'])->name('payments.success');
        Route::get('failure', [PaymentController::class, 'failure'])->name('payments.failure');
        Route::get('pending', [PaymentController::class, 'pending'])->name('payments.pending');
    });
 
        Route::post('/matches/join-team', [MatchPlayersController::class, 'joinTeam']);
        Route::get('/matches/{matchId}/teams', [MatchPlayersController::class, 'getTeams']);
    
        Route::get('/payments/verify-status/{paymentId}', [PaymentController::class, 'verifyPaymentStatus']);
 
  //  Route::get('/matches/{matchId}/teams', [MatchTeamController::class, 'getTeamsForMatch']);
   // Route::post('/matches/join-team', [MatchTeamController::class, 'joinTeam']);
    Route::post('/teams/{teamId}/leave', [MatchTeamController::class, 'leaveTeam']);

     Route::get('matches/{match}/teams', [DailyMatchController::class, 'getMatchTeams']);
    Route::get('/daily-matches', [DailyMatchController::class, 'getAvailableMatches']);
    Route::post('/daily-matches/{match}/join', [DailyMatchController::class, 'joinMatch']);
    Route::post('/daily-matches/{match}/leave', [DailyMatchController::class, 'leaveMatch']);
    Route::get('/daily-matches/{match}/players', [DailyMatchController::class, 'getMatchPlayers']);
    
     Route::post('equipos/{equipo}/invitar/codigo', [EquipoController::class, 'invitarPorCodigo']);
    Route::get('/equipos/buscar-usuario/{codigo}', [EquipoController::class, 'buscarUsuarioPorCodigo']);
    Route::get('/equipos', [EquipoController::class, 'index']);
    Route::post('/equipos', [EquipoController::class, 'store']);
    Route::post('/equipos/{equipo}/invitar', [EquipoController::class, 'invitarMiembro']);
    Route::post('/equipos/{equipo}/aceptar', [EquipoController::class, 'aceptarInvitacion']);
    Route::post('/equipos/{equipo}/abandonar', [EquipoController::class, 'abandonarEquipo']);
    Route::post('/equipos/{equipo}/torneos', [EquipoController::class, 'unirseATorneo']);
    Route::delete('equipos/{equipo}/miembros/{user}', [EquipoController::class, 'eliminarMiembro']);

    Route::post('equipos/{equipo}/torneos/inscribir', [EquipoController::class, 'inscribirseATorneo']);

    Route::post('equipos/{equipo}/unirse-abierto', [EquipoController::class, 'unirseAEquipoAbierto']);
    Route::post('equipos/{equipo}/solicitar-union', [EquipoController::class, 'solicitarUnirseAEquipoPrivado']);


     Route::get('equipos/invitaciones/pendientes', [EquipoController::class, 'getInvitacionesPendientes']);
    Route::post('equipos/{equipo}/aceptar', [EquipoController::class, 'aceptarInvitacion']);
    Route::post('equipos/{equipo}/rechazar', [EquipoController::class, 'rechazarInvitacion']);
    Route::get('equipos/invitaciones/pendientes/count', [EquipoController::class, 'getInvitacionesPendientesCount']);

    Route::post('equipos/{equipo}/torneos/{torneo}/inscribir-con-posiciones', [EquipoController::class, 'inscribirEquipoEnTorneo']);
});

Route::get('/stories', [StoryController::class, 'getStoriesApi']);

// Rutas para torneos
Route::group(['prefix' => 'torneos'], function () {
    Route::get('/', [TorneoAPIController::class, 'index']);
    Route::get('/{id}', [TorneoAPIController::class, 'show']);
    Route::get('/status/{status}', [TorneoAPIController::class, 'getTorneosByStatus']);
    Route::get('/filter/active', [TorneoAPIController::class, 'getActiveTournaments']);
    // Cambiar esta línea
    Route::get('/{torneoId}/equipos-disponibles', [EquipoController::class, 'equiposDisponibles']);
 });




 Route::post('/store-player-id', [NotificationApiController::class, 'storePlayerId']);



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

    Route::match(['PUT', 'POST'], '/profile', [AuthController::class, 'updateProfile']);

    Route::get('/fields/{field}/sync-hours', [FieldController::class, 'syncFieldHours']);
    Route::get('/fields/{field}/booked-hours', [FieldController::class, 'getBookedHours']);


    Route::get('/chat/equipos/{equipoId}/mensajes', [ChatMensajeController::class, 'getMensajesEquipo']);
    Route::post('/chat/mensaje', [ChatMensajeController::class, 'store']);


    Route::get('/fields', [FieldController::class, 'index']);
    Route::get('/fields/{field}', [FieldController::class, 'show']);
    Route::get('/fields/{field}/availability', [FieldController::class, 'checkAvailability']);
    Route::post('/bookings', [BookingController::class, 'store']);

    Route::get('/fields/{field}/booked-hours', [FieldController::class, 'getBookedHours']);
    Route::post('/fields/{field}/update-hours', [FieldController::class, 'updateAvailableHours']);

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
