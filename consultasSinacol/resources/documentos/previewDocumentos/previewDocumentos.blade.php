@extends('layouts.default', ['paceTop' => true])

@section('title', 'Previsualización')

@include('includes.component.datatables')
@include('includes.component.pickers')
@include('includes.component.calendar')
@include('includes.component.dropzone')
@section('content')
<!-- begin page-header -->
<span class="GeneralTitle-head">Previsualización de documentos de la audiencia {{$audiencia->folio.'/'.$audiencia->anio}}</span>
<hr class="red">
<!-- end page-header -->
<!-- begin panel -->
<div class="panel panel-default">
    <input type="hidden" id="audiencia_id" value="{{@$audiencia->id}}">
    <input type="hidden" id="solicitud_id" value="{{@$solicitud->id}}">
    <input type="hidden" id="solicitante_id" value="{{@$solicitante_id}}">
    <input type="hidden" id="solicitado_id" value="{{@$solicitado_id}}">
    <input type="hidden" id="resolucion_id" value="{{@$resolucion_id}}">
    <input type="hidden" id="elementos_adicionales" value="{{@$elementos_adicionales}}">
    <input type="hidden" name="listaConceptos[]" id="listaConceptos" value="{{@$listaConceptos}}">

    <input type="hidden" id="listaRelacion" value="{{@$listaResolucionesIndividuales}}">
    <input type="hidden" id="listaFechasPago" value="{{@$listaFechasPago}}">
    <input type="hidden" id="descripcion_pagos" value="{{@$descripcion_pagos}}">
    <input type="hidden" name="maxFileSize" id="maxFileSize" value="{{@$maxFileSize}}">
    <input type="hidden" name="documentosfirmados" id="documentosfirmados" value="{{@$documentosfirmados}}">
    <input type="hidden" id="regenerar" value="{{@$regenerar}}">

    <div class="panel-body">
        <form id="formEliminarDocumento" action={{ route('plantilla-documento.eliminar_file_parte') }} method="POST" enctype="multipart/form-data" data-parsley-validate="true">
            <input type="hidden" name="idDocumento" id="idDocumento" value="">
            <input type="hidden" name="_token" value="{{ csrf_token() }}">
        </form>
        @include('documentos.previewDocumentos.listaDocumentos')
        <div class="mb-2 classFirmaElectronica d-none" style="text-align: end;">
            <a class="btn cursor-pointer" onclick="javascript:modalElectronica(this);" style="border:2px solid {{config('colores.btn-primary-color')}}; color: {{config('colores.btn-primary-color')}}">
                <svg width="16px" height="16px" viewBox="0 0 16 16" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">
                    <title>F6FEF1A2-690E-4077-B0BC-0476FA138936</title>
                    <g id="Page-1" stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                        <g id="firma-5" transform="translate(-758, -230)" fill="{{config('colores.btn-primary-color')}}" fill-rule="nonzero">
                            <g id="Group-8" transform="translate(732, 230)">
                                <g id="Group" transform="translate(26, 0)">
                                    <path d="M0,0 L0,4.66660156 L4.66660156,4.66660156 L4.66660156,0 L0,0 Z M3.33339844,3.33339844 L1.33339844,3.33339844 L1.33339844,1.33339844 L3.33339844,1.33339844 L3.33339844,3.33339844 Z" id="Shape"></path>
                                    <path d="M0,11.3332031 L0,16 L4.66660156,16 L4.66660156,11.3332031 L0,11.3332031 Z M3.33339844,14.6667969 L1.33339844,14.6667969 L1.33339844,12.6667969 L3.33339844,12.6667969 L3.33339844,14.6667969 Z" id="Shape"></path>
                                    <path d="M11.3333984,0 L11.3333984,4.66660156 L16,4.66660156 L16,0 L11.3333984,0 Z M14.6666016,3.33339844 L12.6666016,3.33339844 L12.6666016,1.33339844 L14.6666016,1.33339844 L14.6666016,3.33339844 Z" id="Shape"></path>
                                    <polygon id="Path" points="14.6666016 6 14.6666016 10 11.3333984 10 11.3333984 11.3332031 16 11.3332031 16 6">
                                    </polygon>
                                    <polygon id="Path" points="11.3333984 12.6667969 11.3333984 16 12.6666016 16 12.6666016 14 14.6666016 14 14.6666016 16 16 16 16 12.6667969">
                                    </polygon>
                                    <polygon id="Path" points="6 0 6 1.33339844 8.66660156 1.33339844 8.66660156 4.66660156 10 4.66660156 10 0">
                                    </polygon>
                                    <polygon id="Path" points="8.66660156 6 8.66660156 8.66679687 6 8.66679687 6 12.6667969 8.66660156 12.6667969 8.66660156 16 10 16 10 11.3332031 7.33339844 11.3332031 7.33339844 10 10 10 10 7.33339844 11.3333984 7.33339844 11.3333984 8.66679687 12.6666016 8.66679687 12.6666016 6">
                                    </polygon>
                                    <rect id="Rectangle" x="6" y="14" width="1.33339844" height="2"></rect>
                                    <rect id="Rectangle" x="2.66660156" y="8.66601562" width="2" height="1.33320313"></rect>
                                    <polygon id="Path" points="6 2.66660156 6 6 0 6 0 10 1.33339844 10 1.33339844 7.33339844 7.33339844 7.33339844 7.33339844 2.66660156">
                                    </polygon>
                                </g>
                            </g>
                        </g>
                    </g>
                </svg> Firmar documentos electrónicamente <span class="total_firmas">(2)</span>
            </a>
        </div>
    </div>
    <div class="panel-footer">
        <div class="text-right">
            <a class="btn btnRegresar cursor-pointer" style="border: 2px solid #555770;" onclick="javascript:regresarGuiaAudiencia({{@$solicitante_id}});"><i class="fa fa-times"></i> Cancelar </a>
            <a class="btn cursor-pointer" onclick="javascript:nuevaResolucion({{@$solicitante_id}});" style="border:2px solid {{config('colores.btn-primary-color')}}; color: {{config('colores.btn-primary-color')}}"><svg width="15" height="12" viewBox="0 0 15 12" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink">
                    <defs>
                        <path d="M14.108 2.029 12.269.19a.65.65 0 0 0-.92 0l-4.2 4.201-2.362 2.363-1.839-1.839a.65.65 0 0 0-.92 0L.19 6.754a.65.65 0 0 0 0 .919l4.137 4.137a.65.65 0 0 0 .92 0l1.902-1.903 6.959-6.959a.65.65 0 0 0 0-.92z" id="qxtkfbhcwa" />
                    </defs>
                    <use fill="{{config('colores.btn-primary-color')}}" xlink:href="#qxtkfbhcwa" fill-rule="evenodd" />
                </svg> Finalizar
            </a>
        </div>
    </div>
</div>
@include('documentos.previewDocumentos.modal_preview')
@include('documentos.previewDocumentos.modal_autografa')
@include('documentos.previewDocumentos.modal_electronica')
@include('componentes.modal_previewDocumentosPDF')
@endsection
@push('scripts')
<script src="/js/firma_documentos.js?v={{\Carbon\Carbon::now()->toDateTimeString()}}"></script>
<script src="/js/fiel_util.js?v={{\Carbon\Carbon::now()->toDateTimeString()}}"></script>
<script src="/js/fiel.js?v={{\Carbon\Carbon::now()->toDateTimeString()}}"></script>
<script src="/js/helper.js?v={{\Carbon\Carbon::now()->toDateTimeString()}}"></script>
<script src="https://cdn.jsdelivr.net/npm/node-forge@1.0.0/dist/forge.min.js?v={{\Carbon\Carbon::now()->toDateTimeString()}}"></script>
@endpush