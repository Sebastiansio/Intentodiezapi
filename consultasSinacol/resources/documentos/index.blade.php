@extends('layouts.default', ['paceTop' => true])

@section('title', 'Plantillas')

@include('includes.component.datatables')

@section('content')

    <!-- begin breadcrumb -->
    <ol class="breadcrumb float-xl-right">
        <li class="breadcrumb-item"><a href="javascript:;">Home</a></li>
        <li class="breadcrumb-item"><a href="javascript:;">Plantillas Documentso</a></li>
        <!-- <li class="breadcrumb-item active">Audiencias</li> -->
    </ol>
    <!-- end breadcrumb -->
    <!-- begin page-header -->
    <h1 class="page-header">Administrar plantillas <small>Listado de plantillas</small></h1>
    <!-- end page-header -->
    <!-- begin panel -->
    <div class="panel panel-default">
        <!-- begin panel-heading -->
        <div class="panel-heading">
            <h4 class="panel-title">Listado de plantillas</h4>
            <div class="panel-heading-btn">
                <a class="btn btn-primary" id="btnNuevo"><i class="fa fa-plus-circle"></i> Nuevo</a>
            </div>


        </div>
        <!-- end panel-heading -->
        <!-- begin panel-body -->
        <div class="panel-body">


            <div class="col-sm-3 float-right mb-3">
                <form class="" role="search">
                    <div class="input-group">
                        <input type="text" class="form-control" placeholder="Buscar plantilla" name="nombre_plantilla" value="{{request()->get('nombre_plantilla')}}">
                        <div class="input-group-btn">
                            <button class="btn btn-default" type="submit"><i class="fa fa-search"></i></button>
                        </div>
                    </div>
                </form>
            </div>



            @include('documentos._list')
        </div>
    </div>

@endsection

@push('scripts')
    <script>
        $(document).ready(function() {
            //$('#data-table-default').DataTable({paging: false,"info":false,responsive: true,language: {url: "/assets/plugins/datatables.net/dataTable.es.json"}});
        });
        $('.btn-borrar').on('click', function (e) {
            let that = this;
            e.preventDefault();
            swal({
                title: '¿Está seguro?',
                text: 'Al oprimir el botón de aceptar se eliminará el registro',
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
