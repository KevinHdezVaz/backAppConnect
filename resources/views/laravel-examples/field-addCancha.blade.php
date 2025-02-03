@extends('layouts.user_type.auth')
@section('content')

<style>
.location-loading {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: rgba(255,255,255,0.9);
    padding: 20px;
    border-radius: 5px;
    text-align: center;
    z-index: 1000;
}
</style>

<div class="container-fluid py-4">
    <div class="card">
        <div class="card-header pb-0 px-3">
            <h6 class="mb-0">Nueva Cancha</h6>
        </div>
     
        <div class="card-body pt-4 p-3">
    <!-- Alertas aquí -->
    @if ($errors->any())
        <div class="alert alert-danger text-white" role="alert">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if(session('success'))
        <div class="alert alert-success text-white" role="alert">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger text-white" role="alert">
            {{ session('error') }}
        </div>
    @endif

    <form action="{{ route('field-management.store') }}" method="POST" enctype="multipart/form-data">

                @csrf
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="name">Nombre de la Cancha</label>
                            <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="type">Tipo de Cancha</label>
                            <select name="type" class="form-control @error('type') is-invalid @enderror" required>
                                <option value="futbol5">Fútbol 5</option>
                                <option value="futbol7">Fútbol 7</option>
                                <option value="futbol11">Fútbol 11</option>
                            </select>
                            @error('type')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="description">Descripción</label>
                    <textarea name="description" class="form-control @error('description') is-invalid @enderror" rows="3" required></textarea>
                    @error('description')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="row">
    <!-- Campo Municipio -->
    <div class="col-md-6">
        <div class="form-group">
            <label for="municipio">Municipio</label>
            <input type="text" name="municipio" class="form-control @error('municipio') is-invalid @enderror" 
                   value="{{ old('municipio', $field->municipio ?? '') }}" required>
            @error('municipio')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>

    <!-- Campo Precio por Partido -->
    <div class="col-md-6">
        <div class="form-group">
            <label for="price_per_match">Precio por partido</label>
            <div class="input-group">
                <span class="input-group-text">$</span>
                <input type="number" name="price_per_match" step="0.01" 
                       class="form-control @error('price_per_match') is-invalid @enderror" required>
                @error('price_per_match')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
        </div>
    </div>
</div>


                <div class="row mb-3">
    <div class="col-md-4"> <!-- Reducimos el ancho a la mitad -->
        <div class="form-group">
            <label for="location">Ubicación</label>
            <div>
                <input type="hidden" name="location" id="location" class="@error('location') is-invalid @enderror" required>
                <button type="button" class="btn btn-primary w-100" onclick="showMap()">
                    <i class="fas fa-map-marker-alt me-2"></i> Seleccionar ubicación en el mapa
                </button>
                @if($errors->has('location'))
                    <div class="text-danger mt-2">{{ $errors->first('location') }}</div>
                @endif
                <div id="selected-location" class="text-muted mt-2"></div>
            </div>
        </div>
    </div>
</div>

</div>
<div class="row">
    <div class="col-md-6">
        <div class="form-group">
            <label for="latitude">Latitud</label>
            <input type="number" id="latitude" name="latitude" class="form-control @error('latitude') is-invalid @enderror" step="any" readonly>
            @error('latitude')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>
    <div class="col-md-6">
        <div class="form-group">
            <label for="longitude">Longitud</label>
            <input type="number" id="longitude" name="longitude" class="form-control @error('longitude') is-invalid @enderror" step="any" readonly>
            @error('longitude')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>
</div>

 

<!-- Modal para el mapa -->
<div class="modal fade" id="mapModal" tabindex="-1" aria-labelledby="mapModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="mapModalLabel">Seleccionar Ubicación</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="map" style="height: 400px; position: relative;">
                    <div id="location-loading" class="location-loading d-none">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Cargando...</span>
                        </div>
                        <p class="mt-2 mb-0">Obteniendo tu ubicación...</p>
                    </div>
                </div>
                <div class="mt-3">
                    <input type="text" id="searchLocation" class="form-control" placeholder="Buscar ubicación...">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-primary" onclick="confirmLocation()">Confirmar Ubicación</button>
            </div>
        </div>
    </div>
</div>

                
                <div class="form-group">
                    <label>Amenities</label>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="amenities[]" value="Vestuarios">
                                <label class="form-check-label">Vestuarios</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="amenities[]" value="Estacionamiento">
                                <label class="form-check-label">Estacionamiento</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="amenities[]" value="Iluminación nocturna">
                                <label class="form-check-label">Iluminación nocturna</label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Horarios Disponibles</label>
                    <div class="mb-3">
                        <div class="row">
                            <div class="col-12">
                                <p class="text-sm text-muted mb-2">Configura los horarios para cada día de la semana:</p>
                            </div>
                        </div>

                        <!-- Configuración por día -->
                        <div id="days-config">
                            <!-- Los días se generarán dinámicamente por JavaScript -->
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="images">Imágenes</label>
                    <input type="file" name="images[]" class="form-control @error('images') is-invalid @enderror" multiple accept="image/*">
                    @error('images')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="is_active" checked>
                    <label class="form-check-label">Cancha Activa</label>
                </div>

                <div class="d-flex justify-content-end mt-4">
    <a href="{{ route('field-management') }}" class="btn btn-light m-0">Cancelar</a>
    <button type="submit" class="btn bg-gradient-primary m-0 ms-2">Crear Cancha</button>
