<?php

namespace App\Http\Controllers;

use App\Models\Field;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
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
        $field = Field::findOrFail($id);
        return view('laravel-examples.field-edit', compact('field'));
    }

    public function create()
    {
        return view('laravel-examples.field-addCancha');

    }

    public function store(Request $request)
    {
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
            'images.*' => 'image|mimes:jpeg,png,jpg,gif|max:2048'
        ]);
    
        // Convertir "on" a 1 para el campo is_active
        $validated['is_active'] = $request->has('is_active') ? 1 : 0;
    
        // Procesar los horarios disponibles
        $availableHours = [];
        foreach ($request->available_hours as $day => $hours) {
            $availableHours[$day] = [
                $hours['start'],
                $hours['end']
            ];
        }
        $validated['available_hours'] = json_encode($availableHours);
    
        // Procesar las imágenes
        if ($request->hasFile('images')) {
            $images = [];
            foreach ($request->file('images') as $image) {
                $path = $image->store('fields', 'public');
                $images[] = Storage::url($path);
            }
            $validated['images'] = json_encode($images);
        }
    
        // Crear la cancha
        Field::create($validated);
    
        // Redirigir con mensaje de éxito
        return redirect()->route('field-management')->with('success', 'Cancha creada exitosamente');
    }

    
    public function update(Request $request, $id)
    {
        $field = Field::findOrFail($id);
        
        $validated = $request->validate([
            'name' => 'required|string',
            'description' => 'required|string',
            'location' => 'required|string',
            'price_per_match' => 'required|numeric',
            'type' => 'required|in:futbol5,futbol7,futbol11',
            'is_active' => 'boolean'
        ]);

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