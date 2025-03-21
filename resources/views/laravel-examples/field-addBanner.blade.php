@extends('layouts.user_type.auth')

@section('content')
<div class="container-fluid py-4">
    <div class="card">
        <div class="card-header pb-0 px-3">
            <h6 class="mb-0">Nuevo Banner</h6>
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
            <form method="POST" action="{{ route('banner.store') }}" enctype="multipart/form-data">
                @csrf
                <div class="form-group">
                    <label for="image">Imagen del Banner</label>
                    <input type="file" id="image" name="image" 
                        class="form-control @error('image') is-invalid @enderror" 
                        accept="image/jpeg,image/png,image/jpg" required>
                    @error('image')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <small class="text-muted">Formatos permitidos: JPG, JPEG, PNG. Tamaño máximo: 10MB</small>
                </div>
                <div class="d-flex justify-content-end mt-4">
                    <a href="{{ url('banner') }}" class="btn btn-light m-0">Cancelar</a>
                    <button type="submit" class="btn bg-gradient-primary m-0 ms-2">Agregar Banner</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection