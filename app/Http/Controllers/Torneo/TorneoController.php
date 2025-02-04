<?php

namespace App\Http\Controllers\Torneo;

use App\Models\Torneo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Controller;

class TorneoController extends Controller
{
    public function index()
    {
        $torneos = Torneo::all();
        return view('laravel-examples.Torneos.field-listTorneo', compact('torneos'));
    }

    public function create()
    {
        return view('laravel-examples.Torneos.field-addTorneo');
    }


    
    public function store(Request $request)
{
    try {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:255',
            'formato' => 'required|in:liga,eliminacion,grupos_eliminacion',
            'descripcion' => 'required|string|max:1000',
            'fecha_inicio' => 'required|date|after_or_equal:today',
            'fecha_fin' => 'required|date|after:fecha_inicio',
            'minimo_equipos' => 'required|integer|min:2',
            'maximo_equipos' => 'required|integer|min:2|gte:minimo_equipos',
            'cuota_inscripcion' => 'required|numeric|min:0',
            'premio' => 'nullable|string|max:255',
            'reglas' => 'nullable|array',
            'reglas.*' => 'nullable|string|max:500',
            'imagenes.*' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'estado' => 'nullable|in:abierto',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        // Procesar las imágenes
        $imagePaths = [];
        if ($request->hasFile('imagenes')) {
            foreach ($request->file('imagenes') as $imagen) {
                try {
                    // Guardar la imagen de manera similar a AuthController
                    $path = $imagen->store('torneos', 'public');
                    $imagePaths[] = $path;
                    \Log::info('Imagen subida:', ['path' => $path]);
                } catch (\Exception $e) {
                    \Log::error('Error al subir imagen:', ['error' => $e->getMessage()]);
                    return redirect()->back()->with('error', 'Error al subir la imagen.');
                }
            }
        }

        // Crear el torneo con las imágenes
        $torneoData = $request->except('imagenes');
        if (!empty($imagePaths)) {
            $torneoData['imagenesTorneo'] = json_encode($imagePaths);
        }

        $torneo = Torneo::create($torneoData);

        return redirect()->route('torneos.index')->with('success', 'Torneo creado exitosamente.');
    } catch (\Exception $e) {
        \Log::error('Error al crear torneo: ' . $e->getMessage());
        return redirect()->back()
            ->with('error', 'Error al crear el torneo: ' . $e->getMessage())
            ->withInput();
    }
}

public function update(Request $request, $id)
{
    try {
        $torneo = Torneo::findOrFail($id);
        
        $validator = Validator::make($request->all(), [
            'nombre' => 'sometimes|string|max:255',
            'formato' => 'sometimes|in:liga,eliminacion,grupos_eliminacion',
            'descripcion' => 'sometimes|string|max:1000',
            'fecha_inicio' => 'sometimes|date',
            'fecha_fin' => 'sometimes|date|after:fecha_inicio',
            'minimo_equipos' => 'sometimes|integer|min:2',
            'maximo_equipos' => 'sometimes|integer|min:2|gte:minimo_equipos',
            'cuota_inscripcion' => 'sometimes|numeric|min:0',
            'premio' => 'nullable|string|max:255',
            'reglas' => 'nullable|array',
            'reglas.*' => 'nullable|string|max:500',
            'imagenes.*' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'estado' => 'nullable|in:proximamente,abierto,en_progreso,completado,cancelado',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        // Mantener las imágenes existentes
        $imagePaths = $torneo->imagenesTorneo ? json_decode($torneo->imagenesTorneo, true) : [];

        // Procesar nuevas imágenes
        if ($request->hasFile('imagenes')) {
            foreach ($request->file('imagenes') as $imagen) {
                try {
                    $path = $imagen->store('torneos', 'public');
                    $imagePaths[] = $path;
                } catch (\Exception $e) {
                    \Log::error('Error al subir imagen:', ['error' => $e->getMessage()]);
                    return redirect()->back()->with('error', 'Error al subir la imagen.');
                }
            }
        }

        // Actualizar el torneo
        $torneoData = $request->except('imagenes');
        if (!empty($imagePaths)) {
            $torneoData['imagenesTorneo'] = json_encode($imagePaths);
        }

        $torneo->update($torneoData);

        return redirect()->route('torneos.index')->with('success', 'Torneo actualizado exitosamente.');
    } catch (\Exception $e) {
        \Log::error('Error al actualizar torneo: ' . $e->getMessage());
        return redirect()->back()
            ->with('error', 'Error al actualizar el torneo: ' . $e->getMessage())
            ->withInput();
    }
}




public function edit($id)
{
    $torneo = Torneo::findOrFail($id);
    return view('laravel-examples.Torneos.field-editTorneo', compact('torneo'));
}




    public function destroy($id)
    {
        try {
            $torneo = Torneo::findOrFail($id);

            // Eliminar imágenes asociadas
            if (!empty($torneo->imagenesTorneo)) {
                $imagePaths = json_decode($torneo->imagenesTorneo, true);
                foreach ($imagePaths as $imagePath) {
                    // Convertir URL a path relativo
                    $path = str_replace('/storage/', 'public/', $imagePath);
                    if (Storage::exists($path)) {
                        Storage::delete($path);
                    }
                }
            }

            $torneo->delete();

            return redirect()->route('torneos.index')->with('success', 'Torneo eliminado exitosamente.');

        } catch (\Exception $e) {
            \Log::error('Error al eliminar torneo: ' . $e->getMessage());
            return redirect()->back()
                ->with('error', 'Error al eliminar el torneo: ' . $e->getMessage());
        }
    }
}