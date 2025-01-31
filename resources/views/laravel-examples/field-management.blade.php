@extends('layouts.user_type.auth')
@section('content')
<div>
    <div class="row">
        <div class="col-12">
            <div class="card mb-4 mx-4">
                <div class="card-header pb-0">
                    <div class="d-flex flex-row justify-content-between">
                        <div>
                            <h5 class="mb-0">Todas las Canchas</h5>
                        </div>
                        <a href="{{ route('field.create') }}" class="btn bg-gradient-primary btn-sm mb-0" type="button">+ NUEVA CANCHA</a>
                    </div>
                
                    </div>
                <div class="card-body px-0 pt-0 pb-2">
                    <div class="table-responsive p-0">
                        <table class="table align-items-center mb-0">
                            <thead>
                                <tr>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">ID</th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">NOMBRE</th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">TIPO</th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">UBICACIÓN</th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">PRECIO/HORA</th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">ESTADO</th>
                                    <th class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">ACCIONES</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($fields as $field)
                                <tr>
                                    <td class="ps-4">
                                        <p class="text-xs font-weight-bold mb-0">{{ $field->id }}</p>
                                    </td>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">{{ $field->name }}</p>
                                    </td>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">{{ $field->type }}</p>
                                    </td>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">{{ $field->location }}</p>
                                    </td>
                                    <td>
                                        <p class="text-xs font-weight-bold mb-0">${{ $field->price_per_match }}</p>
                                    </td>
                                    <td>
                                        <span class="badge badge-sm {{ $field->is_active ? 'bg-gradient-success' : 'bg-gradient-secondary' }}">
                                            {{ $field->is_active ? 'Activa' : 'Inactiva' }}
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <a href="{{ route('field.edit', $field->id) }}" class="mx-1">
                                            <i class="fas fa-edit text-secondary"></i>
                                        </a>
                                        <form action="{{ route('field.destroy', $field->id) }}" method="POST" style="display: inline;">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-link p-0 mx-1" 
                                                    onclick="return confirm('¿Está seguro de eliminar esta cancha?')">
                                                <i class="fas fa-trash text-secondary"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection