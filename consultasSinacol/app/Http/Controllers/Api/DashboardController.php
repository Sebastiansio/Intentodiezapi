<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Conciliador;
use App\Audiencia;
use App\Centro;
use Carbon\Carbon;

class DashboardController extends Controller
{
    private const CENTROS_PERMITIDOS_DEFAULT = [38, 39, 40, 41, 42, 43, 44, 46, 47, 48];

    /**
     * Devuelve todos los centros disponibles para que el front configure filtros.
     */
    public function getCentros()
    {
        $centros = Centro::select('id', 'nombre')
            ->orderBy('nombre', 'asc')
            ->get();

        return response()->json([
            'data' => $centros,
            'default_centros' => self::CENTROS_PERMITIDOS_DEFAULT
        ]);
    }

    /**
     * Lista todos los conciliadores disponibles para que el front configure filtros.
     * Opcionalmente filtra por centros solicitados.
     */
    public function getListaConciliadores(Request $request)
    {
        $centrosPermitidos = $this->getCentrosPermitidos($request);

        $conciliadores = Conciliador::with(['persona', 'centro'])
            ->whereIn('centro_id', $centrosPermitidos)
            ->orderBy('centro_id')
            ->get()
            ->map(function($c) {
                $nombre = $c->persona 
                          ? trim($c->persona->nombre . ' ' . $c->persona->primer_apellido . ' ' . $c->persona->segundo_apellido)
                          : 'Sin nombre';
                return [
                    'id' => $c->id,
                    'nombre' => $nombre,
                    'centro_id' => $c->centro_id,
                    'centro_nombre' => $c->centro ? $c->centro->nombre : 'Sin centro'
                ];
            });

        // Obtener configuración actual si existe
        $configuracion = \App\Configuracion::where('clave', 'conciliadores_activos')->first();
        $conciliadoresActivos = $configuracion && $configuracion->valor
            ? json_decode($configuracion->valor, true)
            : [];

        return response()->json([
            'data' => $conciliadores,
            'configuracion_actual' => $conciliadoresActivos,
            'total_disponibles' => $conciliadores->count()
        ]);
    }

    /**
     * Guarda la configuración de conciliadores activos.
     */
    public function guardarConfiguracionConciliadores(Request $request)
    {
        $conciliadoresIds = $request->input('conciliadores', []);

        if (!is_array($conciliadoresIds)) {
            return response()->json(['error' => 'conciliadores debe ser un arreglo'], 400);
        }

        // Validar que los IDs existan
        $conciliadoresIds = array_values(array_filter(array_map(function ($id) {
            return (int) trim((string) $id);
        }, $conciliadoresIds), function ($id) {
            return $id > 0;
        }));

        if (empty($conciliadoresIds)) {
            return response()->json(['error' => 'Debe proporcionar al menos un conciliador'], 400);
        }

        $conciliadoresValidos = Conciliador::whereIn('id', $conciliadoresIds)
            ->pluck('id')
            ->map(function ($id) {
                return (int) $id;
            })
            ->toArray();

        if (count($conciliadoresValidos) !== count($conciliadoresIds)) {
            return response()->json([
                'error' => 'Algunos conciliadores no existen',
                'válidos' => $conciliadoresValidos,
                'solicitados' => $conciliadoresIds
            ], 400);
        }

        $configuracion = \App\Configuracion::updateOrCreate(
            ['clave' => 'conciliadores_activos'],
            ['valor' => json_encode($conciliadoresValidos)]
        );

        return response()->json([
            'mensaje' => 'Configuración guardada exitosamente',
            'conciliadores_activos' => $conciliadoresValidos
        ]);
    }

    /**
     * Obtiene la configuración actual de conciliadores activos.
     */
    public function obtenerConfiguracionConciliadores()
    {
        $configuracion = \App\Configuracion::where('clave', 'conciliadores_activos')->first();
        $conciliadoresActivos = $configuracion && $configuracion->valor
            ? json_decode($configuracion->valor, true)
            : [];

        $conciliadoresData = [];
        if (!empty($conciliadoresActivos)) {
            $conciliadoresData = Conciliador::with(['persona', 'centro'])
                ->whereIn('id', $conciliadoresActivos)
                ->get()
                ->map(function($c) {
                    $nombre = $c->persona 
                              ? trim($c->persona->nombre . ' ' . $c->persona->primer_apellido . ' ' . $c->persona->segundo_apellido)
                              : 'Sin nombre';
                    return [
                        'id' => $c->id,
                        'nombre' => $nombre,
                        'centro_id' => $c->centro_id,
                        'centro_nombre' => $c->centro ? $c->centro->nombre : 'Sin centro'
                    ];
                })->toArray();
        }

        return response()->json([
            'conciliadores_ids' => $conciliadoresActivos,
            'conciliadores_detallado' => $conciliadoresData,
            'total_configurados' => count($conciliadoresActivos)
        ]);
    }

