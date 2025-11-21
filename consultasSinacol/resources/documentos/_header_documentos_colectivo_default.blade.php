{{-- @extends('layouts.header-footer') --}}
{{-- @section('content') --}}
    
    
    <p style="margin-top: 1%;">
        CENTRO FEDERAL DE CONCILIACIÓN Y REGISTRO LABORAL 
    </p>
    <p>
        CON SEDE EN @if(isset($centro)) {{isset($centro->domicilio->estado) ? Str::upper($centro->domicilio->estado) : 'MEXICO'}} @endif
    </p>
    <p>
        NUMERO IDENTIFICACIÓN ÚNICO: @if(isset($solicitud)) {{$solicitud->expediente->folio}} @endif
    </p>
{{-- @endsection --}}
