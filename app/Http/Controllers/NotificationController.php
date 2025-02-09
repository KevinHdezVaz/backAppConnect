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
        \Log::info('Recibiendo player_id request', [
            'data' => $request->all(),
            'headers' => $request->headers->all()
        ]);
    
        try {
            $request->validate([
                'player_id' => 'required|string|max:255', // Agrega validación de longitud
            ]);
    
            $token = DeviceToken::updateOrCreate(
                ['player_id' => $request->player_id],
                ['player_id' => $request->player_id]
            );
    
            \Log::info('Player ID guardado exitosamente', ['player_id' => $request->player_id]);
    
            return response()->json(['message' => 'Player ID almacenado correctamente']);
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
        $ONESIGNAL_APP_ID = env('ONESIGNAL_APP_ID');
         $ONESIGNAL_REST_API_KEY = base64_encode(env('ONESIGNAL_REST_API_KEY'));
        $fields = [
            'app_id' => $ONESIGNAL_APP_ID,
            'include_player_ids' => $playerIds,
            'contents' => ['en' => $message],
            'headings' => ['en' => $title]
        ];

        $fields = json_encode($fields);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://onesignal.com/api/v1/notifications');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json; charset=utf-8',
            'Authorization: Basic ' . $ONESIGNAL_REST_API_KEY,
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            $error_msg = curl_error($ch);
            curl_close($ch);
            \Log::error('Error al enviar notificación a OneSignal: ' . $error_msg);
            return json_encode(['error' => $error_msg]);
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