    /**
     * Obtiene los conciliadores activos según configuración o retorna vacío si no hay.
     * Si hay configuración, retorna solo esos. Si no hay, retorna null.
     */
    private function getConciliadoresActivos()
    {
        $configuracion = \App\Configuracion::where('clave', 'conciliadores_activos')->first();
        
        if (!$configuracion || !$configuracion->valor) {
            return null; // Sin configuración, usar lógica original
        }

        $conciliadoresActivos = json_decode($configuracion->valor, true);
        return !empty($conciliadoresActivos) ? $conciliadoresActivos : null;
    }

    /**
     * Resuelve los centros a usar en base a la configuración enviada por query.
     * Acepta centros=1,2,3 o centros[]=1&centros[]=2. Mantiene compatibilidad con centro_id.
     */
    private function getCentrosPermitidos(Request $request)
    {
        $centrosRequest = $request->query('centros');

        if ($centrosRequest === null || $centrosRequest === '') {
            $centroIdRequest = $request->query('centro_id');
            if ($centroIdRequest !== null && $centroIdRequest !== '') {
                $centrosRequest = [$centroIdRequest];
            }
        }

        if ($centrosRequest === null || $centrosRequest === '') {
            return self::CENTROS_PERMITIDOS_DEFAULT;
        }

        if (is_string($centrosRequest)) {
            $centrosRequest = explode(',', $centrosRequest);
        }

        if (!is_array($centrosRequest)) {
            return self::CENTROS_PERMITIDOS_DEFAULT;
        }

        $centrosRequest = array_values(array_filter(array_map(function ($centroId) {
            return (int) trim((string) $centroId);
        }, $centrosRequest), function ($centroId) {
            return $centroId > 0;
        }));

        if (empty($centrosRequest)) {
            return self::CENTROS_PERMITIDOS_DEFAULT;
        }

        $centrosValidos = Centro::whereIn('id', $centrosRequest)
            ->pluck('id')
            ->map(function ($id) {
                return (int) $id;
            })
            ->toArray();

        return !empty($centrosValidos)
            ? $centrosValidos
            : self::CENTROS_PERMITIDOS_DEFAULT;
    }

    /**
     * Obtiene todos los conciliadores con su nombre.
     * Si hay conciliadores configurados en la BD, retorna solo esos.
     * Si no, retorna todos los del centro permitido.
     */
    public function getConciliadores(Request $request)
    {
        $centrosPermitidos = $this->getCentrosPermitidos($request);

        // Verificar si hay configuración de conciliadores activos
        $conciliadoresActivos = $this->getConciliadoresActivos();

        $query = Conciliador::with(['persona', 'centro']);

        if ($conciliadoresActivos !== null) {
            // Usar conciliadores configurados
            $query->whereIn('id', $conciliadoresActivos);
        } else {
            // Usar todos los de los centros permitidos
            $query->whereIn('centro_id', $centrosPermitidos);
        }

        $conciliadores = $query->get()
            ->map(function($c) {
                $nombre = $c->persona 
                          ? trim($c->persona->nombre . ' ' . $c->persona->primer_apellido . ' ' . $c->persona->segundo_apellido)
                          : 'Sin nombre';
                return [
                    'id' => $c->id,
                    'nombre' => $nombre,
                    'centro_id' => $c->centro_id,
                    'centro_nombre' => $c->centro ? $c->centro->nombre : 'Sin centro'
                ];
            });

        return response()->json($conciliadores);
    }

