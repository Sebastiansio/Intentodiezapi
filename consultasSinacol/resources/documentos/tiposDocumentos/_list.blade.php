<table id="data-table-default" class="table table-striped table-bordered table-td-valign-middle">
    <thead>
    <tr>
        <th width="1%"></th>
        <th class="text-nowrap">Nombre</th>
        <th class="text-nowrap all">Acciones</th>
    </tr>
    </thead>
    <tbody>
    @foreach($tipoDocumento as $tipoD)
        <tr class="odd gradeX">
            <td width="1%" class="f-s-600 text-inverse">{{$tipoD->id}}</td>
            <td>{{$tipoD->nombre}}</td>
            <td class="all">
                <form action="{{ route('tipo-documento.destroy', $tipoD->id) }}" method="POST" style="display: inline-block;">
                    @csrf
                    @method('DELETE')
                    <div style="display: inline-block;">
                        <a href="{{route('tipo-documento.edit',[$tipoD])}}" class="btn btn-xs btn-primary">
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
