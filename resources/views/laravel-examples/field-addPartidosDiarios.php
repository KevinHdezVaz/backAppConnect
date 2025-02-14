@extends('layouts.user_type.auth')
@section('content')
<div class="container-fluid py-4">
    <div class="card">
        <div class="card-header pb-0 px-3">
            <h6 class="mb-0">Nuevo Partido Diario</h6>
        </div>
        
        <div class="card-body pt-4 p-3">
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

            <form action="{{ route('daily-matches.store') }}" method="POST">
                @csrf
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="name">Nombre del Partido</label>
                            <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" 
                                   value="{{ old('name') }}" required 
                                   placeholder="ej: Partido Matutino">
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="field_id">Cancha</label>
                            <select name="field_id" class="form-control @error('field_id') is-invalid @enderror" required>
                                <option value="">Seleccionar cancha...</option>
                                @foreach($fields as $field)
                                    <option value="{{ $field->id }}">{{ $field->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="max_players">Máximo de Jugadores por Equipo</label>
                            <input type="number" name="max_players" 
                                   class="form-control @error('max_players') is-invalid @enderror"
                                   value="{{ old('max_players', 7) }}" min="5" max="11" required>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="price">Precio por Jugador</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" name="price" step="0.01" 
                                       class="form-control @error('price') is-invalid @enderror"
                                       value="{{ old('price') }}" required>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-group mt-4">
                    <label>Días y Horarios</label>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Día</th>
                                    <th>Activo</th>
                                    <th>Hora Inicio</th>
                                    <th>Hora Fin</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach(['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'] as $day)
                                    <tr>
                                        <td>{{ $day }}</td>
                                        <td>
                                            <div class="form-check form-switch">
                                                <input class="form-check-input day-toggle" type="checkbox" 
                                                       name="days[{{ strtolower($day) }}][active]"
                                                       data-day="{{ strtolower($day) }}">
                                            </div>
                                        </td>
                                        <td>
                                            <input type="time" class="form-control time-input" 
                                                   name="days[{{ strtolower($day) }}][start_time]"
                                                   data-day="{{ strtolower($day) }}" disabled>
                                        </td>
                                        <td>
                                            <input type="time" class="form-control time-input" 
                                                   name="days[{{ strtolower($day) }}][end_time]"
                                                   data-day="{{ strtolower($day) }}" disabled>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="row mt-4">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="start_date">Fecha de Inicio</label>
                            <input type="date" name="start_date" 
                                   class="form-control @error('start_date') is-invalid @enderror"
                                   value="{{ old('start_date', now()->format('Y-m-d')) }}" required>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="end_date">Fecha de Fin (opcional)</label>
                            <input type="date" name="end_date" 
                                   class="form-control @error('end_date') is-invalid @enderror"
                                   value="{{ old('end_date') }}">
                        </div>
                    </div>
                </div>

                <div class="form-check form-switch mt-4">
                    <input class="form-check-input" type="checkbox" name="is_active" checked>
                    <label class="form-check-label">Partido Activo</label>
                </div>

                <div class="d-flex justify-content-end mt-4">
                    <a href="{{ route('daily-matches.index') }}" class="btn btn-light m-0">Cancelar</a>
                    <button type="submit" class="btn bg-gradient-primary m-0 ms-2">Crear Partido</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Manejar la activación/desactivación de los horarios por día
    document.querySelectorAll('.day-toggle').forEach(toggle => {
        toggle.addEventListener('change', function() {
            const day = this.dataset.day;
            const timeInputs = document.querySelectorAll(`.time-input[data-day="${day}"]`);
            
            timeInputs.forEach(input => {
                input.disabled = !this.checked;
                if (this.checked) {
                    input.required = true;
                    if (!input.value) {
                        // Establecer valores por defecto
                        if (input.name.includes('start_time')) {
                            input.value = '08:00';
                        } else {
                            input.value = '22:00';
                        }
                    }
                } else {
                    input.required = false;
                }
            });
        });
    });
});
</script>
@endsection