    /**
     * Obtiene las audiencias de un conciliador en la semana actual (o según fechas).
     */
    public function getAudiencias(Request $request, $id)
    {
        $centrosPermitidos = $this->getCentrosPermitidos($request);
        $conciliadoresActivos = $this->getConciliadoresActivos();

        // Validación del conciliador
        if ($conciliadoresActivos !== null) {
            // Si hay configuración, validar que sea un conciliador activo
            $conciliadorValido = Conciliador::whereIn('id', $conciliadoresActivos)->find($id);
        } else {
            // Si no hay configuración, validar que pertenezca al centro
            $conciliadorValido = Conciliador::whereIn('centro_id', $centrosPermitidos)->find($id);
        }

        if (!$conciliadorValido) {
            return response()->json(['error' => 'Conciliador no encontrado o no tiene permisos'], 404);
        }

        $fechaInicio = $request->query('fecha_inicio', Carbon::now()->startOfWeek()->toDateString());
        $fechaFin = $request->query('fecha_fin', Carbon::now()->endOfWeek()->toDateString());
        
        $incluirInmediatas = $request->query('incluir_inmediatas', 'true');

        $query = Audiencia::whereBetween('fecha_audiencia', [$fechaInicio, $fechaFin])
            ->where(function($query) use ($id) {
                $query->where('conciliador_id', $id)
                      ->orWhereHas('conciliadoresAudiencias', function($q) use ($id) {
                          $q->where('conciliador_id', $id);
                      });
            });

        if ($incluirInmediatas === 'false' || $incluirInmediatas === '0' || $incluirInmediatas === false) {
            $query->whereHas('expediente.solicitud', function ($q) {
                $q->where('inmediata', false);
            });
        }

        $audiencias = $query->select('id', 'expediente_id', 'resolucion_id', 'fecha_audiencia', 'hora_inicio', 'hora_fin', 'etapa_notificacion_id')
            ->with(['expediente:id,folio,anio,solicitud_id', 'expediente.solicitud:id,inmediata', 'salasAudiencias.sala:id,sala', 'resolucion:id,nombre', 'etapa_notificacion', 'audienciaParte.tipo_notificacion', 'audienciaParte.parte'])
            ->orderBy('fecha_audiencia')
            ->orderBy('hora_inicio')
            ->get();

        $total = $audiencias->count();

        $audienciasTransformadas = $audiencias->map(function($a) {
            $sala = $a->salasAudiencias && $a->salasAudiencias->count() > 0 
                    ? $a->salasAudiencias->first()->sala->sala 
                    : 'Sin sala asignada';
                
                $notificacionesPartes = [];
                if ($a->audienciaParte) {
                    $notificacionesPartes = $a->audienciaParte->map(function ($ap) {
                        return [
                            'parte_id' => $ap->parte_id,
                            'tipo_parte' => $ap->parte ? $ap->parte->tipo_parte_id : null,
                            'tipo_notificacion' => $ap->tipo_notificacion ? $ap->tipo_notificacion->nombre : 'Sin tipo',
                            'fecha_notificacion' => $ap->fecha_notificacion,
                            'estatus_notificacion' => $ap->finalizado ?: 'Pendiente',
                            'detalle_notificacion' => $ap->detalle,
                            'multa' => (bool) $ap->multa
                        ];
                    })->toArray();
                }

                return [
                    'id' => $a->id,
                    'inmediata' => $a->expediente && $a->expediente->solicitud ? (bool) $a->expediente->solicitud->inmediata : false,
                    'expediente' => $a->expediente ? $a->expediente->folio : 'Sin expediente',
                    'anio' => $a->expediente ? $a->expediente->anio : '',
                    'fecha' => $a->fecha_audiencia,
                    'hora_inicio' => $a->hora_inicio,
                    'hora_fin' => $a->hora_fin,
                    'sala' => $sala,
                    'estado_audiencia_id' => null, // La columna estado_audiencia_id no existe en la base de datos
                    'resolucion_id' => $a->resolucion_id,
                    'resolucion_nombre' => $a->resolucion ? $a->resolucion->nombre : 'Sin resolución',
                    'etapa_notificacion' => $a->etapa_notificacion ? $a->etapa_notificacion->etapa : 'N/A',
                    'notificaciones_partes' => $notificacionesPartes
                ];
            });

        $respuesta = [
            'total' => $total,
            'data' => $audienciasTransformadas
        ];

        // Usamos JSON_UNESCAPED_SLASHES para evitar que las barras / en el expediente se escapen con \
        return response()->json($respuesta, 200, [], JSON_UNESCAPED_SLASHES);
    }

