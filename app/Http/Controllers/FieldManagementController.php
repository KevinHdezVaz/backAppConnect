<?php

namespace App\Http\Controllers;

use App\Models\Field;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class FieldManagementController extends Controller
{
    public function index()
    {
        $fields = Field::all();
        return view('laravel-examples.field-management', compact('fields'));
    }

   public function edit($id)
{
    try {
        \Log::info('Entrando al método edit con ID: ' . $id);
        
        $field = Field::findOrFail($id);
        \Log::info('Field encontrado:', ['field' => $field->toArray()]);
        
        if (!view()->exists('laravel-examples.field-edit')) {
            \Log::error('La vista field-edit no existe');
            throw new \Exception('La vista no fue encontrada');
        }
        
        return view('laravel-examples.field-edit', compact('field'));
    } catch (\Exception $e) {
        \Log::error('Error en edit method:', [
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        
        return redirect()->route('field-management')
            ->with('error', 'Error al cargar la cancha: ' . $e->getMessage());
    }
}
    public function create()
    {
        return view('laravel-examples.field-addCancha');
    }

    public function store(Request $request)
{
    \Log::debug('Datos recibidos:', $request->all());
    try {
        // Validar los datos de entrada
        $validated = $request->validate([
            'name' => 'required|string',
            'description' => 'required|string',
            'price_per_match' => 'required|numeric',
            'type' => 'required|in:futbol5,futbol7,futbol11',
            'latitude' => 'nullable|numeric',
            'municipio' => 'required|string',
            'longitude' => 'nullable|numeric',
            'is_active' => 'sometimes',
            'amenities' => 'nullable|array',
            'available_hours' => 'required|string',
            'images.*' => 'nullable|file|mimes:jpeg,png,jpg,gif|max:2048'
        ]);

        // Decodificar y validar available_hours
        $availableHours = json_decode($request->available_hours, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Error en el formato de available_hours');
        }

        // Preparar los datos
        $validatedData = [
            'name' => $validated['name'],
            'description' => $validated['description'],
            'price_per_match' => $validated['price_per_match'],      
            'municipio' => $validated['municipio'],
            'duration_per_match' => 60,
            'type' => $validated['type'],
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'is_active' => $request->has('is_active') ? 1 : 0,
            'available_hours' => $availableHours,  
            'amenities' => json_encode($request->input('amenities', []), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        ];

        // Procesar imágenes
        if ($request->hasFile('images')) {
            $imagePaths = [];
            foreach ($request->file('images') as $image) {
                $path = $image->store('fields', 'public');
                $imagePaths[] = Storage::url($path);
            }
            $validatedData['images'] = json_encode($imagePaths, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        // Crear la cancha
        $field = Field::create($validatedData);

        return redirect()->route('field-management')->with('success', 'Cancha creada exitosamente');
    } catch (\Exception $e) {
        \Log::error('Error en store:', [
            'mensaje' => $e->getMessage(),
            'línea' => $e->getLine(),
            'archivo' => $e->getFile(),
            'trace' => $e->getTraceAsString()
        ]);
        return redirect()->back()->withInput()->withErrors(['error' => 'Error al crear la cancha: ' . $e->getMessage()]);
    }
}


public function update(Request $request, $id)
{
    try {
        \Log::info('Actualizando cancha', $request->all());
        $field = Field::findOrFail($id);

        // Validación
        $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:futbol5,futbol7,futbol11',
            'description' => 'required|string',
            'municipio' => 'required|string',
            'price_per_match' => 'required|numeric',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'images.*' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'amenities' => 'nullable|array',
            'available_hours' => 'required|string', // Cambiado de 'json' a 'string'
        ]);

        // Decodificar y validar available_hours
        $availableHours = json_decode($request->available_hours, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Error en el formato de available_hours');
        }

       // Manejar imágenes
       $newImageArray = $request->existing_images ?? [];
        
       if ($request->hasFile('images')) {
           foreach ($request->file('images') as $image) {
               $path = $image->store('fields', 'public');
               $newImageArray[] = Storage::url($path);
           }
       }

 
        $field->images = json_encode($newImageArray);

        // Actualizar los datos básicos
        $field->name = $request->name;
        $field->type = $request->type;
        $field->municipio = $request->municipio;
        $field->description = $request->description;
        $field->price_per_match = $request->price_per_match;
        $field->latitude = $request->latitude;
        $field->longitude = $request->longitude;
        $field->amenities = json_encode($request->amenities ?? []);
        $field->available_hours = json_encode($availableHours); // Guardar como JSON
        $field->is_active = $request->has('is_active');

        $field->save();

        return redirect()->route('field-management')
            ->with('success', 'Cancha actualizada exitosamente');
    } catch (\Exception $e) {
        \Log::error('Error actualizando cancha: ' . $e->getMessage());
        \Log::error('Available hours recibido: ' . $request->available_hours);
        return redirect()->back()
            ->withInput()
            ->with('error', 'Error al actualizar la cancha: ' . $e->getMessage());
    }
}

    public function destroy($id)
    {
        $field = Field::findOrFail($id);
        $field->delete();
        return redirect()->route('field-management')->with('success', 'Cancha eliminada exitosamente');
    }
}
