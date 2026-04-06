<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Conciliador;
use App\Audiencia;
use App\Centro;
use App\Configuracion;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Solicitud;

class DashboardController extends Controller
{
    private const CENTROS_PERMITIDOS_DEFAULT = [38, 39, 40, 41, 42, 43, 44, 46, 47, 48];

    private const CONFIG_KEY_CANDIDATES = ['clave', 'codigo', 'nombre', 'parametro', 'key'];

    private const CONFIG_VALUE_CANDIDATES = ['valor', 'value', 'dato', 'contenido'];

    private function getConfiguracionExistingColumns($candidates)
    {
        if (!Schema::hasTable('configuraciones')) {
            return [];
        }

        $existingColumns = [];
        foreach ($candidates as $column) {
            if (Schema::hasColumn('configuraciones', $column)) {
                $existingColumns[] = $column;
            }
        }

        return $existingColumns;
    }

    private function getConfiguracionKeyColumn()
    {
        if (!Schema::hasTable('configuraciones')) {
            return 'codigo';
        }

        foreach (self::CONFIG_KEY_CANDIDATES as $column) {
            if (Schema::hasColumn('configuraciones', $column)) {
                return $column;
            }
        }

        return 'codigo';
    }

    private function getConfiguracionValueColumn()
    {
        if (!Schema::hasTable('configuraciones')) {
            return 'valor';
        }

        foreach (self::CONFIG_VALUE_CANDIDATES as $column) {
            if (Schema::hasColumn('configuraciones', $column)) {
                return $column;
            }
        }

        return 'valor';
    }

    private function getConfiguracionPorCodigo($codigo)
    {
        if (!Schema::hasTable('configuraciones')) {
            return null;
        }

        $keyColumns = $this->getConfiguracionExistingColumns(self::CONFIG_KEY_CANDIDATES);
        if (empty($keyColumns)) {
            return null;
        }

        $query = Configuracion::query();
        foreach ($keyColumns as $index => $column) {
            if ($index === 0) {
                $query->where($column, $codigo);
            } else {
                $query->orWhere($column, $codigo);
            }
        }

        return $query->first();
    }

    private function saveConfiguracionPorCodigo($codigo, $valor)
    {
        if (!Schema::hasTable('configuraciones')) {
            return null;
        }

        $keyColumns = $this->getConfiguracionExistingColumns(self::CONFIG_KEY_CANDIDATES);
        if (empty($keyColumns)) {
            return null;
        }

        $keyColumn = $keyColumns[0];
        $valueColumn = $this->getConfiguracionValueColumn();

        $registro = $this->getConfiguracionPorCodigo($codigo);

        if ($registro) {
            foreach ($keyColumns as $column) {
                if (empty($registro->{$column})) {
                    $registro->{$column} = $codigo;
                }
            }
            $registro->{$valueColumn} = $valor;
            $registro->save();
            return $registro;
        }

        $payload = [
            $keyColumn => $codigo,
            $valueColumn => $valor,
        ];

        foreach ($keyColumns as $column) {
            $payload[$column] = $codigo;
        }

        return Configuracion::create($payload);
    }

    private function getConfiguracionValor($configuracion)
    {
        if (!$configuracion) {
            return null;
        }

        $valueColumn = $this->getConfiguracionValueColumn();
        return $configuracion->{$valueColumn} ?? null;
    }

    private function getPesoAudienciaPorParte($audiencia)
    {
        $citados = 0;

        if ($audiencia->audienciaParte) {
            foreach ($audiencia->audienciaParte as $ap) {
                if ($ap->parte && $ap->parte->tipo_parte_id == 2) {
                    $citados++;
                }
            }
        }

        return max(1, $citados);
    }

    private function getDesgloseResolucionesPorCategoria($audiencias, $porParte = false)
    {
        $desglose = [
            'convenios' => 0,
            'no_convenios' => 0,
            'no_convenios_incomparecencia' => 0,
            'archivados' => 0,
            'sin_resolucion' => 0,
        ];

        foreach ($audiencias as $a) {
            $peso = $porParte ? $this->getPesoAudienciaPorParte($a) : 1;

            if ($a->resolucion_id == 1) {
                $desglose['convenios'] += $peso;
            } elseif (in_array($a->resolucion_id, [2, 3])) {
                if ($a->tipo_terminacion_audiencia_id == 3) {
                    $desglose['no_convenios_incomparecencia'] += $peso;
                } else {
                    $desglose['no_convenios'] += $peso;
                }
            } elseif ($a->resolucion_id == 4) {
                $desglose['archivados'] += $peso;
            } else {
                $desglose['sin_resolucion'] += $peso;
            }
        }

        return $desglose;
    }

    private function getEstadoAgendaTexto($audiencia)
    {
        if (!$audiencia->resolucion_id) {
            return 'Pendiente';
        }

        if ($audiencia->resolucion_id == 1) {
            return 'Confirmada';
        }

        if (in_array($audiencia->resolucion_id, [2, 3])) {
            return $audiencia->tipo_terminacion_audiencia_id == 3 ? 'Suspendida' : 'Cancelada';
        }

        if ($audiencia->resolucion_id == 4) {
            return 'Cancelada';
        }

        return 'Pendiente';
    }