    /**
     * Obtiene las estadísticas de efectividad de las resoluciones de un conciliador.
     */
    public function getEstadisticas(Request $request, $id)
    {
        $centrosPermitidos = $this->getCentrosPermitidos($request);
        $conciliadoresActivos = $this->getConciliadoresActivos();

        if ($id !== 'todos' && $id != 0) {
            if ($conciliadoresActivos !== null) {
                // Validar que sea un conciliador activo
                $conciliadorValido = Conciliador::whereIn('id', $conciliadoresActivos)->find($id);
            } else {
                // Validar que pertenezca al centro
                $conciliadorValido = Conciliador::whereIn('centro_id', $centrosPermitidos)->find($id);
            }
            
            if (!$conciliadorValido) {
                return response()->json(['error' => 'Conciliador no encontrado o no tiene permisos'], 404);
            }
        }

        $fechaInicio = $request->query('fecha_inicio', Carbon::now()->startOfWeek()->toDateString());
        $fechaFin = $request->query('fecha_fin', Carbon::now()->endOfWeek()->toDateString());
        
        $incluirInmediatas = $request->query('incluir_inmediatas', 'true');

        // Traemos las audiencias para contabilizarlas
        $audienciasQuery = Audiencia::whereBetween('fecha_audiencia', [$fechaInicio, $fechaFin]);

        if ($id !== 'todos' && $id != 0) {
            $audienciasQuery->where(function($query) use ($id) {
                $query->where('conciliador_id', $id)
                      ->orWhereHas('conciliadoresAudiencias', function($q) use ($id) {
                          $q->where('conciliador_id', $id);
                      });
            });
        } else {
            // Para 'todos', usar conciliadores configurados si existen, si no usar todos del centro
            if ($conciliadoresActivos !== null) {
                $conciliadoresIdsValidos = $conciliadoresActivos;
            } else {
                $conciliadoresIdsValidos = Conciliador::whereIn('centro_id', $centrosPermitidos)->pluck('id')->toArray();
            }
            
            $audienciasQuery->where(function($query) use ($conciliadoresIdsValidos) {
                $query->whereIn('conciliador_id', $conciliadoresIdsValidos)
                      ->orWhereHas('conciliadoresAudiencias', function($q) use ($conciliadoresIdsValidos) {
                          $q->whereIn('conciliador_id', $conciliadoresIdsValidos);
                      });
            });
        }

        if ($incluirInmediatas === 'false' || $incluirInmediatas === '0' || $incluirInmediatas === false) {
            $audienciasQuery->whereHas('expediente.solicitud', function ($q) {
                $q->where('inmediata', false);
            });
        }

        $audiencias = $audienciasQuery->select('id', 'resolucion_id', 'expediente_id')
            ->with(['audienciaParte' => function($q) {
                $q->select('id', 'audiencia_id', 'finalizado');
            }, 'expediente.solicitud:id,inmediata'])->get();

        $totalAudiencias = $audiencias->count();
        $inmediatas = $audiencias->filter(function($a) {
            return $a->expediente && $a->expediente->solicitud && $a->expediente->solicitud->inmediata;
        })->count();

        // Contadores según las resoluciones (1: Convenio, 2 y 3: No convenio, 4: Archivado)
        $convenios = $audiencias->where('resolucion_id', 1)->count();
        $noConvenios = $audiencias->whereIn('resolucion_id', [2, 3])->count();
        $archivados = $audiencias->where('resolucion_id', 4)->count();
        $sinResolucion = $audiencias->whereNull('resolucion_id')->count();

        // Estatus de notificaciones agrupados
        $estatusNotificaciones = [];
        foreach ($audiencias as $a) {
            foreach ($a->audienciaParte as $ap) {
                $status = $ap->finalizado ?: 'Pendiente';
                if (!isset($estatusNotificaciones[$status])) {
                    $estatusNotificaciones[$status] = 0;
                }
                $estatusNotificaciones[$status]++;
            }
        }

        // Calculamos la efectividad (Convenios vs total de audiencias con resolución válida para el cálculo)
        // Generalmente, la efectividad es: Convenios / (Convenios + No Convenios) o Convenios / Total General
        // Lo calcularemos sobre el Total General para mayor precisión.
        $efectividadGeneral = $totalAudiencias > 0 ? round(($convenios / $totalAudiencias) * 100, 2) : 0;
        
        // Alternativamente: Efectividad de Conciliación (descartando sin resolución y archivados)
        $totalConciliados = $convenios + $noConvenios;
        $efectividadReal = $totalConciliados > 0 ? round(($convenios / $totalConciliados) * 100, 2) : 0;

        return response()->json([
            'total_audiencias' => $totalAudiencias,
            'total_audiencias_inmediatas' => $inmediatas,
            'total_audiencias_ordinarias' => $totalAudiencias - $inmediatas,
            'convenios' => $convenios,
            'no_convenios' => $noConvenios,
            'archivados' => $archivados,
            'sin_resolucion' => $sinResolucion,
            'estatus_notificaciones' => $estatusNotificaciones,
            'porcentaje_efectividad_general' => $efectividadGeneral,
            'porcentaje_efectividad_conciliacion' => $efectividadReal
        ]);
    }

