@extends('layouts.app') {{-- O el layout principal que estés usando --}}

@section('title', 'Carga Masiva de Solicitudes')

@push('css')
    {{-- Agrega aquí cualquier CSS adicional si es necesario, como para Select2 --}}
    <link href="/assets/plugins/select2/dist/css/select2.min.css" rel="stylesheet" />
@endpush

@section('content')
    <ol class="breadcrumb pull-right">
        <li class="breadcrumb-item"><a href="{{ route('home') }}">Inicio</a></li>
        <li class="breadcrumb-item"><a href="{{ route('solicitudes.index') }}">Solicitudes</a></li>
        <li class="breadcrumb-item active">Carga Masiva</li>
    </ol>
    <h1 class="page-header">Carga Masiva de Solicitudes</h1>
    @include('includes.flash-messages')

    @if ($errors->any())
        <div class="alert alert-danger">
            <h4 class="alert-heading">Error de Validación</h4>
            <p>Se encontraron los siguientes errores en tu formulario:</p>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif
    <form action="{{ route('solicitud.masiva.store') }}" method="POST" enctype="multipart/form-data">
        @csrf

        <div class="panel panel-inverse">
            <div class="panel-heading">
                <h4 class="panel-title">1. Datos Comunes de la Solicitud</h4>
            </div>
            <div class="panel-body">
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="fecha_conflicto">Fecha de Conflicto *</label>
                            <input type="date" class="form-control" name="fecha_conflicto" value="{{ old('fecha_conflicto') }}" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="tipo_solicitud_id">Tipo de Solicitud *</label>
                            <select class="form-control" name="tipo_solicitud_id" required>
                                {{-- Asumiendo que pasas $tipo_solicitudes desde el controlador --}}
                                @foreach($tipo_solicitudes ?? [] as $id => $nombre)
                                    <option value="{{ $id }}" {{ old('tipo_solicitud_id') == $id ? 'selected' : '' }}>{{ $nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="giro_comercial_id">Giro Comercial *</label>
                            <select class="form-control" name="giro_comercial_id" required>
                                {{-- Asumiendo que pasas $giros_comerciales desde el controlador --}}
                                @foreach($giros_comerciales ?? [] as $id => $nombre)
                                    <option value="{{ $id }}" {{ old('giro_comercial_id') == $id ? 'selected' : '' }}>{{ $nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
                <div class="row">
                     <div class="col-md-8">
                        <div class="form-group">
                            <label for="objeto_solicitudes">Objeto(s) de Solicitud *</label>
                            <select class="form-control select2" name="objeto_solicitudes[]" multiple="multiple" required>
                                {{-- Asumiendo que pasas $objeto_solicitudes desde el controlador --}}
                                @foreach($objeto_solicitudes ?? [] as $id => $nombre)
                                    <option value="{{ $id }}" {{ (is_array(old('objeto_solicitudes')) && in_array($id, old('objeto_solicitudes'))) ? 'selected' : '' }}>{{ $nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                         <div class="form-group">
                            <label for="virtual">Modalidad de Audiencia *</label>
                            <div>
                                <label><input type="radio" name="virtual" value="0" {{ old('virtual', '0') == '0' ? 'checked' : '' }}> Presencial</label>
                                <label class="ml-3"><input type="radio" name="virtual" value="1" {{ old('virtual') == '1' ? 'checked' : '' }}> Virtual</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="panel panel-inverse">
            <div class="panel-heading">
                <h4 class="panel-title">2. Datos del Solicitante Único</h4>
            </div>
            <div class="panel-body">
                <div class="row">
                    <div class="col-md-12">
                        <div class="form-group">
                            <label>Tipo de Persona *</label>
                            <div>
                                <label><input type="radio" name="solicitante[tipo_persona_id]" value="1" {{ old('solicitante.tipo_persona_id', '1') == '1' ? 'checked' : '' }}> Persona Física</label>
                                <label class="ml-3"><input type="radio" name="solicitante[tipo_persona_id]" value="2" {{ old('solicitante.tipo_persona_id') == '2' ? 'checked' : '' }}> Persona Moral</label>
                            </div>
                        </div>
                    </div>
                </div>
                
                {{-- CAMPOS PARA PERSONA MORAL --}}
                <div class="row persona-moral-fields" style="display: none;">
                    <div class="col-md-8">
                        <div class="form-group">
                            <label for="solicitante_nombre_comercial">Nombre Comercial / Razón Social *</label>
                            <input type="text" class="form-control" name="solicitante[nombre_comercial]" value="{{ old('solicitante.nombre_comercial') }}">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="solicitante_rfc_moral">RFC *</label>
                            <input type="text" class="form-control" name="solicitante[rfc]" value="{{ old('solicitante.rfc') }}">
                        </div>
                    </div>
                </div>
                
                {{-- CAMPOS PARA PERSONA FÍSICA --}}
                <div class="row persona-fisica-fields" style="display: none;">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="solicitante_nombre">Nombre(s) *</label>
                            <input type="text" class="form-control" name="solicitante[nombre]" value="{{ old('solicitante.nombre') }}">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="solicitante_primer_apellido">Primer Apellido *</label>
                            <input type="text" class="form-control" name="solicitante[primer_apellido]" value="{{ old('solicitante.primer_apellido') }}">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="solicitante_segundo_apellido">Segundo Apellido</label>
                            <input type="text" class="form-control" name="solicitante[segundo_apellido]" value="{{ old('solicitante.segundo_apellido') }}">
                        </div>
                    </div>
                     <div class="col-md-4">
                        <div class="form-group">
                            <label for="solicitante_curp">CURP</label>
                            <input type="text" class="form-control" name="solicitante[curp]" value="{{ old('solicitante.curp') }}">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="solicitante_rfc_fisica">RFC</label>
                            <input type="text" class="form-control" name="solicitante[rfc_fisica]" value="{{ old('solicitante.rfc_fisica') }}">
                        </div>
                    </div>
                </div>

                <hr>
                <h5 class="mt-3">Datos de Contacto del Solicitante</h5>
                {{-- Basado en la lógica de getSolicitante() --}}
                <div class="row">
                    <div class="col-md-4">
                         <div class="form-group">
                            <label for="solicitante_telefono">Teléfono Celular *</label>
                            <input type="tel" class="form-control" name="solicitante[contactos][0][telefono]" value="{{ old('solicitante.contactos.0.telefono') }}" required>
                            <input type="hidden" name="solicitante[contactos][0][tipo_contacto_id]" value="1"> {{-- Asumiendo 1 = Celular --}}
                        </div>
                    </div>
                     <div class="col-md-4">
                         <div class="form-group">
                            <label for="solicitante_email">Email *</label>
                            <input type="email" class="form-control" name="solicitante[contactos][1][telefono]" value="{{ old('solicitante.contactos.1.telefono') }}" required>
                            <input type="hidden" name="solicitante[contactos][1][tipo_contacto_id]" value="3"> {{-- Asumiendo 3 = Email --}}
                        </div>
                    </div>
                </div>
                
                <hr>
                <h5 class="mt-3">Domicilio del Solicitante *</h5>
                {{-- Basado en la lógica de getSolicitante() --}}
                 <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="estado_id">Estado *</label>
                            <select class="form-control" name="solicitante[domicilios][0][estado_id]" required>
                                @foreach($estados ?? [] as $estado)
                                    <option value="{{ $estado->id }}" {{ old('solicitante.domicilios.0.estado_id') == $estado->id ? 'selected' : '' }}>{{ $estado->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="municipio">Municipio / Alcaldía *</label>
                            <input type="text" class="form-control" name="solicitante[domicilios][0][municipio]" value="{{ old('solicitante.domicilios.0.municipio') }}" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="cp">Código Postal *</label>
                            <input type="text" class="form-control" name="solicitante[domicilios][0][cp]" value="{{ old('solicitante.domicilios.0.cp') }}" required>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="tipo_vialidad_id">Tipo de Vialidad *</label>
                            <select class="form-control" name="solicitante[domicilios][0][tipo_vialidad_id]" required>
                                @foreach($tipo_vialidades ?? [] as $vialidad)
                                    <option value="{{ $vialidad->id }}" {{ old('solicitante.domicilios.0.tipo_vialidad_id') == $vialidad->id ? 'selected' : '' }}>{{ $vialidad->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-md-8">
                        <div class="form-group">
                            <label for="vialidad">Nombre de la Vialidad o Calle *</label>
                            <input type="text" class="form-control" name="solicitante[domicilios][0][vialidad]" value="{{ old('solicitante.domicilios.0.vialidad') }}" required>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="num_ext">Número Exterior *</label>
                            <input type="text" class="form-control" name="solicitante[domicilios][0][num_ext]" value="{{ old('solicitante.domicilios.0.num_ext') }}" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="num_int">Número Interior</label>
                            <input type="text" class="form-control" name="solicitante[domicilios][0][num_int]" value="{{ old('solicitante.domicilios.0.num_int') }}">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="asentamiento">Colonia / Asentamiento *</label>
                            <input type="text" class="form-control" name="solicitante[domicilios][0][asentamiento]" value="{{ old('solicitante.domicilios.0.asentamiento') }}" required>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <div class="panel panel-inverse">
            <div class="panel-heading">
                <h4 class="panel-title">3. Archivo de Citados (Múltiples)</h4>
            </div>
            <div class="panel-body">
                <div class="form-group">
                    <label for="archivo_citados">Subir Archivo (CSV, XLSX) *</label>
                    <input type="file" class="form-control" name="archivo_citados" accept=".csv, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, application/vnd.ms-excel" required>
                </div>
                
                <div class="note">
                    <div class="note-icon"><i class="fa fa-info-circle"></i></div>
                    <div class="note-content">
                        <h4><b>Instrucciones Importantes</b></h4>
                        <p>
                            El archivo que subas aquí debe contener **únicamente a los citados**. Los datos del solicitante se tomarán del formulario anterior.
                        </p>
                        <p>
                            Asegúrate de que tu archivo CSV o Excel tenga las **30 columnas** en el orden correcto que espera el sistema (el mismo formato que usa tu comando `SolicitudMasiva`).
                        </p>
                    </div>
                </div>
                
            </div>
        </div>

        <div class="row">
            <div class="col-md-12 text-right">
                <a href="{{ route('solicitudes.index') }}" class="btn btn-default btn-lg">Cancelar</a>
                <button type="submit" class="btn btn-success btn-lg">Procesar Carga Masiva</button>
            </div>
        </div>
    </form>

@endsection

@push('scripts')
    <script src="/assets/plugins/select2/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {
            // Inicializar Select2
            $('.select2').select2({
                placeholder: 'Seleccione una o más opciones'
            });

            // Lógica para mostrar/ocultar campos de Persona Física/Moral
            function togglePersonaFields(tipo) {
                if (tipo == '1') { // Física
                    $('.persona-fisica-fields').show();
                    $('.persona-moral-fields').hide();
                    // Ajustar 'required' y 'name'
                    $('input[name="solicitante[nombre_comercial]"]').prop('required', false);
                    $('input[name="solicitante[rfc]"]').prop('required', false);
                    $('input[name="solicitante[nombre]"]').prop('required', true);
                    $('input[name="solicitante[primer_apellido]"]').prop('required', true);
                    // Cambiar el name del RFC de moral al de fisica (o viceversa)
                    $('input[name="solicitante[rfc]"]').attr('name', 'solicitante[rfc_moral_disabled]');
                    $('input[name="solicitante[rfc_fisica]"]').attr('name', 'solicitante[rfc]');

                } else if (tipo == '2') { // Moral
                    $('.persona-fisica-fields').hide();
                    $('.persona-moral-fields').show();
                    // Ajustar 'required' y 'name'
                    $('input[name="solicitante[nombre_comercial]"]').prop('required', true);
                    $('input[name="solicitante[rfc]"]').prop('required', true);
                    $('input[name="solicitante[nombre]"]').prop('required', false);
                    $('input[name="solicitante[primer_apellido]"]').prop('required', false);
                    // Cambiar el name del RFC
                    $('input[name="solicitante[rfc]"]').attr('name', 'solicitante[rfc_fisica]');
                    $('input[name="solicitante[rfc_moral_disabled]"]').attr('name', 'solicitante[rfc]');
                }
            }

            // Al cargar la página
            var tipoSeleccionado = $('input[name="solicitante[tipo_persona_id]"]:checked').val();
            togglePersonaFields(tipoSeleccionado);

            // Al cambiar la selección
            $('input[name="solicitante[tipo_persona_id]"]').change(function() {
                togglePersonaFields(this.value);
            });
        });
    </script>
@endpush