<?php
namespace App\Http\Controllers;


use Carbon\Carbon;
use App\Models\Audiencia;
use App\Http\Controllers\DB;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
class AudienciasController extends Controller
{
    public function getAudiencias(Request $request)
    {
        // $fechaAudiencia = $request->input('fecha_audiencia');y
        $fechaAudiencia = Carbon::now();
        $fechaAudiencia = $fechaAudiencia->format('Y-m-d');

        $audiencias = Audiencia::withDetails($fechaAudiencia)->get();
        
        return response()->json($audiencias);
        
    }

    public function getHolaMundo(Request $request){
        return response()->json(['hola' => 'mundo']);
    }

    public function getAudienciasPorDia(Request $request)
    {
         // Obtener la fecha del request o usar la fecha actual
        $fecha = Carbon::now();
        $fecha = $fecha->format('Y-m-d');

        $audiencias = Audiencia::conciliacionAudiencias($fecha, $fecha)->get();

        // Formatear los resultados para incluir los campos requeridos
        $result = $audiencias->map(function ($item) {
            return [
                'Expediente' => $item->expediente,
                'Folio_soli' => $item->folio_solicitud,
                'Fecha audiencia' => $item->fecha_evento,
                'Hora de inicio' => $item->hora_inicio,
                'Hora Fin' => $item->hora_termino,
                'Conciliador' => $item->conciliador,
                'Estatus' => $item->estatus,
                'Solicitante' => $item->solicitantes,
                'Citado' => $item->citados,           
            ];
        });

        return response()->json($result);
    }



}
