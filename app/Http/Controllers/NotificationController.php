<?php
namespace App\Http\Controllers;

use App\Models\DeviceToken;
use App\Models\Notification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{

    public function index()
    {
        $notifications = Notification::all(); // O ajusta según tu lógica
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

        // Enviar la notificación a OneSignal
        $response = $this->sendOneSignalNotification(
            $playerIds, 
            $request->message,
            $request->title
        );

        // Guardar en la base de datos
        Notification::create([
            'title' => $request->title,
            'message' => $request->message,
            'player_ids' => $playerIds
        ]);

        return redirect()->route('notifications.index')
            ->with('success', 'Notificación enviada exitosamente');
    }


    // Almacena el playerId en la base de datos
    public function storePlayerId(Request $request)
    {
        $request->validate([
            'player_id' => 'required|string',
        ]);

        DeviceToken::create([
            'player_id' => $request->player_id,
        ]);

        return response()->json(['message' => 'Player ID almacenado correctamente']);
    }

    // Envía una notificación a todos los dispositivos
 
    public function sendNotification(Request $request)
    {
        $request->validate([
            'message' => 'required|string',
        ]);
    
        $playerIds = DeviceToken::pluck('player_id')->toArray();
    
        // Envía la notificación usando OneSignal
        $response = $this->sendOneSignalNotification($playerIds, $request->message);
    
        // Guarda la notificación en el historial
        Notification::create([
            'message' => $request->message,
            'player_ids' => json_encode($playerIds),
        ]);
    
        return response()->json(['message' => 'Notificación enviada', 'response' => $response]);
    }
    // Método para enviar notificaciones usando la API de OneSignal
     private function sendOneSignalNotification($playerIds, $message, $title)
    {
        $appId = '90fd23c4-a605-40ed-ab39-78405c75a705';
        $restApiKey = 'os_v2_app_ajg7bs7h3fa3vm7tjxzf3pr5b7uianjfekqudzm5fmmc25wlbfdvpeewonbrbr7plrmzm5a4gcbxhyjhkmjek3erd4otyjn65mirfpq';
        
        $fields = [
            'app_id' => $appId,
            'include_player_ids' => $playerIds,
            'contents' => ['en' => $message],
            'headings' => ['en' => $title]
        ];

        $fields = json_encode($fields);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://onesignal.com/api/v1/notifications');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json; charset=utf-8',
            'Authorization: Basic ' . $restApiKey,
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
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