@extends('layouts.user_type.auth')

@section('content')
  <div class="row">
    <div class="col-xl-3 col-sm-6 mb-xl-0 mb-4">
      <div class="card">
        <div class="card-body p-3">
          <div class="row">
            <div class="col-8">
              <div class="numbers">
                <p class="text-sm mb-0 text-capitalize font-weight-bold">Nuevos Usuarios</p>
                <h5 class="font-weight-bolder mb-0">
                  {{ $newUsersCount }}
                  <span class="text-success text-sm font-weight-bolder">Este mes</span>
                </h5>
              </div>
            </div>
            <div class="col-4 text-end">
              <div class="icon icon-shape bg-gradient-primary shadow text-center border-radius-md">
                <i class="ni ni-single-02 text-lg opacity-10"></i>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-xl-3 col-sm-6 mb-xl-0 mb-4">
      <div class="card">
        <div class="card-body p-3">
          <div class="row">
            <div class="col-8">
              <div class="numbers">
                <p class="text-sm mb-0 text-capitalize font-weight-bold">Nuevos Equipos</p>
                <h5 class="font-weight-bolder mb-0">
                  {{ $newTeamsCount }}
                  <span class="text-success text-sm font-weight-bolder">Este mes</span>
                </h5>
              </div>
            </div>
            <div class="col-4 text-end">
              <div class="icon icon-shape bg-gradient-primary shadow text-center border-radius-md">
                <i class="ni ni-trophy text-lg opacity-10"></i>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <!-- ... otras cards ... -->
  </div>

  <div class="row mt-4">
    <div class="col-lg-7">
      <div class="card z-index-2">
        <div class="card-header pb-0">
          <h6>Registro de Usuarios y Equipos</h6>
        </div>
        <div class="card-body p-3">
          <div class="chart">
            <canvas id="mixed-chart" height="300"></canvas>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Nueva sección para mostrar partidos -->
  <div class="row mt-4">
    <div class="col-12">
      <div class="card">
        <div class="card-header pb-0">
          <h6>Lista de Partidos Diarios</h6>
          <p class="text-sm">Todos los partidos disponibles y completos</p>
        </div>
        <div class="card-body p-3">
          <div class="table-responsive">
            <table class="table align-items-center mb-0">
              <thead>
                <tr>
                  <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Nombre</th>
                  <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Cancha</th>
                  <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Fecha</th>
                  <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Hora</th>
                   <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">Estado</th>
                </tr>
              </thead>
              <tbody>
                @forelse ($matches as $match)
                  <tr class="{{ $match->status == 'full' ? 'bg-light text-muted' : '' }}">
                    <td>
                      <span class="text-xs font-weight-bold">{{ $match->name }}</span>
                    </td>
                    <td>
                      <span class="text-xs font-weight-bold">{{ $match->field->name ?? 'Sin cancha' }}</span>
                    </td>
                    <td>
                      <span class="text-xs font-weight-bold">{{ $match->schedule_date->format('d/m/Y') }}</span>
                    </td>
                    <td>
                      <span class="text-xs font-weight-bold">{{ $match->start_time }} - {{ $match->end_time }}</span>
                    </td>
                     
                    <td>
                      <span class="badge badge-sm {{ $match->status == 'full' ? 'bg-secondary' : 'bg-success' }}">
                        {{ $match->status == 'full' ? 'Completo' : 'Disponible' }}
                      </span>
                    </td>
                  </tr>
                @empty
                  <tr>
                    <td colspan="6" class="text-center">No hay partidos disponibles</td>
                  </tr>
                @endforelse
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
@endsection

@push('dashboard')
<script>
window.onload = function() {
  var ctx = document.getElementById("mixed-chart").getContext("2d");
  new Chart(ctx, {
    type: 'line',
    data: {
      labels: @json($monthLabels),
      datasets: [{
        label: 'Usuarios Registrados',
        data: @json($userData),
        borderColor: '#cb0c9f',
        tension: 0.4,
        fill: false
      }, {
        label: 'Equipos Creados',
        data: @json($teamData),
        borderColor: '#3A416F',
        tension: 0.4,
        fill: false
      }]
    },
    options: {
      responsive: true,
      interaction: {
        intersect: false,
        mode: 'index'
      }
    }
  });
};
</script>
@endpush