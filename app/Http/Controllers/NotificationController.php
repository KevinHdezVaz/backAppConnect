<?php
namespace App\Http\Controllers;

use App\Models\DeviceToken;
use App\Models\Notification;
use Illuminate\Http\Request;

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
        try {
            \Log::info('Recibiendo player_id request', [
                'data' => $request->all()
            ]);
    
            $request->validate([
                'player_id' => 'required|string|max:255',
                'user_id' => 'required|integer|exists:users,id' // Validar que el user_id exista en la tabla users
            ]);
    
            // Convertir user_id a integer si es necesario
            $userId = intval($request->user_id);
    
            // Verificar si el registro ya existe
            $existingToken = DeviceToken::where('player_id', $request->player_id)->first();
            \Log::info('Registro existente', [
                'existing_token' => $existingToken
            ]);
    
            // Si el registro ya existe, actualizar el user_id
            if ($existingToken) {
                $existingToken->user_id = $userId;
                $existingToken->save();
                $token = $existingToken;
            } else {
                // Si no existe, crear un nuevo registro
                $token = DeviceToken::create([
                    'player_id' => $request->player_id,
                    'user_id' => $userId
                ]);
            }
    
            \Log::info('Token guardado', [
                'token' => $token->fresh()->toArray() // Recargar el modelo para ver los datos actuales
            ]);
    
            return response()->json([
                'success' => true,
                'message' => 'Player ID almacenado correctamente',
                'token' => $token
            ]);
    
        } catch (\Exception $e) {
            \Log::error('Error al guardar player_id', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['error' => $e->getMessage()], 500);
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

        // Envía la notificación usando OneSignal
        $response = $this->sendOneSignalNotification($playerIds, $request->message, $request->title);

        // Guarda la notificación en el historial
        Notification::create([
            'title' => $request->title,
            'message' => $request->message,
            'player_ids' => json_encode($playerIds),
        ]);

        return response()->json(['message' => 'Notificación enviada', 'response' => json_decode($response)]);
    }
 
 
   

    private function sendOneSignalNotification($playerIds, $message, $title)
    {
        \Log::info('Preparando notificación OneSignal', [
            'app_id' => env('ONESIGNAL_APP_ID'),
            'has_key' => !empty(env('ONESIGNAL_REST_API_KEY')),
            'recipients' => count($playerIds)
        ]);
    
        $fields = [
            'app_id' => env('ONESIGNAL_APP_ID'),
            'include_player_ids' => $playerIds,
            'contents' => ['en' => $message],
            'headings' => ['en' => $title]
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
    
        \Log::info('Respuesta de OneSignal', [
            'http_code' => $httpcode,
            'response' => json_decode($response, true),
            'request' => $fields
        ]);
    
        if (curl_errno($ch)) {
            $error = curl_error($ch);
            \Log::error('Error CURL', ['error' => $error]);
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
