<?php

namespace App\Http\Controllers;

use App\Contador;
use App\Filters\CatalogoFilter;
use Illuminate\Http\Request;

class ContadorController extends Controller
{
    protected $request;

    public function __construct(?Request $request = null)
    {
        $this->request = $request;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        Contador::with('tipoContador')->get();

        // Filtramos los usuarios con los parametros que vengan en el request
        $contadores = (new CatalogoFilter(Contador::query(), $this->request))
            ->searchWith(Contador::class)
            ->filter();
        //        dd($contadores);
        // Si en el request viene el parametro all entonces regresamos todos los elementos
        // de lo contrario paginamos
        if ($this->request->get('all')) {
            $contadores = $contadores->get();
        } else {
            $contadores = $contadores->paginate($this->request->get('per_page', 10));
        }

        // // Para cada objeto obtenido cargamos sus relaciones.
        $contadores = tap($contadores)->each(function ($contador) {
            $contador->loadDataFromRequest();
        });

        // return $this->sendResponse($solicitud, 'SUCCESS');

        if ($this->request->wantsJson()) {
            return $this->sendResponse($contadores, 'SUCCESS');
        }

        return view('contadores.index', compact('contadores'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('contadores.create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        if ($request->id == '') {
            $contador = Contador::create($request->all());
        } else {
            $contador = Contador::find($request->id);
            $contador->update(['contador' => $request->contador, 'anio' => $request->anio, 'centro_id' => $request->centro_id, 'tipo_contador_id' => $request->tipo_contador_id]);
            //            dd(Contador::find($request->id));
        }

        return $contador;
    }

    /**
     * Display the specified resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function show(Contador $contador)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Contador  $contador
     */
    public function edit($id)
    {
        $contador = Contador::find($id);

        return view('contadores.edit', compact('contador'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Contador $contador)
    {
        //        dd($request);
        $contador->update($request->all());

        // $sala->fill($request->all())->save();
        //         return redirect()->back();
        return redirect('salas');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function destroy(Contador $contador)
    {
        //
    }
}
