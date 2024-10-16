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
            $fecha = $request->input('fecha', date('Y-m-d'));
    
            $audiencias = Audiencia::conciliacionAudiencias($fecha, $fecha)->get();
    
            // Formatear los resultados para incluir los campos requeridos
            $result = $audiencias->map(function ($item) {
                // Decodificar 'solicitantes' y 'citados' si son cadenas JSON
                $solicitantes = $item->solicitantes;
                if (is_string($solicitantes)) {
                    $solicitantes = json_decode($solicitantes, true) ?? [];
                }
    
                $citados = $item->citados;
                if (is_string($citados)) {
                    $citados = json_decode($citados, true) ?? [];
                }
    
                // Transformar 'solicitantes' en un array de objetos con clave 'Solicitante'
                $solicitantesArray = array_map(function ($solicitante) {
                    return ['Solicitante' => $solicitante];
                }, $solicitantes);
    
                // Transformar 'citados' en un array de objetos con clave 'Citado'
                $citadosArray = array_map(function ($citado) {
                    return ['Citado' => $citado];
                }, $citados);
    
                return [
                    'Expediente' => $item->expediente,
                    'Folio_soli' => $item->folio_solicitud,
                    'Fecha audiencia' => $item->fecha_evento,
                    'Hora de inicio' => $item->hora_inicio,
                    'Hora Fin' => $item->hora_termino,
                    'Conciliador' => $item->conciliador,
                    'Estatus' => $item->estatus,
                    'Solicitantes' => $solicitantesArray,
                    'Citados' => $citadosArray,
                ];
            });
    
            return response()->json($result);
        }

}
