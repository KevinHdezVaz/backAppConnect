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
                'types' => 'required|array|min:1', // Validar que al menos se seleccione un tipo
                'types.*' => 'in:fut5,fut7,fut11', // Validar que los tipos sean válidos
                'latitude' => 'nullable|numeric',
                'municipio' => 'required|string',
                'longitude' => 'nullable|numeric',
                'is_active' => 'sometimes',
                'amenities' => 'nullable|array',
                'available_hours' => 'required|string',
                'images.*' => 'nullable|file|mimes:jpeg,png,jpg,gif|max:2048'
            ]);
            
            // Verificar si available_hours es una cadena JSON válida
            $availableHours = is_string($request->available_hours) ? json_decode($request->available_hours, true) : $request->available_hours;
            if (json_last_error() !== JSON_ERROR_NONE && is_string($request->available_hours)) {
                throw new \Exception('Error en el formato de available_hours: ' . json_last_error_msg());
            }
            
            // Preparar los datos
            $validatedData = [
                'name' => $validated['name'],
                'description' => $validated['description'],
                'price_per_match' => $validated['price_per_match'],
                'municipio' => $validated['municipio'],
                'latitude' => $validated['latitude'] ?? null,
                'longitude' => $validated['longitude'] ?? null,
                'is_active' => $request->has('is_active') ? 1 : 0,
                'types' => json_encode($validated['types'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), // Codificar types como JSON
                'available_hours' => is_array($availableHours) ? json_encode($availableHours, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : $availableHours,
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

        $request->validate([
            'name' => 'required|string|max:255',
            'types' => 'required|array', // Validar que types sea un arreglo
            'types.*' => 'in:fut5,fut7,fut11', // Validar que cada tipo sea válido
            'description' => 'required|string',
            'municipio' => 'required|string|max:255',
            'price_per_match' => 'required|numeric|min:0',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'amenities' => 'nullable|array',
            'amenities.*' => 'in:Vestuarios,Estacionamiento,Iluminación nocturna',
            'available_hours' => 'nullable|json',
            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048',
            'is_active' => 'boolean',
        ]);

        // Preparar los datos para la actualización
        $data = $request->only([
            'name', 'description', 'municipio', 'price_per_match', 'latitude', 
            'longitude', 'is_active'
        ]);

        // Manejar los tipos (types) como un arreglo JSON
        if ($request->has('types')) {
            $data['types'] = json_encode($request->input('types'));
        }

        // Manejar amenities como un arreglo JSON
        if ($request->has('amenities')) {
            $data['amenities'] = json_encode($request->input('amenities'));
        }

        // Manejar available_hours como JSON (ya viene en el request como JSON)
        if ($request->has('available_hours')) {
            $data['available_hours'] = $request->input('available_hours');
        }

        // Manejar imágenes existentes y nuevas
        if ($request->has('existing_images')) {
            $existingImages = $request->input('existing_images');
            $data['images'] = json_encode($existingImages);
        }

        if ($request->hasFile('images')) {
            $newImages = [];
            foreach ($request->file('images') as $image) {
                $path = $image->store('fields', 'public');
                $newImages[] = "/storage/$path";
            }
            if (isset($data['images'])) {
                $currentImages = json_decode($data['images'], true) ?? [];
                $data['images'] = json_encode(array_merge($currentImages, $newImages));
            } else {
                $data['images'] = json_encode($newImages);
            }
        }

        // Manejar imágenes eliminadas
        if ($request->has('removed_images')) {
            $removedImages = json_decode($request->input('removed_images'), true) ?? [];
            $currentImages = json_decode($field->images ?? '[]', true) ?? [];
            $remainingImages = array_diff($currentImages, $removedImages);
            $data['images'] = json_encode(array_values($remainingImages));
        }

        $field->update($data);

        return redirect()->route('field-management')->with('success', 'Cancha actualizada exitosamente');
    }

    public function destroy($id)
    {
        $field = Field::findOrFail($id);
        $field->delete();
        return redirect()->route('field-management')->with('success', 'Cancha eliminada exitosamente');
    }
}