<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReporteController extends Controller
{
    public function expedientesPorFecha(Request $request)
    {
        // 1. Validamos los datos de entrada
        $request->validate([
            'fecha_inicio'       => 'nullable|date',
            'fecha_fin'          => 'nullable|date|after_or_equal:fecha_inicio',
            'numero_expediente'  => 'nullable|string',
            'folio_solicitud'    => 'nullable|integer|required_with:anio',
            'anio'               => 'nullable|integer|required_with:folio_solicitud',
        ]);

        // Validar que al menos uno de los criterios esté presente
        if (!$request->filled('numero_expediente')
            && !$request->filled('folio_solicitud')
            && !$request->filled('anio')
            && (!$request->filled('fecha_inicio') || !$request->filled('fecha_fin'))) {
            return response()->json([
                'success' => false,
                'message' => 'Debes proporcionar número de expediente, folio de solicitud, año o bien fecha_inicio y fecha_fin'
            ], 400, [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        $fechaInicio        = $request->input('fecha_inicio');
        $fechaFin           = $request->input('fecha_fin');
        $numeroExpediente   = $request->input('numero_expediente');
        $folioSolicitud     = $request->input('folio_solicitud');
        $anio               = $request->input('anio');

        if (($request->filled('folio_solicitud') && !$request->filled('anio'))
            || (!$request->filled('folio_solicitud') && $request->filled('anio'))) {
            return response()->json([
                'success' => false,
                'message' => 'Para buscar por solicitud debes enviar ambos parámetros: folio_solicitud y anio'
            ], 400, [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        // 2. Preparamos el SQL
        // Nota: He reemplazado la parte del "WITH parametros" por variables directas (:variable)
        // para usar el sistema de seguridad de Laravel.
        $sql = <<<SQL
            SELECT 
                e.folio AS "numero_expediente",
                
                TO_CHAR(s.fecha_ratificacion, 'YYYY-MM-DD') AS "fecha_apertura",
                
                CASE 
                    WHEN a.finalizada = true THEN TO_CHAR(a.fecha_audiencia, 'YYYY-MM-DD')
                    ELSE 'En Proceso' 
                END AS "fecha_cierre",

                CASE
                    WHEN s.inmediata = true THEN 'Ratificación de Convenio'
                    ELSE 'Audiencia de Conciliación'
                END AS "tipo_tramite",

                CASE 
                    WHEN s.tipo_solicitud_id = 1 THEN 'Trabajador'
                    WHEN s.tipo_solicitud_id = 2 THEN 'Patron individual'
                    WHEN s.tipo_solicitud_id = 3 THEN 'Patron colectiva'
                    WHEN s.tipo_solicitud_id = 4 THEN 'Sindicato'
                    ELSE ts.nombre 
                END AS "tipo_solicitud",

                -- Nombre del trabajador
                (SELECT STRING_AGG(DISTINCT TRIM(UPPER(CONCAT(p.nombre, ' ', p.primer_apellido, ' ', p.segundo_apellido))), ' | ')
                 FROM partes p 
                 WHERE p.solicitud_id = s.id 
                   AND (
                       (s.tipo_solicitud_id IN (1, 4) AND p.tipo_parte_id = 1) 
                       OR 
                       (s.tipo_solicitud_id IN (2, 3) AND p.tipo_parte_id = 2)
                   )
                ) AS "nombre_trabajador",

                -- Nombre de la empresa
                (SELECT STRING_AGG(DISTINCT TRIM(UPPER(COALESCE(p.nombre_comercial, CONCAT(p.nombre, ' ', p.primer_apellido, ' ', p.segundo_apellido)))), ' | ')
                 FROM partes p 
                 WHERE p.solicitud_id = s.id 
                   AND (
                       (s.tipo_solicitud_id IN (1, 4) AND p.tipo_parte_id = 2) 
                       OR 
                       (s.tipo_solicitud_id IN (2, 3) AND p.tipo_parte_id = 1)
                   )
                ) AS "nombre_empresa",

                -- Resultado de audiencia
                CASE 
                    WHEN r.id = 1 THEN 'Hubo convenio'
                    WHEN r.id = 2 THEN 'No hubo convenio, pero se desea realizar una nueva audiencia'
                    WHEN r.id = 3 THEN 'No hubo convenio'
                    WHEN r.id = 4 THEN 'Archivado'
                    ELSE COALESCE(r.nombre, 'Sin Resultado / Pendiente') 
                END AS "resultado_audiencia",

                TRIM(UPPER(CONCAT(u_per.nombre, ' ', u_per.primer_apellido, ' ', u_per.segundo_apellido))) AS "asesor_atendio",

                TRIM(UPPER(CONCAT(c_per.nombre, ' ', c_per.primer_apellido, ' ', c_per.segundo_apellido))) AS "conciliador_atendio"

            FROM expedientes e
                INNER JOIN solicitudes s ON e.solicitud_id = s.id
                LEFT JOIN tipo_solicitudes ts ON s.tipo_solicitud_id = ts.id
                
                -- Audiencias (Última)
                LEFT JOIN audiencias a ON a.id = (
                    SELECT id FROM audiencias 
                    WHERE expediente_id = e.id 
                    ORDER BY created_at DESC LIMIT 1
                )
                
                -- Resoluciones
                LEFT JOIN resoluciones r ON a.resolucion_id = r.id

                -- Conciliadores y Personas
                LEFT JOIN conciliadores_audiencias ca ON a.id = ca.audiencia_id
                LEFT JOIN conciliadores c ON ca.conciliador_id = c.id
                LEFT JOIN personas c_per ON c.persona_id = c_per.id
                LEFT JOIN users u ON s.captura_user_id = u.id
                LEFT JOIN personas u_per ON u.persona_id = u_per.id

            WHERE 
                e.deleted_at IS NULL 
                AND s.deleted_at IS NULL
SQL;

        // Construir las condiciones dinámicamente
        $conditions = [];
        $params = [];

        if ($numeroExpediente) {
            $conditions[] = "e.folio = :numero_expediente";
            $params['numero_expediente'] = $numeroExpediente;
        }

        if ($folioSolicitud && $anio) {
            $conditions[] = "s.folio = :folio_solicitud";
            $params['folio_solicitud'] = $folioSolicitud;
            $conditions[] = "s.anio = :anio";
            $params['anio'] = $anio;
        }

        if ($fechaInicio && $fechaFin) {
            $conditions[] = "s.fecha_ratificacion::date BETWEEN :fecha_inicio AND :fecha_fin";
            $params['fecha_inicio'] = $fechaInicio;
            $params['fecha_fin'] = $fechaFin;
        }

        // Agregar las condiciones al SQL
        if (!empty($conditions)) {
            $sql .= " AND " . implode(" AND ", $conditions);
        }

        // 3. Ejecutamos la consulta
        try {
            $data = DB::select($sql, $params);

            return response()->json([
                'success' => true,
                'count'   => count($data),
                'data'    => $data
            ], 200, [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener el reporte: ' . $e->getMessage()
            ], 500, [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
    }
}