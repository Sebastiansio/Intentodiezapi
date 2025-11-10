<?php
namespace App\Http\Controllers;
use Carbon\Carbon;
use App\Audiencia;
// use App\Http\Controllers\DB;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Expediente;
use App\Solicitud;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
class AudienciasController extends Controller
{
    public function getAudienciasPorDia(Request $request): JsonResponse
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
                    'Folio audiencia' => $item->audiencia,
                    'Folio solicitud' => $item->folio_solicitud,
                    'Fecha audiencia' => $item->fecha_evento,
                    'Hora de inicio' => $item->hora_inicio,
                    'Hora Fin' => $item->hora_termino,
                    'Conciliador' => $item->conciliador,
                    'Estatus' => $item->estatus,
                    'url_virtual' => $item->url_virtual,
                    'Virtual' => $item->virtual,
                    'centro_id' => $item->centro_id,
                    'Solicitantes' => $solicitantesArray,
                    'Citados' => $citadosArray,
                ];
            });
    
            return response()->json($result);
        }

    public function getAudienciasDiaSiguiente(Request $request)
        {
            // Obtener la fecha del request o usar la fecha actual
            $fecha = "2024-10-25";
    
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
                    'Folio audiencia' => $item->audiencia,
                    'Folio solicitud' => $item->folio_solicitud,
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

        public function getMundo(){

            $mundo = "Hola mundo";

            return response()->json($mundo);
        }

    public function checkFolioExistsAMG(Request $request)
    {
        // Validate the 'folio' parameter from the request
        $request->validate([
            'folio' => 'required|string',
        ]);

        // Get the 'folio' parameter from the query
        $folio = $request->input('folio');

        // Check if the folio exists in the database
        $exists = Solicitud::where('folio', $folio)->exists();

        // Get the 'centro_id' associated with the 'folio'
        $centro_id = Solicitud::where('folio', $folio)->value('centro_id');

        // Check the existence and centro_id
        if ($exists) {
            if ($centro_id == 38) {
                return response()->json([
                    'exists' => $exists,
                    'message' => "El folio existe en el sistema."
                ]);
            } else {
                return response()->json([
                    'exists' => $exists,
                    'message' => "El folio existe en el sistema, pero no corresponde a la sede que quiere realizar la cita."
                ]);
            }
        } else {
            return response()->json([
                'exists' => $exists,
                'message' => "El folio no existe en el sistema, verifica que sea correcto."
            ]);
        }
    }

    public function checkFolioExistsTLJ(Request $request)
    {
        // Validate the 'folio' parameter from the request
        $request->validate([
            'folio' => 'required|string',
        ]);

        // Get the 'folio' parameter from the query
        $folio = $request->input('folio');

        // Check if the folio exists in the database
        $exists = Solicitud::where('folio', $folio)->exists();

        // Get the 'centro_id' associated with the 'folio'
        $centro_id = Solicitud::where('folio', $folio)->value('centro_id');

        // Check the existence and centro_id
        if ($exists) {
            if ($centro_id == 48) {
                return response()->json([
                    'exists' => $exists,
                    'message' => "El folio existe en el sistema."
                ]);
            } else {
                return response()->json([
                    'exists' => $exists,
                    'message' => "El folio existe en el sistema, pero no corresponde a la sede que quiere realizar la cita."
                ]);
            }
        } else {
            return response()->json([
                'exists' => $exists,
                'message' => "El folio no existe en el sistema, verifica que sea correcto."
            ]);
        }
    }




    public function getTotalAudiencias(Request $request)
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
                    'Folio audiencia' => $item->audiencia,
                    'Folio solicitud' => $item->folio_solicitud,
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

        public function getTotalAudienciasCount(Request $request)
        {
            // Obtener la fecha del mes de febrero
            $fechaInicio = "2025-02-01"; // Primer día de febrero
            $fechaFin = "2025-02-28"; // Último día de febrero
    
            $audienciasCount = Audiencia::conciliacionAudiencias($fechaInicio, $fechaFin)->get();

            $result = $audienciasCount->map(function ($item) {
            });

            $count = $result->count();

            return response()->json(['total_audiencias' => $count]);
        }

    public function datosSolicitud(Request $request, string $folio, int $anio): JsonResponse
    {
        // Validar token de API
        $token = $request->header('Authorization');
        $expectedToken = 'Bearer ' . config('app.api_token');
        
        if ($token !== $expectedToken) {
            return response()->json([
                'message' => 'Token de autorización inválido'
            ], 401);
        }

        // 3) Ejecutar la consulta, filtrando por folio, año y centro
        $registro = DB::table('solicitudes as s')
            ->selectRaw("
                CONCAT(s.folio, '/', s.anio) AS folio_solicitud,
                CONCAT_WS(' ',
                    ps.nombre, ps.primer_apellido, ps.segundo_apellido
                ) AS nombre_parte1,
                s.centro_id,
                ps.curp AS curp_parte1,
                ps.rfc AS rfc_parte1,
                ps.nombre_comercial AS nombre_comercial_parte1,
                co.contacto AS contacto_parte1,
                pc.nombre_comercial AS nombre_comercial_parte2
            ")
            ->leftJoin('partes as ps', function($j){
                $j->on('ps.solicitud_id','s.id')
                  ->where('ps.tipo_parte_id', 1);
            })
            ->leftJoin('contactos as co','co.contactable_id','ps.id')
            ->leftJoin('partes as pc', function($j){
                $j->on('pc.solicitud_id','s.id')
                  ->where('pc.tipo_parte_id', 2);
            })
            ->where('s.folio',     $folio)
            ->where('s.anio',      $anio)
            ->orderByDesc('co.id')
            ->limit(1)
            ->first();

        // 4) Si no existe, devolvemos 404
        if (! $registro) {
            return response()->json([
                'message' => "No se encontró la solicitud {$folio}/{$anio} "
            ], 404);
        }

        // 5) Devolvemos el registro en JSON
        return response()->json($registro);
    }

}