    /**
     * Obtiene un resumen general agrupado por días en un periodo determinado.
     * Muestra total de audiencias en el día y agrupación por resoluciones.
     */
    public function getResumenGeneral(Request $request)
    {
        $centrosFiltro = $this->getCentrosPermitidos($request);
        $conciliadoresActivos = $this->getConciliadoresActivos();

        $fechaInicio = $request->query('fecha_inicio', Carbon::now()->startOfWeek()->toDateString());
        $fechaFin = $request->query('fecha_fin', Carbon::now()->endOfWeek()->toDateString());
        
        $incluirInmediatas = $request->query('incluir_inmediatas', 'true');

        // Identificar IDs de conciliadores válidos
        if ($conciliadoresActivos !== null) {
            $conciliadoresIdsValidos = $conciliadoresActivos;
        } else {
            $conciliadoresIdsValidos = Conciliador::whereIn('centro_id', $centrosFiltro)->pluck('id')->toArray();
        }

        // Obtener nombres de los centros filtrados
        $centrosInfo = Centro::whereIn('id', $centrosFiltro)->select('id', 'nombre')->get();

        $query = Audiencia::whereBetween('fecha_audiencia', [$fechaInicio, $fechaFin])
            ->where(function($query) use ($conciliadoresIdsValidos) {
                $query->whereIn('conciliador_id', $conciliadoresIdsValidos)
                      ->orWhereHas('conciliadoresAudiencias', function($q) use ($conciliadoresIdsValidos) {
                          $q->whereIn('conciliador_id', $conciliadoresIdsValidos);
                      });
            });

        if ($incluirInmediatas === 'false' || $incluirInmediatas === '0' || $incluirInmediatas === false) {
            $query->whereHas('expediente.solicitud', function ($q) {
                $q->where('inmediata', false);
            });
        }

        // Obtener todas las audiencias aplicando filtros y relaciones
        $audiencias = $query->select('id', 'fecha_audiencia', 'resolucion_id', 'conciliador_id', 'expediente_id')
            ->with(['resolucion', 'conciliador.centro', 'conciliadoresAudiencias.conciliador.centro', 'audienciaParte:id,audiencia_id,finalizado', 'expediente.solicitud:id,inmediata'])
            ->orderBy('fecha_audiencia', 'asc')
            ->get();

        // Agrumamos la data por día (fecha_audiencia)
        $resumenDiario = $audiencias->groupBy('fecha_audiencia')->map(function ($audienciasDelDia, $fecha) {
            $totalDelDia = $audienciasDelDia->count();
            $inmediatasDelDia = $audienciasDelDia->filter(function($a) {
                return $a->expediente && $a->expediente->solicitud && $a->expediente->solicitud->inmediata;
            })->count();
            $ordinariasDelDia = $totalDelDia - $inmediatasDelDia;
            
            // Sub-agrupamos por nombre de la resolución
            $resolucionesAgrupadas = $audienciasDelDia->groupBy(function ($a) {
                return $a->resolucion ? $a->resolucion->nombre : 'Sin resolución';
            })->map(function ($grupo) {
                return $grupo->count();
            });

            // Sub-agrupamos estatus de notificaciones en el día
            $notificacionesAgrupadas = [];
            foreach ($audienciasDelDia as $a) {
                foreach ($a->audienciaParte as $ap) {
                    $status = $ap->finalizado ?: 'Pendiente';
                    if (!isset($notificacionesAgrupadas[$status])) {
                        $notificacionesAgrupadas[$status] = 0;
                    }
                    $notificacionesAgrupadas[$status]++;
                }
            }

            return [
                'fecha' => $fecha,
                'total_audiencias' => $totalDelDia,
                'total_audiencias_inmediatas' => $inmediatasDelDia,
                'total_audiencias_ordinarias' => $ordinariasDelDia,
                'resoluciones' => $resolucionesAgrupadas,
                'estatus_notificaciones' => $notificacionesAgrupadas
            ];
        })->values(); // Lo hacemos array numérico para mejor legibilidad en el JSON

        // Agrupamos la data por sede
        $resumenSedes = $audiencias->groupBy(function ($a) {
            if ($a->conciliador && $a->conciliador->centro) {
                return $a->conciliador->centro->nombre;
            }
            if ($a->conciliadoresAudiencias && $a->conciliadoresAudiencias->count() > 0) {
                $ca = $a->conciliadoresAudiencias->first();
                if ($ca && $ca->conciliador && $ca->conciliador->centro) {
                    return $ca->conciliador->centro->nombre;
                }
            }
            return 'Sede no identificada';
        })->map(function ($audienciasDeSede, $sede) {
            $totalDeSede = $audienciasDeSede->count();
            $inmediatasDeSede = $audienciasDeSede->filter(function($a) {
                return $a->expediente && $a->expediente->solicitud && $a->expediente->solicitud->inmediata;
            })->count();
            $ordinariasDeSede = $totalDeSede - $inmediatasDeSede;
            
            $resolucionesAgrupadasSede = $audienciasDeSede->groupBy(function ($a) {
                return $a->resolucion ? $a->resolucion->nombre : 'Sin resolución';
            })->map(function ($grupo) {
                return $grupo->count();
            });

            // Sub-agrupamos estatus de notificaciones en la sede
            $notificacionesAgrupadasSede = [];
            foreach ($audienciasDeSede as $a) {
                foreach ($a->audienciaParte as $ap) {
                    $status = $ap->finalizado ?: 'Pendiente';
                    if (!isset($notificacionesAgrupadasSede[$status])) {
                        $notificacionesAgrupadasSede[$status] = 0;
                    }
                    $notificacionesAgrupadasSede[$status]++;
                }
            }

            return [
                'sede' => $sede,
                'total_audiencias' => $totalDeSede,
                'total_audiencias_inmediatas' => $inmediatasDeSede,
                'total_audiencias_ordinarias' => $ordinariasDeSede,
                'resoluciones' => $resolucionesAgrupadasSede,
                'estatus_notificaciones' => $notificacionesAgrupadasSede
            ];
        })->values();

        // Para darle un valor extra al front, mandamos también un consolidado de todo el periodo sumado
        $totalGeneral = $audiencias->count();
        $totalGeneralInmediatas = $audiencias->filter(function($a) {
            return $a->expediente && $a->expediente->solicitud && $a->expediente->solicitud->inmediata;
        })->count();
        $totalGeneralOrdinarias = $totalGeneral - $totalGeneralInmediatas;

        $resolucionesGenerales = $audiencias->groupBy(function ($a) {
            return $a->resolucion ? $a->resolucion->nombre : 'Sin resolución';
        })->map->count();

        $estatusNotificacionesGenerales = [];
        foreach ($audiencias as $a) {
            foreach ($a->audienciaParte as $ap) {
                $status = $ap->finalizado ?: 'Pendiente';
                if (!isset($estatusNotificacionesGenerales[$status])) {
                    $estatusNotificacionesGenerales[$status] = 0;
                }
                $estatusNotificacionesGenerales[$status]++;
            }
        }

        return response()->json([
            'resumen_periodo' => [
                'fecha_inicio' => $fechaInicio,
                'fecha_fin' => $fechaFin,
                'centros_incluidos' => $centrosInfo,
                'total_audiencias' => $totalGeneral,
                'total_audiencias_inmediatas' => $totalGeneralInmediatas,
                'total_audiencias_ordinarias' => $totalGeneralOrdinarias,
                'resoluciones' => $resolucionesGenerales,
                'estatus_notificaciones' => $estatusNotificacionesGenerales
            ],
            'desglose_diario' => $resumenDiario,
            'desglose_sedes' => $resumenSedes
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }
}