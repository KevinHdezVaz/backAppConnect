@extends('layouts.user_type.auth')
@section('content')
@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<div class="container-fluid py-4">
    <div class="card">
        <div class="card-header pb-0 px-3">
            <h6 class="mb-0">Editar Cancha</h6>
        </div>
        <div class="card-body pt-4 p-3">
            <form action="{{ route('field-management.update', $field->id) }}" method="POST" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="name">Nombre de la Cancha</label>
                            <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" 
                                   value="{{ old('name', $field->name) }}" required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="type">Tipo de Cancha</label>
                            <select name="type" class="form-control @error('type') is-invalid @enderror" required>
                                <option value="futbol5" {{ $field->type == 'futbol5' ? 'selected' : '' }}>Fútbol 5</option>
                                <option value="futbol7" {{ $field->type == 'futbol7' ? 'selected' : '' }}>Fútbol 7</option>
                                <option value="futbol11" {{ $field->type == 'futbol11' ? 'selected' : '' }}>Fútbol 11</option>
                            </select>
                            @error('type')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="description">Descripción</label>
                    <textarea name="description" class="form-control @error('description') is-invalid @enderror" 
                              rows="3" required>{{ old('description', $field->description) }}</textarea>
                    @error('description')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="municipio">Municipio</label>
                            <input type="text" name="municipio" class="form-control @error('municipio') is-invalid @enderror" 
                                   value="{{ old('municipio', $field->municipio) }}" required>
                            @error('municipio')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="price_per_match">Precio por Partido</label>
                            <input type="number" name="price_per_match" class="form-control @error('price_per_match') is-invalid @enderror" 
                                   value="{{ old('price_per_match', $field->price_per_match) }}" step="0.01" required>
                            @error('price_per_match')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="latitude">Latitud</label>
                            <input type="number" name="latitude" class="form-control @error('latitude') is-invalid @enderror" 
                                   value="{{ old('latitude', $field->latitude) }}" step="any">
                            @error('latitude')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="longitude">Longitud</label>
                            <input type="number" name="longitude" class="form-control @error('longitude') is-invalid @enderror" 
                                   value="{{ old('longitude', $field->longitude) }}" step="any">
                            @error('longitude')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Amenities</label>
                    @php
                        $amenities = json_decode($field->amenities ?? '[]');
                    @endphp
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="amenities[]" value="Vestuarios"
                                       {{ in_array('Vestuarios', $amenities ?? []) ? 'checked' : '' }}>
                                <label class="form-check-label">Vestuarios</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="amenities[]" value="Estacionamiento"
                                       {{ in_array('Estacionamiento', $amenities ?? []) ? 'checked' : '' }}>
                                <label class="form-check-label">Estacionamiento</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="amenities[]" value="Iluminación nocturna"
                                       {{ in_array('Iluminación nocturna', $amenities ?? []) ? 'checked' : '' }}>
                                <label class="form-check-label">Iluminación nocturna</label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Horarios Disponibles</label>
                    <div class="mb-3">
                        <div id="days-config">
                            <!-- Los días se generarán dinámicamente por JavaScript -->
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="images">Imágenes Actuales</label>
                    <div class="row mb-3">
                        @if($field->images)
                            @foreach(json_decode($field->images) as $index => $image)
                                <div class="col-md-3 mb-2" id="image-container-{{ $index }}">
                                    <div class="position-relative">
                                        <img src="{{ $image }}" class="img-thumbnail" style="height: 150px; width: 100%; object-fit: cover;">
                                        <button type="button" 
                                                class="btn btn-danger btn-sm position-absolute"
                                                style="top: 5px; right: 5px; padding: 3px 8px; z-index: 10;"
                                                onclick="removeImage({{ $index }})">
                                            <i class="fas fa-times"></i>
                                        </button>
                                        <input type="hidden" name="existing_images[]" value="{{ $image }}">
                                    </div>
                                </div>
                            @endforeach
                        @endif
                    </div>
                    
                    <label for="images">Agregar Nuevas Imágenes</label>
                    <input type="file" name="images[]" class="form-control @error('images') is-invalid @enderror" multiple accept="image/*">
                    <div id="image-preview-container" class="row mt-3"></div>
                    @error('images')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="is_active" {{ $field->is_active ? 'checked' : '' }}>
                    <label class="form-check-label">Cancha Activa</label>
                </div>

                <div class="d-flex justify-content-end mt-4">
                    <a href="{{ route('field-management') }}" class="btn btn-light m-0">Cancelar</a>
                    <button type="submit" class="btn bg-gradient-primary m-0 ms-2">Actualizar Cancha</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Funciones para manejo de imágenes
function removeImage(index) {
    const container = document.getElementById(`image-container-${index}`);
    if (container) {
        container.remove();
        updateExistingImages();
    }
}

function updateExistingImages() {
    const currentImages = Array.from(document.querySelectorAll('input[name="existing_images[]"]'))
        .map(input => input.value);
    
    let removedImages = document.getElementById('removed_images');
    if (!removedImages) {
        removedImages = document.createElement('input');
        removedImages.type = 'hidden';
        removedImages.name = 'removed_images';
        removedImages.id = 'removed_images';
        document.querySelector('form').appendChild(removedImages);
    }
    removedImages.value = JSON.stringify(currentImages);
}

