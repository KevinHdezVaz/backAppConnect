@extends('layouts.user_type.auth')
@section('content')
<div class="container-fluid py-4">
    <div class="card">
        <div class="card-header pb-0 px-3">
            <h6 class="mb-0">Nueva Cancha</h6>
        </div>
        <div class="card-body pt-4 p-3">
            <form action="{{ route('field.store') }}" method="POST" enctype="multipart/form-data">
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
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="location">Ubicación</label>
                            <input type="text" name="location" class="form-control @error('location') is-invalid @enderror" required>
                            @error('location')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="price_per_match">Precio por Partido</label>
                            <input type="number" name="price_per_match" class="form-control @error('price_per_match') is-invalid @enderror" step="0.01" required>
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
                            <input type="number" name="latitude" class="form-control @error('latitude') is-invalid @enderror" step="any">
                            @error('latitude')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="longitude">Longitud</label>
                            <input type="number" name="longitude" class="form-control @error('longitude') is-invalid @enderror" step="any">
                            @error('longitude')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
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
                    @foreach(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'] as $day)
                        <div class="mb-3">
                            <label class="text-capitalize">{{ $day }}</label>
                            <div class="row">
                                <div class="col-md-6">
                                    <label>Hora inicio</label>
                                    <input type="time" name="available_hours[{{ $day }}][start]" class="form-control @error('available_hours.' . $day . '.start') is-invalid @enderror">
                                    @error('available_hours.' . $day . '.start')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-6">
                                    <label>Hora fin</label>
                                    <input type="time" name="available_hours[{{ $day }}][end]" class="form-control @error('available_hours.' . $day . '.end') is-invalid @enderror">
                                    @error('available_hours.' . $day . '.end')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    @endforeach
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
                    <button type="button" class="btn btn-light m-0">Cancelar</button>
                    <button type="submit" class="btn bg-gradient-primary m-0 ms-2">Crear Cancha</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('js')
<script>
    // Aquí puedes agregar JavaScript para manejar la vista previa de imágenes
    // y cualquier otra funcionalidad interactiva que necesites
</script>
@endpush