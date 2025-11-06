@extends('layouts.app') {{-- O el layout principal que estés usando --}}

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">Carga Masiva de Solicitudes desde Excel</div>

                <div class="card-body">
                    {{-- Mensajes de éxito o error --}}
                    @if (session('success'))
                        <div class="alert alert-success" role="alert">
                            {{ session('success') }}
                        </div>
                    @endif
                    @if (session('error'))
                        <div class="alert alert-danger" role="alert">
                            {{ session('error') }}
                        </div>
                    @endif

                    <form action="{{ route('carga.handle') }}" method="POST" enctype="multipart/form-data">
                        @csrf

                        <div class="form-group">
                            <label for="excel_file">Selecciona el archivo Excel (.xlsx o .csv)</label>
                            <input type="file" class="form-control-file" id="excel_file" name="excel_file" required accept=".xlsx, .csv">
                            @error('excel_file')
                                <div class="text-danger">{{ $message }}</div>
                            @enderror
                        </div>

                        <br>

                        <button type="submit" class="btn btn-primary">
                            Subir y Procesar Archivo
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