</div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const daysConfig = {
        monday: 'Lunes',
        tuesday: 'Martes',
        wednesday: 'Miércoles',
        thursday: 'Jueves',
        friday: 'Viernes',
        saturday: 'Sábado',
        sunday: 'Domingo'
    };

    // Inicializar todos los días
    Object.keys(daysConfig).forEach(day => {
        const container = document.getElementById('days-config');
        container.innerHTML += createDayConfig(day, daysConfig[day]);
    });

    // Event listeners para los checkboxes y campos de tiempo
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

    // Función para crear la configuración de cada día
    function createDayConfig(day, dayName) {
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
                                       data-day="${day}" value="10:00">
                            </div>
                            <div class="col-md-5">
                                <label class="form-label">Hora fin</label>
                                <input type="time" class="form-control time-end" 
                                       data-day="${day}" value="22:00">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    // Función para generar los intervalos de tiempo
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

    // Función para actualizar los horarios disponibles
    
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

    // Actualizar el campo oculto
    let hiddenInput = document.getElementById('available_hours_input');
    if (!hiddenInput) {
        hiddenInput = document.createElement('input');
        hiddenInput.type = 'hidden';
        hiddenInput.name = 'available_hours';
        hiddenInput.id = 'available_hours_input';
        document.querySelector('form').appendChild(hiddenInput);
    }
    hiddenInput.value = JSON.stringify(availableHours); // Convertir a JSON una vez
}

    // Inicializar los horarios al cargar la página
    updateAvailableHours();


    
});
</script>

<script>
let map;
let marker;
let geocoder;

function showMap() {
    const modal = new bootstrap.Modal(document.getElementById('mapModal'));
    modal.show();
    
    setTimeout(() => {
        initMap();
    }, 500);
}

function initMap() {

    document.getElementById('location-loading').classList.remove('d-none');

    // Primero intentamos obtener la ubicación actual
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            (position) => {
                const currentPosition = {
                    lat: position.coords.latitude,
                    lng: position.coords.longitude
                };
                initializeMapWithPosition(currentPosition);
                document.getElementById('location-loading').classList.add('d-none');
            },
            () => {
                // Si falla, usamos una posición por defecto
                const defaultPosition = { lat: -34.6037, lng: -58.3816 }; // Buenos Aires
                initializeMapWithPosition(defaultPosition);
                document.getElementById('location-loading').classList.add('d-none');
            }
        );
    }else {
        // Si no hay geolocalización disponible, usamos posición por defecto
        const defaultPosition = { lat: 19.29371244831551 , lng: -99.19100707319005 };
        initializeMapWithPosition(defaultPosition);
    }
}

function initializeMapWithPosition(position) {
    // Inicializar el mapa
    map = new google.maps.Map(document.getElementById('map'), {
        center: position,
        zoom: 15
    });

    geocoder = new google.maps.Geocoder();

    // Colocar marcador en la posición inicial
    placeMarker(position);

    // Agregar marcador al hacer clic
    map.addListener('click', function(e) {
        placeMarker(e.latLng);
    });

    // Inicializar el buscador
    const searchInput = document.getElementById('searchLocation');
    const searchBox = new google.maps.places.SearchBox(searchInput);

    map.addListener('bounds_changed', function() {
        searchBox.setBounds(map.getBounds());
    });

    searchBox.addListener('places_changed', function() {
        const places = searchBox.getPlaces();

        if (places.length === 0) {
            return;
        }

        const place = places[0];
        map.setCenter(place.geometry.location);
        placeMarker(place.geometry.location);
    });
}

function placeMarker(location) {
    if (marker) {
        marker.setMap(null);
    }

    marker = new google.maps.Marker({
        position: location,
        map: map,
        animation: google.maps.Animation.DROP
    });

    // Obtener dirección
    geocoder.geocode({ location: location }, (results, status) => {
        if (status === 'OK') {
            if (results[0]) {
                document.getElementById('location').value = results[0].formatted_address;
            }
        }
    });

    // Centrar el mapa en el marcador
    map.setCenter(location);
}

function confirmLocation() {
    if (marker) {
        const position = marker.getPosition();
        document.getElementById('latitude').value = position.lat();
        document.getElementById('longitude').value = position.lng();
        
        bootstrap.Modal.getInstance(document.getElementById('mapModal')).hide();
    } else {
        alert('Por favor, selecciona una ubicación en el mapa');
    }
}
</script>

@endsection