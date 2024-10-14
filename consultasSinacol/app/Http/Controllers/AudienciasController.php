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

    public function getQuery(Request $request){

        $desde = $request->input('desde');
    // Validar la entrada 'desde' según sea necesario

    $sql = <<<'SQL'
    SELECT s.id AS solicitud_id,
           p.id AS parte_id,
           a.id AS audiencia_id,
           es.nombre AS estatus_solicitud,
           a.finalizada AS audiencia_finalizada,
           s.inmediata,
           p.tipo_parte_id,
           p.tipo_persona_id,
           UPPER(pe.nombre || ' ' || pe.primer_apellido || ' ' || pe.segundo_apellido) AS usuario_captura_solicitud,
           s.created_at::date AS fecha_solicitud,
           s.captura_user_id,
           (DATE_PART('hour', s.created_at) || ':' || DATE_PART('minute', s.created_at) || ':' ||
            DATE_PART('seconds', s.created_at))::time AS hora_solicitud,
           a.fecha_audiencia,
           a.hora_inicio AS hora_inicio_audiencia,
           c.abreviatura AS centro,
           g.nombre AS genero,
           p.edad,
           s.ratificada AS confirmada,
           s.fecha_ratificacion::date AS fecha_confirmacion,
           (DATE_PART('hour', s.fecha_ratificacion) || ':' || DATE_PART('minute', s.fecha_ratificacion) || ':' ||
            DATE_PART('seconds', s.fecha_ratificacion))::time AS hora_confirmacion,
           CASE WHEN s.ratificada = true THEN 1 ELSE 0 END AS confirmacion_positiva,
           CASE WHEN s.ratificada = false THEN 1 ELSE 0 END AS confirmacion_negativa,
           s.tipo_solicitud_id,
           ts.nombre AS tipo_solicitud,
           CASE WHEN s.tipo_solicitud_id = 1 THEN 1 ELSE 0 END AS solicitud_trabajador,
           CASE WHEN s.tipo_solicitud_id = 2 THEN 1 ELSE 0 END AS solicitud_patron_individual,
           CASE WHEN s.tipo_solicitud_id = 3 THEN 1 ELSE 0 END AS solicitud_patron_colectivo,
           tis.nombre AS incidencia,
           s.fecha_incidencia,
           CASE WHEN tis.id = 4 THEN 1 ELSE 0 END AS incompetencia_solicitud,
           CASE WHEN tis.id = 6 THEN 1 ELSE 0 END AS incompetencia_audiencia,
           CASE WHEN (tis.id = 4 OR tis.id = 6) THEN 1 ELSE 0 END AS incompetencia,
           CASE WHEN tis.id = 7 THEN 1 ELSE 0 END AS no_admision_7motr,
           CASE WHEN r.id = 1 THEN 1 ELSE 0 END AS hubo_convenio,
           CASE WHEN r.id = 2 THEN 1 ELSE 0 END AS reagendada,
           CASE WHEN r.id = 3 THEN 1 ELSE 0 END AS no_hubo_convenio,
           CASE WHEN r.id = 4 THEN 1 ELSE 0 END AS archivado,
           CASE
               WHEN (a.id IS NOT NULL) THEN
                   (SELECT COUNT(*) FROM resolucion_partes AS rp
                    WHERE rp.audiencia_id = a.id
                      AND rp.parte_solicitante_id = p.id
                      AND rp.deleted_at IS NULL
                      AND rp.terminacion_bilateral_id = 1)
               ELSE 0
           END AS num_tb_archivados,
           -- Continúa con el resto de los campos...
           -- [Aquí incluyes todos los campos que ya tienes en tu consulta original]
    FROM partes AS p
         LEFT JOIN generos g ON p.genero_id = g.id
         JOIN solicitudes s ON p.solicitud_id = s.id
         JOIN estatus_solicitudes es ON s.estatus_solicitud_id = es.id
         JOIN tipo_solicitudes ts ON ts.id = s.tipo_solicitud_id
         JOIN centros AS c ON s.centro_id = c.id
         LEFT JOIN users AS u ON s.captura_user_id = u.id
         LEFT JOIN personas AS pe ON pe.id = u.persona_id
         LEFT JOIN expedientes e ON e.solicitud_id = s.id AND e.deleted_at IS NULL AND s.deleted_at IS NULL
         LEFT JOIN audiencias a ON a.expediente_id = e.id AND a.deleted_at IS NULL AND e.deleted_at IS NULL
         LEFT JOIN resoluciones r ON r.id = a.resolucion_id AND a.deleted_at IS NULL
         LEFT JOIN tipo_incidencia_solicitudes AS tis ON s.tipo_incidencia_solicitud_id = tis.id
         LEFT JOIN domicilios d ON d.domiciliable_id = p.id
            AND d.domiciliable_type = 'App\\Parte'
            AND d.deleted_at IS NULL
            AND p.deleted_at IS NULL
    WHERE p.deleted_at IS NULL
      AND s.tipo_solicitud_id = 1
      AND p.tipo_parte_id = 1
      AND s.deleted_at IS NULL
      AND s.created_at::date >= :desde

    UNION

    SELECT s.id AS solicitud_id,
           p.id AS parte_id,
           a.id AS audiencia_id,
           es.nombre AS estatus_solicitud,
           a.finalizada AS audiencia_finalizada,
           s.inmediata,
           p.tipo_parte_id,
           p.tipo_persona_id,
           UPPER(pe.nombre || ' ' || pe.primer_apellido || ' ' || pe.segundo_apellido) AS usuario_captura_solicitud,
           s.created_at::date AS fecha_solicitud,
           s.captura_user_id,
           (DATE_PART('hour', s.created_at) || ':' || DATE_PART('minute', s.created_at) || ':' ||
            DATE_PART('seconds', s.created_at))::time AS hora_solicitud,
           a.fecha_audiencia,
           a.hora_inicio AS hora_inicio_audiencia,
           c.abreviatura AS centro,
           g.nombre AS genero,
           p.edad,
           s.ratificada AS confirmada,
           s.fecha_ratificacion::date AS fecha_confirmacion,
           (DATE_PART('hour', s.fecha_ratificacion) || ':' || DATE_PART('minute', s.fecha_ratificacion) || ':' ||
            DATE_PART('seconds', s.fecha_ratificacion))::time AS hora_confirmacion,
           CASE WHEN s.ratificada = true THEN 1 ELSE 0 END AS confirmacion_positiva,
           CASE WHEN s.ratificada = false THEN 1 ELSE 0 END AS confirmacion_negativa,
           s.tipo_solicitud_id,
           ts.nombre AS tipo_solicitud,
           CASE WHEN s.tipo_solicitud_id = 1 THEN 1 ELSE 0 END AS solicitud_trabajador,
           CASE WHEN s.tipo_solicitud_id = 2 THEN 1 ELSE 0 END AS solicitud_patron_individual,
           CASE WHEN s.tipo_solicitud_id = 3 THEN 1 ELSE 0 END AS solicitud_patron_colectivo,
           tis.nombre AS incidencia,
           s.fecha_incidencia,
           CASE WHEN tis.id = 4 THEN 1 ELSE 0 END AS incompetencia_solicitud,
           CASE WHEN tis.id = 6 THEN 1 ELSE 0 END AS incompetencia_audiencia,
           CASE WHEN (tis.id = 4 OR tis.id = 6) THEN 1 ELSE 0 END AS incompetencia,
           CASE WHEN tis.id = 7 THEN 1 ELSE 0 END AS no_admision_7motr,
           CASE WHEN r.id = 1 THEN 1 ELSE 0 END AS hubo_convenio,
           CASE WHEN r.id = 2 THEN 1 ELSE 0 END AS reagendada,
           CASE WHEN r.id = 3 THEN 1 ELSE 0 END AS no_hubo_convenio,
           CASE WHEN r.id = 4 THEN 1 ELSE 0 END AS archivado,
           CASE
               WHEN (a.id IS NOT NULL) THEN
                   (SELECT COUNT(*) FROM resolucion_partes AS rp
                    WHERE rp.audiencia_id = a.id
                      AND rp.parte_solicitada_id = p.id
                      AND rp.deleted_at IS NULL
                      AND rp.terminacion_bilateral_id = 1)
               ELSE 0
           END AS num_tb_archivados,
           -- Continúa con el resto de los campos...
           -- [Aquí incluyes todos los campos que ya tienes en tu consulta original para la segunda parte]
    FROM partes AS p
         LEFT JOIN generos g ON p.genero_id = g.id
         JOIN solicitudes s ON p.solicitud_id = s.id
         JOIN estatus_solicitudes es ON s.estatus_solicitud_id = es.id
         JOIN tipo_solicitudes ts ON ts.id = s.tipo_solicitud_id
         JOIN centros AS c ON s.centro_id = c.id
         LEFT JOIN users AS u ON s.captura_user_id = u.id
         LEFT JOIN personas AS pe ON pe.id = u.persona_id
         LEFT JOIN expedientes e ON e.solicitud_id = s.id AND e.deleted_at IS NULL AND s.deleted_at IS NULL
         LEFT JOIN audiencias a ON a.expediente_id = e.id AND a.deleted_at IS NULL AND e.deleted_at IS NULL
         LEFT JOIN resoluciones r ON r.id = a.resolucion_id AND a.deleted_at IS NULL
         LEFT JOIN tipo_incidencia_solicitudes AS tis ON s.tipo_incidencia_solicitud_id = tis.id
         LEFT JOIN domicilios d ON d.domiciliable_id = p.id
            AND d.domiciliable_type = 'App\\Parte'
            AND d.deleted_at IS NULL
            AND p.deleted_at IS NULL
    WHERE p.deleted_at IS NULL
      AND s.tipo_solicitud_id IN (2, 3)
      AND p.tipo_parte_id = 2
      AND s.deleted_at IS NULL
      AND s.created_at::date >= :desde
    SQL;

    // Ejecutar la consulta
    $results = DB::select($sql, ['desde' => $desde]);

    // Convertir los resultados a un array
    $resultsArray = json_decode(json_encode($results), true);

    // Crear el archivo CSV
    $filename = 'reporte.csv';
    $file = fopen(storage_path('app/' . $filename), 'w');

    if (!empty($resultsArray)) {
        // Escribir la cabecera
        fputcsv($file, array_keys($resultsArray[0]));

        // Escribir los datos
        foreach ($resultsArray as $row) {
            fputcsv($file, $row);
        }
    }

    fclose($file);

    // Retornar el archivo como descarga
    return response()->download(storage_path('app/' . $filename));
    }
}
