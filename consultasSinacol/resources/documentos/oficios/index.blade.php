@extends('layouts.default', ['paceTop' => true])

@section('title', 'Expedicion de oficios')

@include('includes.component.datatables')

@section('content')

    <!-- begin breadcrumb -->
    <ol class="breadcrumb float-xl-right">
        <li class="breadcrumb-item"><a href="javascript:;">Inicio</a></li>
        <li class="breadcrumb-item"><a href="javascript:;">Oficios Documentos</a></li>
    </ol>
    <div class="panel panel-default">
        <!-- begin panel-body -->
        @if($plantilla_documento)
        <div class="panel-body">
            @include('documentos.oficios.editor')
        </div>
        @else
            <div class="panel-body">
                <h2 class="alert-warning alert">No se ha configurado correctamente la plantilla base de este proceso. <br>
                    <small>Favor de solicitar a su área técnica la correcta configuración del proceso de Oficio Libre en el archivo de ambiente (.env)</small></h2>
            </div>
        @endif
    </div>

@endsection

@push('scripts')
    <script>
        $(document).ready(function() {
            $('#data-table-default').DataTable({responsive: true,language: {url: "/assets/plugins/datatables.net/dataTable.es.json"}});
        });
        $('.btn-borrar').on('click', function (e) {
            let that = this;
            e.preventDefault();
            swal({
                title: '¿Está seguro?',
                text: 'Al oprimir el botón de Aceptar se eliminará el registro',
                icon: 'warning',
                buttons: {
                    cancel: {
                        text: 'Cancelar',
                        value: null,
                        visible: true,
                        className: 'btn btn-default',
                        closeModal: true,
                    },
                    confirm: {
                        text: 'Aceptar',
                        value: true,
                        visible: true,
                        className: 'btn btn-warning',
                        closeModal: true
                    }
                }
            }).then(function(isConfirm){
                if(isConfirm){
                    $(that).closest('form').submit();
                }
            });
            return false;
        });

        $('#btnNuevo').on('click', function (e) {
            let that = this;
            e.preventDefault();
            swal({
                title: '¿Desea cargar plantilla por default?',
                text: 'Al oprimir el botón de aceptar se cargara la plantilla base',
                icon: 'warning',
                buttons: {
                    cancel: {
                        text: 'Cancelar',
                        value: null,
                        visible: true,
                        className: 'btn btn-default',
                        closeModal: true,
                    },
                    confirm: {
                        text: 'Aceptar',
                        value: true,
                        visible: true,
                        className: 'btn btn-warning',
                        closeModal: true
                    }
                }
            }).then(function(isConfirm){
                if(isConfirm){
                  window.location.href = "{{ url('plantilla-documento/cargarDefault') }}"
                }else{

                  // window.location.href = "{{URL::to('plantilla-documentos.create')}}"
                  window.location.href = "{{ route('plantilla-documentos.create') }}"
                }
            });
            return false;
        });
    </script>
@endpush
