<?php
namespace App\Http\Controllers;
use App\Models\Stories;  // Mantenemos Stories
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class StoryController extends Controller
{
    // Para la vista web del panel administrativo
    public function index()
    {
        $stories = Stories::with('administrator')
            ->orderBy('created_at', 'desc')
            ->paginate(10);
            
        return view('laravel-examples.field-liststory', compact('stories'));
    }

  
    public function create()
    {
        return view('laravel-examples.field-addstory');
    }
 
    public function update(Request $request, Stories $story)  // Cambiado a Stories
    {
        if (auth()->guard('admin')->id() !== $story->administrator_id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized'
            ], 403);
        }
        
        $request->validate([
            'title' => 'string|max:255',
            'is_active' => 'boolean'
        ]);
        
        $story->update($request->only(['title', 'is_active']));
        
        return response()->json([
            'status' => 'success',
            'data' => $story
        ]);
    }
    public function store(Request $request)
    {
        try {
            $request->validate([
                'title' => 'required|string|max:255',
                'image' => 'required|image|mimes:jpeg,png,jpg|max:2048',
                'video' => 'nullable|mimetypes:video/mp4|max:10240'
            ]);
    
            // Asegurarse de que el directorio existe
            Storage::makeDirectory('public/stories');
            
            // Guardar imagen con nombre único
            $imageName = time() . '_' . $request->file('image')->getClientOriginalName();
            $imagePath = $request->file('image')->storeAs('stories', $imageName, 'public');
            
            $videoPath = null;
            if ($request->hasFile('video')) {
                $videoName = time() . '_' . $request->file('video')->getClientOriginalName();
                $videoPath = $request->file('video')->storeAs('stories/videos', $videoName, 'public');
            }
    
            $story = Stories::create([
                'title' => $request->title,
                'image_url' => $imagePath,
                'video_url' => $videoPath ?  $videoPath : null,
                'administrator_id' => auth()->id(),
                'expires_at' => now()->addHours(24),
                'is_active' => true
            ]);
    
            return redirect()->route('admin.stories.index')
                ->with('success', 'Historia creada exitosamente');
    
        } catch (\Exception $e) {
            \Log::error('Error creating story: ' . $e->getMessage());
            return back()
                ->withErrors(['error' => 'Error al crear la historia: ' . $e->getMessage()])
                ->withInput();
        }
    }

public function getStoriesApi()
{
    try {
        $stories = Stories::with('administrator')
            ->where('is_active', true)
            ->where('expires_at', '>', now())
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function($story) {
                $story->image_url = asset($story->image_url);
                if ($story->video_url) {
                    $story->video_url = asset($story->video_url);
                }
                return $story;
            });

        return response()->json([
            'status' => 'success',
            'data' => $stories
        ]);
    } catch (\Exception $e) {
        \Log::error('Error fetching stories: ' . $e->getMessage());
        return response()->json([
            'status' => 'error',
            'message' => 'Error al cargar las historias'
        ], 500);
    }
}
}