function previewImages(input) {
    const previewContainer = document.getElementById('image-preview-container');
    if (!previewContainer) {
        const container = document.createElement('div');
        container.id = 'image-preview-container';
        container.className = 'row mt-3';
        input.parentElement.appendChild(container);
    }

    const files = input.files;
    const container = document.getElementById('image-preview-container');
    container.innerHTML = ''; // Limpiar previsualizaciones anteriores

    for (let i = 0; i < files.length; i++) {
        const file = files[i];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const previewDiv = document.createElement('div');
                previewDiv.className = 'col-md-3 mb-2';
                previewDiv.innerHTML = `
                    <div class="position-relative">
                        <img src="${e.target.result}" class="img-thumbnail" style="height: 150px; width: 100%; object-fit: cover;">
                        <button type="button" 
                                class="btn btn-danger btn-sm position-absolute"
                                style="top: 5px; right: 5px; padding: 3px 8px;"
                                onclick="removePreview(this)">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                `;
                container.appendChild(previewDiv);
            };
            reader.readAsDataURL(file);
        }
    }
}

function removePreview(button) {
    button.closest('.col-md-3').remove();
}

// Código principal
document.addEventListener('DOMContentLoaded', function () {
    // Event listener para vista previa de imágenes
    document.querySelector('input[name="images[]"]').addEventListener('change', function() {
        previewImages(this);
    });

    const daysConfig = {
        monday: 'Lunes',
        tuesday: 'Martes',
        wednesday: 'Miércoles',
        thursday: 'Jueves',
        friday: 'Viernes',
        saturday: 'Sábado',
        sunday: 'Domingo'
    };

    const existingHours = @json($field->available_hours);

    Object.keys(daysConfig).forEach(day => {
        const container = document.getElementById('days-config');
        container.innerHTML += createDayConfig(day, daysConfig[day], existingHours[day] || []);
    });

    Object.keys(existingHours).forEach(day => {
        const checkbox = document.querySelector(`#enable_${day}`);
        if (checkbox && existingHours[day].length > 0) {
            checkbox.checked = true;
            document.getElementById(`${day}_times`).style.display = 'flex';
        }
    });

    document.querySelectorAll('.day-enable').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const day = this.dataset.day;
            const timeInputs = document.getElementById(`${day}_times`);
            timeInputs.style.display = this.checked ? 'flex' : 'none';
            updateAvailableHours();
        });
    });

    document.querySelectorAll('.time-start, .time-end').forEach(input => {
        input.addEventListener('change', updateAvailableHours);
    });

    function createDayConfig(day, dayName, hours = []) {
        const startTime = hours[0] || '10:00';
        const endTime = hours[hours.length - 1] || '22:00';

        return `
            <div class="day-config border rounded p-3 mb-3">
                <div class="row align-items-center">
                    <div class="col-md-3">
                        <div class="form-check">
                            <input class="form-check-input day-enable" type="checkbox" 
                                   id="enable_${day}" data-day="${day}">
                            <label class="form-check-label" for="enable_${day}">
                                <strong>${dayName}</strong>
                            </label>
                        </div>
                    </div>
                    <div class="col-md-9">
                        <div class="row time-inputs" id="${day}_times" style="display: none;">
                            <div class="col-md-5">
                                <label class="form-label">Hora inicio</label>
                                <input type="time" class="form-control time-start" 
                                       data-day="${day}" value="${startTime}">
                            </div>
                            <div class="col-md-5">
                                <label class="form-label">Hora fin</label>
                                <input type="time" class="form-control time-end" 
                                       data-day="${day}" value="${endTime}">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    function generateTimeSlots(start, end) {
        const slots = [];
        let current = new Date(`2000-01-01T${start}`);
        const endTime = new Date(`2000-01-01T${end}`);
        
        while (current <= endTime) {
            slots.push(current.toTimeString().slice(0, 5));
            current.setHours(current.getHours() + 1);
        }
        
        return slots;
    }

    function updateAvailableHours() {
        const availableHours = {};
        
        document.querySelectorAll('.day-enable:checked').forEach(checkbox => {
            const day = checkbox.dataset.day;
            const startTime = document.querySelector(`.time-start[data-day="${day}"]`).value;
            const endTime = document.querySelector(`.time-end[data-day="${day}"]`).value;
            
            if (startTime && endTime) {
                availableHours[day] = generateTimeSlots(startTime, endTime);
            }
        });

        let hiddenInput = document.getElementById('available_hours_input');
        if (!hiddenInput) {
            hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = 'available_hours';
            hiddenInput.id = 'available_hours_input';
            document.querySelector('form').appendChild(hiddenInput);
        }
        hiddenInput.value = JSON.stringify(availableHours);
    }

    // Inicializar los horarios al cargar la página
    updateAvailableHours();
});
</script>
@endsection