    private function getPartesAgendaTexto($audiencia)
    {
        $partes = [];

        if ($audiencia->audienciaParte) {
            foreach ($audiencia->audienciaParte as $ap) {
                if (!$ap->parte) {
                    continue;
                }

                $nombreParte = trim(($ap->parte->nombre ?? '') . ' ' . ($ap->parte->primer_apellido ?? '') . ' ' . ($ap->parte->segundo_apellido ?? ''));
                if ($nombreParte !== '') {
                    $partes[] = $nombreParte;
                }
            }
        }

        $partes = array_values(array_unique($partes));

        return !empty($partes) ? implode(' vs. ', $partes) : 'Sin partes registradas';
    }

    private function getHoraAgendaTexto($hora)
    {
        if (!$hora) {
            return 'Sin hora';
        }

        try {
            return Carbon::parse($hora)->format('h:i A');
        } catch (\Exception $e) {
            return (string) $hora;
        }
    }

    private function parseFiltroBooleano($valor)
    {
        if ($valor === null || $valor === '' || $valor === 'todas' || $valor === 'todos' || $valor === 'all') {
            return null;
        }

        if ($valor === true || $valor === 'true' || $valor === '1' || $valor === 1 || $valor === 'si' || $valor === 'sí') {
            return true;
        }

        if ($valor === false || $valor === 'false' || $valor === '0' || $valor === 0 || $valor === 'no') {
            return false;
        }

        return null;
    }

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
        $configuracion = $this->getConfiguracionPorCodigo('conciliadores_activos');
        $valor = $this->getConfiguracionValor($configuracion);
        $conciliadoresActivos = $valor ? json_decode($valor, true) : [];

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

        $this->saveConfiguracionPorCodigo('conciliadores_activos', json_encode($conciliadoresValidos));

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
        $configuracion = $this->getConfiguracionPorCodigo('conciliadores_activos');
        $valor = $this->getConfiguracionValor($configuracion);
        $conciliadoresActivos = $valor ? json_decode($valor, true) : [];

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
        $configuracion = $this->getConfiguracionPorCodigo('conciliadores_activos');
        $valor = $this->getConfiguracionValor($configuracion);

        if (!$configuracion || !$valor) {
            return null; // Sin configuración, usar lógica original
        }

        $conciliadoresActivos = json_decode($valor, true);
        if (!is_array($conciliadoresActivos)) {
            return null;
        }

