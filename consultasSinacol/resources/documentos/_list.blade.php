<table id="data-table-default" class="table table-striped table-bordered table-td-valign-middle">
    <thead>
    <tr>
        <th width="1%" nowrap>
            <a href="{!! request()->fullUrlWithQuery(['sort_by' => 'id', 'dir'=>(request()->get('dir')=='asc')?'desc':'asc']) !!}">
                ID
                @if(request()->get('dir') == 'asc' && request()->get('sort_by') == 'id')
                <b class="fa fa-caret-down"></b>
                @elseif(request()->get('dir') == 'desc' && request()->get('sort_by') == 'id')
                <b class="fa fa-caret-up"></b>
                @endif
            </a>

        </th>
        <!-- <th class="text-nowrap">Folio</th> -->
        <th class="text-nowrap">
            <a href="{!! request()->fullUrlWithQuery(['sort_by' => 'nombre_plantilla', 'dir'=>(request()->get('dir')=='asc')?'desc':'asc']) !!}">
                Nombre
                @if(request()->get('dir') == 'asc' && request()->get('sort_by') == 'nombre_plantilla')
                    <b class="fa fa-caret-down"></b>
                @elseif(request()->get('dir') == 'desc' && request()->get('sort_by') == 'nombre_plantilla')
                    <b class="fa fa-caret-up"></b>
                @endif
            </a>
        </th>
        <th class="text-nowrap">
            <a href="{!! request()->fullUrlWithQuery(['sort_by' => 'clave_nomenclatura', 'dir'=>(request()->get('dir')=='asc')?'desc':'asc']) !!}">
                Clave nomenclatura
                @if(request()->get('dir') == 'asc' && request()->get('sort_by') == 'clave_nomenclatura')
                    <b class="fa fa-caret-down"></b>
                @elseif(request()->get('dir') == 'desc' && request()->get('sort_by') == 'clave_nomenclatura')
                    <b class="fa fa-caret-up"></b>
                @endif
            </a>
        </th>
        <!-- <th class="text-nowrap">consecutivo</th> -->
        <th >Acciones</th>
    </tr>
    </thead>
    <tbody>
    @foreach($plantillas as $plantilla)
        <tr class="odd gradeX">
            <td width="1%" class="f-s-600 text-inverse">{{$plantilla->id}}</td>
            <td>{{$plantilla->nombre_plantilla}}</td>
            <td>{{$plantilla->clave_nomenclatura}}</td>
            <td class="all">
                <form action="{{ route('plantilla-documentos.destroy', $plantilla->id) }}" method="POST" style="display: inline-block;">
                    @csrf
                    @method('DELETE')
                    <div style="display: inline-block;">
                        <a href="{{route('plantilla-documentos.edit',[$plantilla])}}" class="btn btn-xs btn-primary">
                            <i class="fa fa-pencil-alt"></i>
                        </a>
                        <button class="btn btn-xs btn-warning btn-borrar">
                            <i class="fa fa-trash btn-borrar"></i>
                        </button>
                    </div>
                </form>
            </td>
        </tr>
    @endforeach

    </tbody>
</table>
<p>Mostrando registros del {{ (($plantillas->currentPage() -1) * 10)+1 }} al {{ ((($plantillas->currentPage() -1) * 10))+$plantillas->count() }} de un total de {{ $plantillas->total() }} registros: </p>

{{ $plantillas->appends(request()->query())->links() }}
