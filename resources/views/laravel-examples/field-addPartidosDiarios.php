@extends('layouts.user_type.auth')
@section('content')

<div class="container-fluid py-4">
    <div class="card">
        <div class="card-header pb-0 px-3">
            <h6 class="mb-0">Nuevo Partido Diario</h6>
        </div>
        
        <div class="card-body pt-4 p-3">
            {{-- Mensajes de error, éxito y advertencia --}}
            @if ($errors->any())
                <div class="alert alert-danger text-white" role="alert">
                    <ul class="mb-0">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if(session('error'))
                <div class="alert alert-danger text-white" role="alert">
                    {{ session('error') }}
                </div>
            @endif

            @if(session('warning'))
                <div class="alert alert-warning text-white" role="alert">
                    {{ session('warning') }}
                </div>
            @endif

            @if(session('success'))
                <div class="alert alert-success text-white" role="alert">
                    {{ session('success') }}
                </div>
            @endif

            <form action="{{ route('daily-matches.store') }}" method="POST">
                @csrf
                
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="week_selection">Seleccionar Semana</label>
                            <select name="week_selection" id="week_selection" class="form-control @error('week_selection') is-invalid @enderror" required>
                                <option value="current">Esta semana ({{ now()->startOfWeek()->format('d/m/Y') }} - {{ now()->endOfWeek()->format('d/m/Y') }})</option>
                                <option value="next">Próxima semana ({{ now()->addWeek()->startOfWeek()->format('d/m/Y') }} - {{ now()->addWeek()->endOfWeek()->format('d/m/Y') }})</option>
                            </select>
                            @error('week_selection')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="name">Nombre del Partido</label>
                            <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" 
                                   value="{{ old('name') }}" required 
                                   placeholder="ej: Partido Matutino">
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
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
                            @error('field_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="row mt-4">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="game_type">Tipo de Partido</label>
                            <select name="game_type" id="game_type" class="form-control @error('game_type') is-invalid @enderror" required>
                                <option value="">Seleccionar tipo...</option>
                                <option value="fut5" {{ old('game_type') === 'fut5' ? 'selected' : '' }}>Fútbol 5</option>
                                <option value="fut7" {{ old('game_type') === 'fut7' ? 'selected' : '' }}>Fútbol 7</option>
                                <option value="fut11" {{ old('game_type') === 'fut11' ? 'selected' : '' }}>Fútbol 11</option>
                            </select>
                            @error('game_type')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
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
                            @error('price')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>

                <div class="form-group mt-4">
                    <label>Días y Horarios Disponibles</label>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th style="width: 15%">Día</th>
                                    <th style="width: 15%">Activo</th>
                                    <th>Horarios Disponibles</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $days = [
                                        'lunes' => 'Lunes',
                                        'martes' => 'Martes',
                                        'miercoles' => 'Miércoles',
                                        'jueves' => 'Jueves',
                                        'viernes' => 'Viernes',
                                        'sabado' => 'Sábado',
                                        'domingo' => 'Domingo'
                                    ];

                                    $hours = [];
                                    for($i = 10; $i <= 19; $i++) {
                                        $hours[] = str_pad($i, 2, '0', STR_PAD_LEFT) . ':00';
                                    }
                                @endphp

                                @foreach($days as $dayKey => $dayName)
                                    <tr class="day-row">
                                        <td>{{ $dayName }}</td>
                                        <td>
                                            <div class="form-check form-switch">
                                                <input class="form-check-input day-toggle" 
                                                       type="checkbox" 
                                                       data-day="{{ $dayKey }}">
                                            </div>
                                        </td>
                                        <td>
                                            <div class="hours-container" id="hours-{{ $dayKey }}" style="display: none">
                                                @foreach($hours as $hour)
                                                    <div class="form-check form-check-inline">
                                                        <input class="form-check-input hour-checkbox" 
                                                               type="checkbox" 
                                                               name="days[{{ $dayKey }}][hours][]" 
                                                               value="{{ $hour }}"
                                                               disabled
                                                               id="{{ $dayKey }}-{{ $hour }}">
                                                        <label class="form-check-label" for="{{ $dayKey }}-{{ $hour }}">
                                                            {{ $hour }}
                                                        </label>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
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
    const weekSelector = document.getElementById('week_selection');
    const dayToggles = document.querySelectorAll('.day-toggle');

    function updateDaysAvailability() {
        const isNextWeek = weekSelector.value === 'next';
        const today = new Date();
        
        dayToggles.forEach(toggle => {
            const dayName = toggle.dataset.day;
            let dayDate = new Date();
            
            // Ajustar al inicio de la semana
            dayDate.setDate(dayDate.getDate() - dayDate.getDay()); // Ir al domingo
            
            const dayMap = {
                'domingo': 0,
                'lunes': 1,
                'martes': 2,
                'miercoles': 3,
                'jueves': 4,
                'viernes': 5,
                'sabado': 6
            };
            
            // Avanzar al día correspondiente
            dayDate.setDate(dayDate.getDate() + dayMap[dayName]);
            
            if (isNextWeek) {
                dayDate.setDate(dayDate.getDate() + 7);
                toggle.disabled = false;
            } else {
                // Para la semana actual, solo deshabilitar días anteriores
                const todayWithoutTime = new Date(today.getFullYear(), today.getMonth(), today.getDate());
                const dayDateWithoutTime = new Date(dayDate.getFullYear(), dayDate.getMonth(), dayDate.getDate());
                toggle.disabled = dayDateWithoutTime < todayWithoutTime;
            }

            if (toggle.disabled) {
                toggle.checked = false;
                const hoursContainer = document.getElementById(`hours-${dayName}`);
                if (hoursContainer) {
                    hoursContainer.style.display = 'none';
                }
            }
            
            console.log(`Día: ${dayName}, Fecha: ${dayDate.toLocaleDateString()}, Hoy: ${today.toLocaleDateString()}, Deshabilitado: ${toggle.disabled}`);
        });
    }

    // Agregar evento para mostrar/ocultar horarios
    dayToggles.forEach(toggle => {
        toggle.addEventListener('change', function() {
            const day = this.dataset.day;
            const hoursContainer = document.getElementById(`hours-${day}`);
            const checkboxes = hoursContainer.querySelectorAll('.hour-checkbox');
            
            if (this.checked) {
                hoursContainer.style.display = 'block';
                checkboxes.forEach(checkbox => {
                    checkbox.disabled = false;
                });
            } else {
                hoursContainer.style.display = 'none';
                checkboxes.forEach(checkbox => {
                    checkbox.disabled = true;
                    checkbox.checked = false;
                });
            }
        });
    });

    weekSelector.addEventListener('change', updateDaysAvailability);
    updateDaysAvailability(); // Llamada inicial
});
</script>

<style>
.hours-container {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
}

.form-check-inline {
    margin-right: 15px;
    background-color: #f8f9fa;
    padding: 5px 10px;
    border-radius: 5px;
}

.hour-checkbox:disabled + label {
    color: #999;
}

.form-check-input:checked + label {
    font-weight: bold;
    color: #2196F3;
}
</style>
@endsection