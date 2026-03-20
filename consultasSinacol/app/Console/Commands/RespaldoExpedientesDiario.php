<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\RespaldoExpediente;
use Carbon\Carbon;

class RespaldoExpedientesDiario extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'respaldo:expedientes {--all : Sincronizar absolutamente todo el histórico sin filtro de fecha}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Extrae y respalda los expedientes nuevos y modificados de las últimas 24 horas';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info("Iniciando el respaldo de expedientes...");

        // Fecha de corte (últimas 24 horas). Si se pasa el flag --all, no consideramos fecha.
        $syncAll = $this->option('all');
        $fechaCorte = Carbon::now()->subHours(25)->toDateTimeString(); 

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
                (SELECT STRING_AGG(DISTINCT TRIM(UPPER(CONCAT(p.nombre, ' ', p.primer_apellido, ' ', p.segundo_apellido))), ' | ')
                 FROM partes p 
                 WHERE p.solicitud_id = s.id 
                   AND (
                       (s.tipo_solicitud_id IN (1, 4) AND p.tipo_parte_id = 1) 
                       OR (s.tipo_solicitud_id IN (2, 3) AND p.tipo_parte_id = 2)
                   )
                ) AS "nombre_trabajador",
                (SELECT STRING_AGG(DISTINCT TRIM(UPPER(COALESCE(p.nombre_comercial, CONCAT(p.nombre, ' ', p.primer_apellido, ' ', p.segundo_apellido)))), ' | ')
                 FROM partes p 
                 WHERE p.solicitud_id = s.id 
                   AND (
                       (s.tipo_solicitud_id IN (1, 4) AND p.tipo_parte_id = 2) 
                       OR (s.tipo_solicitud_id IN (2, 3) AND p.tipo_parte_id = 1)
                   )
                ) AS "nombre_empresa",
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
                LEFT JOIN audiencias a ON a.id = (
                    SELECT id FROM audiencias 
                    WHERE expediente_id = e.id 
                    ORDER BY created_at DESC LIMIT 1
                )
                LEFT JOIN resoluciones r ON a.resolucion_id = r.id
                LEFT JOIN conciliadores_audiencias ca ON a.id = ca.audiencia_id
                LEFT JOIN conciliadores c ON ca.conciliador_id = c.id
                LEFT JOIN personas c_per ON c.persona_id = c_per.id
                LEFT JOIN users u ON s.captura_user_id = u.id
                LEFT JOIN personas u_per ON u.persona_id = u_per.id

            WHERE 
                e.deleted_at IS NULL 
                AND s.deleted_at IS NULL
SQL;

        $params = [];
        if (!$syncAll) {
            // Filtramos expedientes que hayan tenido modificaciones recientes en sus entidades principales
            $sql .= <<<SQL
                AND (
                    e.created_at >= :c1 OR e.updated_at >= :u1 OR
                    s.created_at >= :c2 OR s.updated_at >= :u2 OR
                    a.created_at >= :c3 OR a.updated_at >= :u3
                )
SQL;
            $params = [
                'c1' => $fechaCorte, 'u1' => $fechaCorte,
                'c2' => $fechaCorte, 'u2' => $fechaCorte,
                'c3' => $fechaCorte, 'u3' => $fechaCorte,
            ];
            $this->info("Filtrando cambios a partir de: {$fechaCorte}");
        } else {
            $this->info("El flag --all se especificó. Se sincronizará TODO el historial (esto puede demorar).");
        }

        $data = DB::select($sql, $params);
        $this->info("Se encontraron " . count($data) . " registros. Sincronizando (insert/update)...");

        // Preparar lotes para Upsert (Insert si no existe, Update si existe)
        $upsertData = [];
        foreach ($data as $row) {
            // Usamos numero_expediente como llave del arreglo para eliminar duplicados
            // Si el query lanza varias filas por expediente (ej: multiples conciliadores), nos quedaremos con la última
            $upsertData[$row->numero_expediente] = (array) $row;
        }

        // Convertimos de nuevo a lista indexada numéricamente
        $upsertData = array_values($upsertData);

        // Agrupamos en lotes de 500 para evitar agotar memoria con inserts gigantes
        $chunks = array_chunk($upsertData, 500);

        foreach ($chunks as $chunk) {
            RespaldoExpediente::upsert(
                $chunk,
                ['numero_expediente'], // Claves únicas para identificar el registro
                [
                    'fecha_apertura', 'fecha_cierre', 'tipo_tramite', 'tipo_solicitud', 
                    'nombre_trabajador', 'nombre_empresa', 'resultado_audiencia', 
                    'asesor_atendio', 'conciliador_atendio'
                ] // Columnas a actualizar si ya existe
            );
        }

        $this->info("¡Respaldo sincronizado con éxito!");
        return 0;
    }
}