        $conciliadoresActivos = array_values(array_filter(array_map(function ($id) {
            return (int) $id;
        }, $conciliadoresActivos), function ($id) {
            return $id > 0;
        }));

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
            // Para 'todos', usar conciliadores configurados si existen, cruzando con centros permitidos
            if ($conciliadoresActivos !== null) {
                $conciliadoresIdsValidos = Conciliador::whereIn('id', $conciliadoresActivos)
                    ->whereIn('centro_id', $centrosPermitidos)
                    ->pluck('id')
                    ->toArray();
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

        $audiencias = $audienciasQuery->select('id', 'resolucion_id', 'expediente_id', 'tipo_terminacion_audiencia_id')
            ->with(['audienciaParte' => function($q) {
                $q->select('id', 'audiencia_id', 'finalizado', 'parte_id');
            }, 'audienciaParte.parte:id,tipo_parte_id', 'expediente.solicitud:id,inmediata'])->get();

        $totalAudiencias = $audiencias->count();
        $inmediatas = $audiencias->filter(function($a) {
            return $a->expediente && $a->expediente->solicitud && $a->expediente->solicitud->inmediata;
        })->count();

        // Contadores según las resoluciones por expediente (original)
        $convenios = $audiencias->where('resolucion_id', 1)->count();
        $noConvenios = $audiencias->whereIn('resolucion_id', [2, 3])->where('tipo_terminacion_audiencia_id', '!=', 3)->count();
        $noConveniosIncomparecencia = $audiencias->whereIn('resolucion_id', [2, 3])->where('tipo_terminacion_audiencia_id', 3)->count();
        $archivados = $audiencias->where('resolucion_id', 4)->count();
        $sinResolucion = $audiencias->whereNull('resolucion_id')->count();

        // Contadores de resoluciones por parte (citados, usando tipo_parte_id = 2)
        $convenios_por_parte = 0;
        $no_convenios_por_parte = 0;
        $no_convenios_incomparecencia_por_parte = 0;
        $archivados_por_parte = 0;
        $sin_resolucion_por_parte = 0;

        foreach ($audiencias as $a) {
            $citadosCount = 0;
            if ($a->audienciaParte) {
                foreach ($a->audienciaParte as $ap) {
                    if ($ap->parte && $ap->parte->tipo_parte_id == 2) {
                        $citadosCount++;
                    }
                }
            }
            // Si por error de captura no hay citados, al menos lo contamos como 1
            $peso = max(1, $citadosCount);

            if ($a->resolucion_id == 1) {
                $convenios_por_parte += $peso;
            } elseif (in_array($a->resolucion_id, [2, 3])) {
                if ($a->tipo_terminacion_audiencia_id == 3) {
                    $no_convenios_incomparecencia_por_parte += $peso;
                } else {
                    $no_convenios_por_parte += $peso;
                }
            } elseif ($a->resolucion_id == 4) {
                $archivados_por_parte += $peso;
            } else {
                $sin_resolucion_por_parte += $peso;
            }
        }

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
        $totalConciliados = $convenios + $noConvenios + $noConveniosIncomparecencia;
        $efectividadReal = $totalConciliados > 0 ? round(($convenios / $totalConciliados) * 100, 2) : 0;

        return response()->json([
            'total_audiencias' => $totalAudiencias,
            'total_audiencias_inmediatas' => $inmediatas,
            'total_audiencias_ordinarias' => $totalAudiencias - $inmediatas,
            
            // Conteo por expediente
            'convenios' => $convenios,
            'no_convenios' => $noConvenios,
            'no_convenios_incomparecencia' => $noConveniosIncomparecencia,
            'archivados' => $archivados,
            'sin_resolucion' => $sinResolucion,
            
            // Conteo por parte (citados referenciados en audiencia)
            'convenios_por_parte' => $convenios_por_parte,
            'no_convenios_por_parte' => $no_convenios_por_parte,
            'no_convenios_incomparecencia_por_parte' => $no_convenios_incomparecencia_por_parte,
            'archivados_por_parte' => $archivados_por_parte,
            'sin_resolucion_por_parte' => $sin_resolucion_por_parte,

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

        // Identificar IDs de conciliadores válidos cruzando con los centros solicitados
        if ($conciliadoresActivos !== null) {
            $conciliadoresIdsValidos = Conciliador::whereIn('id', $conciliadoresActivos)
                ->whereIn('centro_id', $centrosFiltro)
                ->pluck('id')
                ->toArray();
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
        $audiencias = $query->select('id', 'fecha_audiencia', 'resolucion_id', 'conciliador_id', 'expediente_id', 'tipo_terminacion_audiencia_id')
            ->with(['resolucion', 'conciliador.centro', 'conciliadoresAudiencias.conciliador.centro', 'audienciaParte:id,audiencia_id,finalizado,parte_id', 'audienciaParte.parte:id,tipo_parte_id', 'expediente.solicitud:id,inmediata'])
            ->orderBy('fecha_audiencia', 'asc')
            ->get();

        // Agrumamos la data por día (fecha_audiencia)
        $resumenDiario = $audiencias->groupBy('fecha_audiencia')->map(function ($audienciasDelDia, $fecha) {
            $totalDelDia = $audienciasDelDia->count();
            $inmediatasDelDia = $audienciasDelDia->filter(function($a) {
                return $a->expediente && $a->expediente->solicitud && $a->expediente->solicitud->inmediata;
            })->count();
            $ordinariasDelDia = $totalDelDia - $inmediatasDelDia;
            
            // Sub-agrupamos por nombre de la resolución (conteo por expediente)
            $resolucionesAgrupadas = $audienciasDelDia->groupBy(function ($a) {
                if (in_array($a->resolucion_id, [2, 3]) && $a->tipo_terminacion_audiencia_id == 3) {
                    return 'No hubo convenio por incomparecencia';
                }
                return $a->resolucion ? $a->resolucion->nombre : 'Sin resolución';
            })->map(function ($grupo) {
                return $grupo->count();
            });

            // Sub-agrupamos por parte (conteo por citados en la audiencia)
            $resolucionesAgrupadasPorParte = $audienciasDelDia->groupBy(function ($a) {
                if (in_array($a->resolucion_id, [2, 3]) && $a->tipo_terminacion_audiencia_id == 3) {
                    return 'No hubo convenio por incomparecencia';
                }
                return $a->resolucion ? $a->resolucion->nombre : 'Sin resolución';
            })->map(function ($grupo) {
                return $grupo->sum(function($a) {
                    $citados = 0;
                    if ($a->audienciaParte) {
                        foreach ($a->audienciaParte as $ap) {
                            if ($ap->parte && $ap->parte->tipo_parte_id == 2) {
                                $citados++;
                            }
                        }
                    }
                    return max(1, $citados);
                });
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
                'resoluciones_por_parte' => $resolucionesAgrupadasPorParte,
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
            
            // Sub-agrupamos por nombre de la resolución (conteo por expediente)
            $resolucionesAgrupadasSede = $audienciasDeSede->groupBy(function ($a) {
                if (in_array($a->resolucion_id, [2, 3]) && $a->tipo_terminacion_audiencia_id == 3) {
                    return 'No hubo convenio por incomparecencia';
                }
                return $a->resolucion ? $a->resolucion->nombre : 'Sin resolución';
            })->map(function ($grupo) {
                return $grupo->count();
            });

            // Sub-agrupamos por parte (conteo por citados)
            $resolucionesAgrupadasSedePorParte = $audienciasDeSede->groupBy(function ($a) {
                if (in_array($a->resolucion_id, [2, 3]) && $a->tipo_terminacion_audiencia_id == 3) {
                    return 'No hubo convenio por incomparecencia';
                }
                return $a->resolucion ? $a->resolucion->nombre : 'Sin resolución';
            })->map(function ($grupo) {
                return $grupo->sum(function($a) {
                    return $this->getPesoAudienciaPorParte($a);
                });
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

            $desgloseResolucionesSede = $this->getDesgloseResolucionesPorCategoria($audienciasDeSede);
            $desgloseResolucionesSedePorParte = $this->getDesgloseResolucionesPorCategoria($audienciasDeSede, true);

            return [
                'sede' => $sede,
                'total_audiencias' => $totalDeSede,
                'total_audiencias_inmediatas' => $inmediatasDeSede,
                'total_audiencias_ordinarias' => $ordinariasDeSede,
                'resoluciones' => $resolucionesAgrupadasSede,
                'resoluciones_por_parte' => $resolucionesAgrupadasSedePorParte,
                'estatus_notificaciones' => $notificacionesAgrupadasSede,
                'desglose_resoluciones' => $desgloseResolucionesSede,
                'desglose_resoluciones_por_parte' => $desgloseResolucionesSedePorParte
            ];
        })->values();

        // Para darle un valor extra al front, mandamos también un consolidado de todo el periodo sumado
        $totalGeneral = $audiencias->count();
        $totalGeneralInmediatas = $audiencias->filter(function($a) {
            return $a->expediente && $a->expediente->solicitud && $a->expediente->solicitud->inmediata;
        })->count();
        $totalGeneralOrdinarias = $totalGeneral - $totalGeneralInmediatas;

        $resolucionesGenerales = $audiencias->groupBy(function ($a) {
            if (in_array($a->resolucion_id, [2, 3]) && $a->tipo_terminacion_audiencia_id == 3) {
                return 'No hubo convenio por incomparecencia';
            }
            return $a->resolucion ? $a->resolucion->nombre : 'Sin resolución';
        })->map->count();

        $resolucionesGeneralesPorParte = $audiencias->groupBy(function ($a) {
            if (in_array($a->resolucion_id, [2, 3]) && $a->tipo_terminacion_audiencia_id == 3) {
                return 'No hubo convenio por incomparecencia';
            }
            return $a->resolucion ? $a->resolucion->nombre : 'Sin resolución';
        })->map(function ($grupo) {
            return $grupo->sum(function($a) {
                $citados = 0;
                if ($a->audienciaParte) {
                    foreach ($a->audienciaParte as $ap) {
                        if ($ap->parte && $ap->parte->tipo_parte_id == 2) {
                            $citados++;
                        }
                    }
                }
                return max(1, $citados);
            });
        });

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
                'resoluciones_por_parte' => $resolucionesGeneralesPorParte,
                'estatus_notificaciones' => $estatusNotificacionesGenerales
            ],
            'desglose_diario' => $resumenDiario,
            'desglose_sedes' => $resumenSedes
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Obtiene un ranking de efectividad de los conciliadores en las fechas establecidas.
     * Basado en el conteo por partes citadas.
     */
    public function getRankingConciliadores(Request $request)
    {
        $centrosFiltro = $this->getCentrosPermitidos($request);
        $conciliadoresActivos = $this->getConciliadoresActivos();

        $fechaInicio = $request->query('fecha_inicio', Carbon::now()->startOfWeek()->toDateString());
        $fechaFin = $request->query('fecha_fin', Carbon::now()->endOfWeek()->toDateString());
        
        $incluirInmediatas = $request->query('incluir_inmediatas', 'true');

        // Identificar IDs de conciliadores válidos cruzando con centros permitidos
        if ($conciliadoresActivos !== null) {
            $conciliadoresIdsValidos = Conciliador::whereIn('id', $conciliadoresActivos)
                ->whereIn('centro_id', $centrosFiltro)
                ->pluck('id')
                ->toArray();
        } else {
            $conciliadoresIdsValidos = Conciliador::whereIn('centro_id', $centrosFiltro)->pluck('id')->toArray();
        }

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

        // Cargar las relaciones que necesitamos para armar el conteo
        $audiencias = $query->select('id', 'resolucion_id', 'conciliador_id', 'expediente_id', 'tipo_terminacion_audiencia_id')
            ->with([
                'conciliador.persona', 'conciliador.centro',
                'conciliadoresAudiencias.conciliador.persona', 'conciliadoresAudiencias.conciliador.centro',
                'audienciaParte:id,audiencia_id,parte_id', 'audienciaParte.parte:id,tipo_parte_id',
                'expediente.solicitud:id,inmediata'
            ])
            ->get();

        $resultadosPorConciliador = [];

        foreach ($audiencias as $a) {
            // Contabilizar partes (citados)
            $citadosCount = 0;
            if ($a->audienciaParte) {
                foreach ($a->audienciaParte as $ap) {
                    if ($ap->parte && $ap->parte->tipo_parte_id == 2) {
                        $citadosCount++;
                    }
                }
            }
            // Si por error no hay citados, tomamos peso 1
            $peso = max(1, $citadosCount);

            // Determinar qué conciliadores están asignados a esta audiencia
            // Revisamos tanto el conciliador principal como los posibles adicionales
            $conciliadoresInvolucrados = [];
            
            if (in_array($a->conciliador_id, $conciliadoresIdsValidos)) {
                $conciliadoresInvolucrados[$a->conciliador_id] = $a->conciliador;
            }
            
            if ($a->conciliadoresAudiencias) {
                foreach ($a->conciliadoresAudiencias as $ca) {
                    if (in_array($ca->conciliador_id, $conciliadoresIdsValidos)) {
                        $conciliadoresInvolucrados[$ca->conciliador_id] = $ca->conciliador;
                    }
                }
            }

            foreach ($conciliadoresInvolucrados as $cId => $conciliador) {
                // Inicializar estrucura del conciliador si no existe
                if (!isset($resultadosPorConciliador[$cId])) {
                    $nombrePersona = $conciliador && $conciliador->persona 
                        ? trim($conciliador->persona->nombre . ' ' . $conciliador->persona->primer_apellido . ' ' . $conciliador->persona->segundo_apellido)
                        : 'Sin nombre';
                        
                    $resultadosPorConciliador[$cId] = [
                        'conciliador_id' => $cId,
                        'nombre' => $nombrePersona,
                        'centro_id' => $conciliador ? $conciliador->centro_id : null,
                        'centro_nombre' => $conciliador && $conciliador->centro ? $conciliador->centro->nombre : 'Sin centro',
                        'total_audiencias_implicado' => 0, // audiencias donde participó
                        'convenios_por_parte' => 0,
                        'no_convenios_por_parte' => 0,
                        'no_convenios_incomparecencia_por_parte' => 0,
                        'archivados_por_parte' => 0,
                        'sin_resolucion_por_parte' => 0,
                    ];
                }

                $resultadosPorConciliador[$cId]['total_audiencias_implicado']++;

                if ($a->resolucion_id == 1) {
                    $resultadosPorConciliador[$cId]['convenios_por_parte'] += $peso;
                } elseif (in_array($a->resolucion_id, [2, 3])) {
                    if ($a->tipo_terminacion_audiencia_id == 3) {
                        $resultadosPorConciliador[$cId]['no_convenios_incomparecencia_por_parte'] += $peso;
                    } else {
                        $resultadosPorConciliador[$cId]['no_convenios_por_parte'] += $peso;
                    }
                } elseif ($a->resolucion_id == 4) {
                    $resultadosPorConciliador[$cId]['archivados_por_parte'] += $peso;
                } else {
                    $resultadosPorConciliador[$cId]['sin_resolucion_por_parte'] += $peso;
                }
            }
        }

        // Calcular porcentajes de efectividad y ordenarlos
        $rankingList = collect($resultadosPorConciliador)->map(function ($item) {
            $convenios = $item['convenios_por_parte'];
            $noConvenios = $item['no_convenios_por_parte'];
            $noConveniosIncomp = $item['no_convenios_incomparecencia_por_parte'];
            
            // Efectividad Conciliación = Convenios / (Convenios + No Convenios + No Convenios Incomparecencia)
            $totalConciliados = $convenios + $noConvenios + $noConveniosIncomp;
            $efectividadReal = $totalConciliados > 0 ? round(($convenios / $totalConciliados) * 100, 2) : 0;
            
            // Total general en ponderado por parte
            $totalGeneralPonderado = $convenios + $noConvenios + $noConveniosIncomp + $item['archivados_por_parte'] + $item['sin_resolucion_por_parte'];
            
            // Efectividad General = Convenios / Total de resolución
            $efectividadGeneral = $totalGeneralPonderado > 0 ? round(($convenios / $totalGeneralPonderado) * 100, 2) : 0;

            $item['porcentaje_efectividad_conciliacion'] = $efectividadReal;
            $item['porcentaje_efectividad_general'] = $efectividadGeneral;
            $item['total_partes_atendidas'] = $totalGeneralPonderado;
            
            return $item;
        })->sortByDesc('porcentaje_efectividad_conciliacion')->values()->toArray();

        return response()->json([
            'fecha_inicio' => $fechaInicio,
            'fecha_fin' => $fechaFin,
            'ranking' => $rankingList
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Agenda diaria de un conciliador (Tablero Operativo).
     */
    public function getAgendaDia(Request $request, $id)
    {
        $centrosPermitidos = $this->getCentrosPermitidos($request);
        $conciliadoresActivos = $this->getConciliadoresActivos();

        if ($conciliadoresActivos !== null) {
            $conciliadorValido = Conciliador::whereIn('id', $conciliadoresActivos)->find($id);
        } else {
            $conciliadorValido = Conciliador::whereIn('centro_id', $centrosPermitidos)->find($id);
        }

        if (!$conciliadorValido) {
            return response()->json(['error' => 'Conciliador no encontrado o no tiene permisos'], 404);
        }

        $fecha = $request->query('fecha', Carbon::now()->toDateString());

        $audiencias = Audiencia::whereDate('fecha_audiencia', $fecha)
            ->where(function($query) use ($id) {
                $query->where('conciliador_id', $id)
                      ->orWhereHas('conciliadoresAudiencias', function($q) use ($id) {
                          $q->where('conciliador_id', $id);
                      });
            })
            ->select('id', 'expediente_id', 'resolucion_id', 'fecha_audiencia', 'hora_inicio', 'hora_fin')
            ->with([
                'expediente:id,folio,anio,solicitud_id', 
                'expediente.solicitud:id,inmediata', 
                'salasAudiencias.sala:id,sala', 
                'resolucion:id,nombre',
                'audienciaParte.parte:id,tipo_parte_id,nombre,primer_apellido,segundo_apellido'
            ])
            ->orderBy('hora_inicio')
            ->get();

            $agenda = $audiencias->map(function($a) {
            $sala = $a->salasAudiencias && $a->salasAudiencias->count() > 0 
                ? $a->salasAudiencias->first()->sala->sala 
                : 'Sin sala asignada';
            
            $partesInvolucradas = [];
            if ($a->audienciaParte) {
                $partesInvolucradas = $a->audienciaParte->map(function ($ap) {
                    $tipo = '';
                    if ($ap->parte) {
                        if ($ap->parte->tipo_parte_id == 1) $tipo = 'Solicitante';
                        elseif ($ap->parte->tipo_parte_id == 2) $tipo = 'Citado';
                        else $tipo = 'Otro';
                        
                        $nombreParte = trim(($ap->parte->nombre ?? '') . ' ' . ($ap->parte->primer_apellido ?? '') . ' ' . ($ap->parte->segundo_apellido ?? ''));
                        return [
                            'tipo' => $tipo,
                            'nombre' => $nombreParte
                        ];
                    }
                    return null;
                })->filter()->values()->toArray();
            }

            $partesTexto = $this->getPartesAgendaTexto($a);

            return [
                'id' => $a->id,
                'hora' => $this->getHoraAgendaTexto($a->hora_inicio),
                'hora_inicio' => $a->hora_inicio,
                'hora_fin' => $a->hora_fin,
                'expediente' => $a->expediente ? $a->expediente->folio . '/' . $a->expediente->anio : 'Sin expediente',
                'inmediata' => $a->expediente && $a->expediente->solicitud ? (bool) $a->expediente->solicitud->inmediata : false,
                'sala' => $sala,
                'estado' => $this->getEstadoAgendaTexto($a),
                'estado_actual' => $a->resolucion ? $a->resolucion->nombre : 'Pendiente o Sin resolución',
                'partes' => $partesTexto,
                'partes_detalle' => $partesInvolucradas
            ];
        });

        $mensaje = $audiencias->isEmpty()
            ? 'No hay citas para la fecha seleccionada.'
            : 'Agenda cargada correctamente.';

        return response()->json([
            'conciliador_id' => (int) $id,
            'fecha' => $fecha,
            'total_audiencias' => $audiencias->count(),
            'audiencias' => $agenda,
            'agenda' => $agenda,
            'mensaje' => $mensaje
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Motivos de No Convenio (Tablero de Coordinadores).
     */
    public function getMotivosNoConvenio(Request $request)
    {
        $centrosFiltro = $this->getCentrosPermitidos($request);
        $conciliadoresActivos = $this->getConciliadoresActivos();

        $fechaInicio = $request->query('fecha_inicio', Carbon::now()->startOfWeek()->toDateString());
        $fechaFin = $request->query('fecha_fin', Carbon::now()->endOfWeek()->toDateString());

        if ($conciliadoresActivos !== null) {
            $conciliadoresIdsValidos = Conciliador::whereIn('id', $conciliadoresActivos)
                ->whereIn('centro_id', $centrosFiltro)
                ->pluck('id')
                ->toArray();
        } else {
            $conciliadoresIdsValidos = Conciliador::whereIn('centro_id', $centrosFiltro)->pluck('id')->toArray();
        }

        $audiencias = Audiencia::whereBetween('fecha_audiencia', [$fechaInicio, $fechaFin])
            ->where(function($query) use ($conciliadoresIdsValidos) {
                $query->whereIn('conciliador_id', $conciliadoresIdsValidos)
                      ->orWhereHas('conciliadoresAudiencias', function($q) use ($conciliadoresIdsValidos) {
                          $q->whereIn('conciliador_id', $conciliadoresIdsValidos);
                      });
            })
            ->whereIn('resolucion_id', [2, 3]) // Solo las de no convenio
            ->select('id', 'tipo_terminacion_audiencia_id')
            ->with('tipoTerminacion')
            ->get();

        $motivos = $audiencias->groupBy('tipo_terminacion_audiencia_id')->map(function ($grupo) {
            $primerElemento = $grupo->first();
            $nombreMotivo = $primerElemento->tipoTerminacion ? $primerElemento->tipoTerminacion->nombre : 'Motivo no especificado (o sin tipo_terminacion)';
            return [
                'motivo' => $nombreMotivo,
                'cantidad' => $grupo->count()
            ];
        })->values()->sortByDesc('cantidad')->values();

        return response()->json([
            'fecha_inicio' => $fechaInicio,
            'fecha_fin' => $fechaFin,
            'total_no_convenios' => $audiencias->count(),
            'motivos' => $motivos
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Histórico Anual Agrupado por Mes (Tablero Nivel Dirección).
     */
    public function getHistoricoMensual(Request $request)
    {
        $centrosFiltro = $this->getCentrosPermitidos($request);
        $conciliadoresActivos = $this->getConciliadoresActivos();

        $anio = $request->query('anio', Carbon::now()->year);
        
        if ($conciliadoresActivos !== null) {
            $conciliadoresIdsValidos = Conciliador::whereIn('id', $conciliadoresActivos)
                ->whereIn('centro_id', $centrosFiltro)
                ->pluck('id')
                ->toArray();
        } else {
            $conciliadoresIdsValidos = Conciliador::whereIn('centro_id', $centrosFiltro)->pluck('id')->toArray();
        }

        $audiencias = Audiencia::whereYear('fecha_audiencia', $anio)
            ->where(function($query) use ($conciliadoresIdsValidos) {
                $query->whereIn('conciliador_id', $conciliadoresIdsValidos)
                      ->orWhereHas('conciliadoresAudiencias', function($q) use ($conciliadoresIdsValidos) {
                          $q->whereIn('conciliador_id', $conciliadoresIdsValidos);
                      });
            })
            ->select('id', 'fecha_audiencia', 'resolucion_id')
            ->get();

        $historico = $audiencias->groupBy(function($a) {
            // ej. 2024-01, 2024-02
            return Carbon::parse($a->fecha_audiencia)->format('Y-m'); 
        })->map(function($mesAudiencias, $mes) {
            $convenios = $mesAudiencias->where('resolucion_id', 1)->count();
            $noConvenios = $mesAudiencias->whereIn('resolucion_id', [2, 3])->count();
            $archivadas = $mesAudiencias->where('resolucion_id', 4)->count();
            $total = $mesAudiencias->count();

            return [
                'mes' => $mes,
                'total_audiencias' => $total,
                'convenios' => $convenios,
                'no_convenios' => $noConvenios,
                'archivadas' => $archivadas,
                'efectividad_porcentaje' => $total > 0 ? round(($convenios / $total) * 100, 2) : 0
            ];
        })->values()->sortBy('mes')->values();

        return response()->json([
            'anio' => $anio,
            'historico' => $historico
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Impacto Económico Acumulado (Tablero Nivel Dirección).
     */
    public function getImpactoEconomico(Request $request)
    {
        $centrosFiltro = $this->getCentrosPermitidos($request);
        $conciliadoresActivos = $this->getConciliadoresActivos();

        $fechaInicio = $request->query('fecha_inicio', Carbon::now()->startOfMonth()->toDateString());
        $fechaFin = $request->query('fecha_fin', Carbon::now()->endOfMonth()->toDateString());

        if ($conciliadoresActivos !== null) {
            $conciliadoresIdsValidos = Conciliador::whereIn('id', $conciliadoresActivos)
                ->whereIn('centro_id', $centrosFiltro)
                ->pluck('id')
                ->toArray();
        } else {
            $conciliadoresIdsValidos = Conciliador::whereIn('centro_id', $centrosFiltro)->pluck('id')->toArray();
        }

        $audiencias = Audiencia::whereBetween('fecha_audiencia', [$fechaInicio, $fechaFin])
            ->where('resolucion_id', 1) 
            ->where(function($query) use ($conciliadoresIdsValidos) {
                $query->whereIn('conciliador_id', $conciliadoresIdsValidos)
                      ->orWhereHas('conciliadoresAudiencias', function($q) use ($conciliadoresIdsValidos) {
                          $q->whereIn('conciliador_id', $conciliadoresIdsValidos);
                      });
            })
            ->select('id', 'resolucion_id', 'conciliador_id')
            ->with([
                'resolucionPartes.parteConceptos:id,resolucion_partes_id,monto'
            ])
            ->get();

        $impactoTotal = 0;
        $conveniosConMonto = 0;

        foreach ($audiencias as $a) {
            $montoAudiencia = 0;
            if ($a->resolucionPartes) {
                foreach ($a->resolucionPartes as $rp) {
                    if ($rp->parteConceptos) {
                        foreach ($rp->parteConceptos as $rpc) {
                            if (is_numeric($rpc->monto)) {
                                $montoAudiencia += (float)$rpc->monto;
                            }
                        }
                    }
                }
            }
            if ($montoAudiencia > 0) {
                $impactoTotal += $montoAudiencia;
                $conveniosConMonto++;
            }
        }

        return response()->json([
            'fecha_inicio' => $fechaInicio,
            'fecha_fin' => $fechaFin,
            'total_convenios' => $audiencias->count(),
            'convenios_con_monto_registrado' => $conveniosConMonto,
            'monto_total_recuperado' => $impactoTotal,
            'mensaje_director' => "Se han recuperado $" . number_format($impactoTotal, 2) . " MXN en convenios este periodo."
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Total de solicitudes generadas vs confirmadas en el periodo.
     * Generadas = por created_at.
     * Confirmadas = ratificada == true (de las generadas en ese rango, o de todo el universo).
     */
    public function getEstadisticasSolicitudes(Request $request)
    {
        $centrosFiltro = $this->getCentrosPermitidos($request);

        $fechaInicio = $request->query('fecha_inicio', Carbon::now()->startOfWeek()->toDateString());
        $fechaFin = $request->query('fecha_fin', Carbon::now()->endOfWeek()->toDateString());

        // Basar el universo principal en solicitudes generadas (created_at) en el rango
        $queryBase = Solicitud::whereIn('centro_id', $centrosFiltro)
            ->whereBetween('created_at', [
                $fechaInicio . ' 00:00:00', 
                $fechaFin . ' 23:59:59'
            ]);

        // Todas las generadas
        $generadasTotal = (clone $queryBase)->count();

        // Aquellas generadas en ese periodo que además están ratificadas
        $confirmadasTotal = (clone $queryBase)->where('ratificada', true)->count();

        // Solicitudes virtuales (remotas)
        $remotasGeneradasTotal = (clone $queryBase)->where('virtual', true)->count();
        $remotasConfirmadasTotal = (clone $queryBase)->where('virtual', true)->where('ratificada', true)->count();

        // Alternativamente, si necesitas un desglose por días para armar una gráfica:
        $solicitudes = $queryBase->select('id', 'created_at', 'ratificada', 'virtual')->get();
        
        $desgloseDiario = $solicitudes->groupBy(function($s) {
            return Carbon::parse($s->created_at)->toDateString();
        })->map(function($solicitudesDia, $fecha) {
            return [
                'fecha' => $fecha,
                'generadas' => $solicitudesDia->count(),
                'confirmadas' => $solicitudesDia->where('ratificada', true)->count(),
                'remotas_generadas' => $solicitudesDia->where('virtual', true)->count(),
                'remotas_confirmadas' => $solicitudesDia->where('virtual', true)->where('ratificada', true)->count()
            ];
        })->values()->sortBy('fecha')->values();

        return response()->json([
            'fecha_inicio' => $fechaInicio,
            'fecha_fin' => $fechaFin,
            'totales' => [
                'solicitudes_generadas' => $generadasTotal,
                'solicitudes_confirmadas' => $confirmadasTotal,
                'solicitudes_remotas_generadas' => $remotasGeneradasTotal,
                'solicitudes_remotas_confirmadas' => $remotasConfirmadasTotal,
                'eficiencia_confirmacion_porcentaje' => $generadasTotal > 0 ? round(($confirmadasTotal / $generadasTotal) * 100, 2) : 0,
                'eficiencia_confirmacion_remotas_porcentaje' => $remotasGeneradasTotal > 0 ? round(($remotasConfirmadasTotal / $remotasGeneradasTotal) * 100, 2) : 0
            ],
            'desglose_diario' => $desgloseDiario
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Cantidad de solicitudes generadas por sede para gráfico de barras.
     * Filtros opcionales: remotas, confirmadas y rango de meses (created_at).
     */
    public function getSolicitudesPorSede(Request $request)
    {
        $centrosFiltro = $this->getCentrosPermitidos($request);

        $anio = (int) $request->query('anio', Carbon::now()->year);
        $mesInicio = (int) $request->query('mes_inicio', 1);
        $mesFin = (int) $request->query('mes_fin', Carbon::now()->month);

        $mesInicio = max(1, min(12, $mesInicio));
        $mesFin = max(1, min(12, $mesFin));

        if ($mesInicio > $mesFin) {
            $tmp = $mesInicio;
            $mesInicio = $mesFin;
            $mesFin = $tmp;
        }

        $filtroRemotas = $this->parseFiltroBooleano($request->query('remotas', 'todas'));
        $filtroConfirmadas = $this->parseFiltroBooleano($request->query('confirmadas', 'todas'));

        $fechaInicio = Carbon::create($anio, $mesInicio, 1)->startOfDay();
        $fechaFin = Carbon::create($anio, $mesFin, 1)->endOfMonth()->endOfDay();

        $query = Solicitud::query()
            ->whereIn('centro_id', $centrosFiltro)
            ->whereBetween('created_at', [$fechaInicio, $fechaFin]);

        if ($filtroRemotas !== null) {
            $query->where('virtual', $filtroRemotas);
        }

        if ($filtroConfirmadas !== null) {
            $query->where('ratificada', $filtroConfirmadas);
        }

        $conteoPorSede = $query
            ->select('centro_id', DB::raw('COUNT(*) as total'))
            ->groupBy('centro_id')
            ->pluck('total', 'centro_id');

        $centros = Centro::whereIn('id', $centrosFiltro)
            ->select('id', 'nombre')
            ->orderBy('nombre')
            ->get();

        $barras = $centros->map(function ($centro) use ($conteoPorSede) {
            return [
                'centro_id' => (int) $centro->id,
                'sede' => $centro->nombre,
                'solicitudes_generadas' => (int) ($conteoPorSede[$centro->id] ?? 0),
            ];
        })->values();

        return response()->json([
            'filtros' => [
                'anio' => $anio,
                'mes_inicio' => $mesInicio,
                'mes_fin' => $mesFin,
                'fecha_inicio' => $fechaInicio->toDateString(),
                'fecha_fin' => $fechaFin->toDateString(),
                'remotas' => $filtroRemotas,
                'confirmadas' => $filtroConfirmadas,
                'centros_incluidos' => $centrosFiltro,
            ],
            'grafica_barras' => [
                'labels' => $barras->pluck('sede')->values(),
                'values' => $barras->pluck('solicitudes_generadas')->values(),
                'total_solicitudes' => (int) $barras->sum('solicitudes_generadas'),
                'series' => $barras,
            ],
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }
}