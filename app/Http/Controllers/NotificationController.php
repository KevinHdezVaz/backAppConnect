<?php
namespace App\Http\Controllers;

use App\Models\DeviceToken;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NotificationController extends Controller
{
    public function index()
    {
        $notifications = Notification::all();
        return view('laravel-examples.field-notifications', compact('notifications'));
    }

    public function create()
    {
        return view('laravel-examples.field-notificationsCreate');
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string',
            'message' => 'required|string',
        ]);

        $playerIds = DeviceToken::pluck('player_id')->toArray();

        if (empty($playerIds)) {
            return redirect()->route('notifications.index')
                ->with('error', 'No hay dispositivos registrados para recibir la notificación.');
        }

        // Enviar la notificación a OneSignal
        $response = $this->sendOneSignalNotification($playerIds, $request->message, $request->title);

        // Guardar en la base de datos
        Notification::create([
            'title' => $request->title,
            'message' => $request->message,
            'player_ids' => json_encode($playerIds)
        ]);

        return redirect()->route('notifications.index')
            ->with('success', 'Notificación enviada exitosamente');
    }
    public function storePlayerId(Request $request)
{
    DB::beginTransaction();
    try {
        \Log::info('Inicio storePlayerId', [
            'request' => $request->all()
        ]);

        $playerId = $request->input('player_id');
        $userId = (int)$request->input('user_id');

        \Log::info('Datos procesados', [
            'player_id' => $playerId,
            'user_id' => $userId,
            'user_id_type' => gettype($userId)
        ]);

        // Actualización directa usando Query Builder
        $result = DB::table('device_tokens')
            ->where('player_id', $playerId)
            ->update([
                'user_id' => $userId,
                'updated_at' => now()
            ]);

        if ($result === 0) {
            // Si no se actualizó ningún registro, insertamos uno nuevo
            DB::table('device_tokens')->insert([
                'player_id' => $playerId,
                'user_id' => $userId,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }

        // Verificar que se guardó correctamente
        $token = DB::table('device_tokens')
            ->where('player_id', $playerId)
            ->first();

        \Log::info('Resultado final', [
            'token' => $token,
            'user_id_saved' => $token->user_id ?? null
        ]);

        DB::commit();

        return response()->json([
            'success' => true,
            'message' => 'Token guardado correctamente',
            'data' => $token
        ]);

    } catch (\Exception $e) {
        DB::rollBack();
        \Log::error('Error en storePlayerId', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

        return response()->json([
            'success' => false,
            'message' => 'Error al guardar token',
            'error' => $e->getMessage()
        ], 500);
    }
}


    public function sendNotification(Request $request)
    {
        $request->validate([
            'message' => 'required|string',
            'title' => 'required|string',
        ]);

        $playerIds = DeviceToken::pluck('player_id')->toArray();

        if (empty($playerIds)) {
            return response()->json(['error' => 'No hay dispositivos registrados'], 400);
        }

        // Enviar la notificación usando OneSignal
        $response = $this->sendOneSignalNotification($playerIds, $request->message, $request->title);

        // Guardar la notificación en el historial
        Notification::create([
            'title' => $request->title,
            'message' => $request->message,
            'player_ids' => json_encode($playerIds),
        ]);

        return response()->json(['message' => 'Notificación enviada', 'response' => json_decode($response)]);
    }

    public function sendOneSignalNotification($playerIds, $message, $title)
    {
        $fields = [
            'app_id' => env('ONESIGNAL_APP_ID'),
            'include_player_ids' => $playerIds,
            'contents' => ['en' => $message],
            'headings' => ['en' => $title],
            'small_icon' => 'ic_launcher',
            'large_icon' => 'ic_launcher',
            'android_group' => 'group_1'
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://onesignal.com/api/v1/notifications');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json; charset=utf-8',
            'Authorization: Basic ' . env('ONESIGNAL_REST_API_KEY')
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_VERBOSE, true);

        $response = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        Log::info('Respuesta de OneSignal', [
            'http_code' => $httpcode,
            'response' => json_decode($response, true),
            'request' => $fields
        ]);

        if (curl_errno($ch)) {
            $error = curl_error($ch);
            Log::error('Error CURL', ['error' => $error]);
            curl_close($ch);
            return json_encode(['error' => $error]);
        }

        curl_close($ch);
        return $response;
    }

    public function destroy(Notification $notification)
    {
        $notification->delete();
        return redirect()->route('notifications.index')
            ->with('success', 'Notificación eliminada exitosamente');
    }
}