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
            'location' => 'required|string',
            'price_per_match' => 'required|numeric',
            'type' => 'required|in:futbol5,futbol7,futbol11',
            'latitude' => 'nullable|numeric',
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
            'location' => $validated['location'],
            'price_per_match' => $validated['price_per_match'],
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
        $field = Field::findOrFail($id);

        // Validar los datos
        $validated = $request->validate([
            'name' => 'required|string',
            'description' => 'required|string',
            'location' => 'required|string',
            'price_per_match' => 'required|numeric',
            'type' => 'required|in:futbol5,futbol7,futbol11',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'is_active' => 'boolean',
            'amenities' => 'nullable|array',
            'available_hours' => 'required|array',
            'available_hours.*.start' => 'nullable|string',
            'available_hours.*.end' => 'nullable|string',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048'
        ]);

        // Procesar available_hours
        $availableHours = [];
        foreach ($request->available_hours as $day => $hours) {
            $filteredHours = array_filter([$hours['start'], $hours['end']], function ($hour) {
                return !is_null($hour);
            });

            if (!empty($filteredHours)) {
                $availableHours[$day] = $filteredHours;
            }
        }
        $validated['available_hours'] = json_encode($availableHours, JSON_UNESCAPED_UNICODE);

        // Procesar amenities
        $validated['amenities'] = $request->amenities ?? [];

        // Procesar imágenes
        if ($request->hasFile('images')) {
            $images = [];
            foreach ($request->file('images') as $image) {
                $path = $image->store('fields', 'public');
                $images[] = Storage::url($path);
            }
            $validated['images'] = $images;
        }

        // Actualizar
        $field->update($validated);

        return redirect()->route('field-management')->with('success', 'Cancha actualizada exitosamente');
    }

    public function destroy($id)
    {
        $field = Field::findOrFail($id);
        $field->delete();
        return redirect()->route('field-management')->with('success', 'Cancha eliminada exitosamente');
    }
}
