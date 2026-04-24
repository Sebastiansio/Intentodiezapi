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

    private const MULTA_STATUS_CANDIDATES = ['state', 'estatus', 'status', 'estado', 'code_estatus'];

    private const MULTA_PARTE_CANDIDATES = ['citado_id', 'parte_id', 'solicitado_id'];

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

    private function calcularEfectividades($acuerdos, $noAcuerdosSinIncomparecencia, $incomparecencias)
    {
        $acuerdos = (int) $acuerdos;
        $noAcuerdosSinIncomparecencia = (int) $noAcuerdosSinIncomparecencia;
        $incomparecencias = (int) $incomparecencias;

        $noAcuerdosTotales = $noAcuerdosSinIncomparecencia + $incomparecencias;
        $baseFederacion = $acuerdos + $noAcuerdosTotales;
        $baseCcl = $acuerdos + $noAcuerdosSinIncomparecencia;

        return [
            'no_acuerdos_totales' => $noAcuerdosTotales,
            'tasa_conciliacion_federacion' => $baseFederacion > 0 ? round(($acuerdos / $baseFederacion) * 100, 2) : 0,
            'porcentaje_efectividad_ccl' => $baseCcl > 0 ? round(($acuerdos / $baseCcl) * 100, 2) : 0,
        ];
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

    private function getMultaExistingColumn($candidates)
    {
        if (!Schema::hasTable('multas')) {
            return null;
        }

        foreach ($candidates as $column) {
            if (Schema::hasColumn('multas', $column)) {
                return $column;
            }
        }

        return null;
    }

    private function normalizeMultaEstado($estado)
    {
        $estado = trim((string) $estado);
        return $estado !== '' ? $estado : 'sin_estado';
    }

    private function mergeConteoMap(array &$destino, array $origen)
    {
        foreach ($origen as $key => $value) {
            if (!isset($destino[$key])) {
                $destino[$key] = 0;
            }

            $destino[$key] += (int) $value;
        }
    }

    private function getMetricasMultasPorAudiencias($audienciaIds)
    {
        $audienciaIds = array_values(array_filter(array_unique(array_map(function ($id) {
            return (int) $id;
        }, is_array($audienciaIds) ? $audienciaIds : [])), function ($id) {
            return $id > 0;
        }));

        $respuesta = [
            'multas_generadas' => 0,
            'audiencias_con_multa' => 0,
            'multas_por_estado' => [],
            'por_audiencia' => [],
        ];

        if (empty($audienciaIds) || !Schema::hasTable('multas') || !Schema::hasColumn('multas', 'audiencia_id')) {
            return $respuesta;
        }

        $statusColumn = $this->getMultaExistingColumn(self::MULTA_STATUS_CANDIDATES);
        $parteColumn = $this->getMultaExistingColumn(self::MULTA_PARTE_CANDIDATES);
        $audienciaParteColumn = Schema::hasColumn('multas', 'audiencia_parte_id') ? 'audiencia_parte_id' : null;

        $query = DB::table('multas')->whereIn('audiencia_id', $audienciaIds);

        if (Schema::hasColumn('multas', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        if (Schema::hasColumn('multas', 'created_at')) {
            $query->orderByDesc('created_at');
        } elseif (Schema::hasColumn('multas', 'id')) {
            $query->orderByDesc('id');
        }

        $selects = [
            'audiencia_id',
            $statusColumn ? DB::raw("COALESCE({$statusColumn}, 'sin_estado') as multa_estado") : DB::raw("'sin_estado' as multa_estado"),
            $audienciaParteColumn ? DB::raw("{$audienciaParteColumn} as audiencia_parte_id") : DB::raw('NULL as audiencia_parte_id'),
            $parteColumn ? DB::raw("{$parteColumn} as parte_id") : DB::raw('NULL as parte_id'),
        ];

        $multas = $query->get($selects);

        foreach ($multas as $multa) {
            $audienciaId = (int) ($multa->audiencia_id ?? 0);
            if ($audienciaId <= 0) {
                continue;
            }

            $estado = $this->normalizeMultaEstado($multa->multa_estado ?? null);
            $audienciaParteId = (int) ($multa->audiencia_parte_id ?? 0);
            $parteId = (int) ($multa->parte_id ?? 0);

            if (!isset($respuesta['por_audiencia'][$audienciaId])) {
                $respuesta['por_audiencia'][$audienciaId] = [
                    'tiene_multa_generada' => true,
                    'total_multas' => 0,
                    'multas_por_estado' => [],
                    'por_audiencia_parte' => [],
                    'por_parte' => [],
                ];
            }

            $respuesta['multas_generadas']++;
            if (!isset($respuesta['multas_por_estado'][$estado])) {
                $respuesta['multas_por_estado'][$estado] = 0;
            }
            $respuesta['multas_por_estado'][$estado]++;

            $respuesta['por_audiencia'][$audienciaId]['total_multas']++;
            if (!isset($respuesta['por_audiencia'][$audienciaId]['multas_por_estado'][$estado])) {
                $respuesta['por_audiencia'][$audienciaId]['multas_por_estado'][$estado] = 0;
            }
            $respuesta['por_audiencia'][$audienciaId]['multas_por_estado'][$estado]++;

            if ($audienciaParteId > 0) {
                if (!isset($respuesta['por_audiencia'][$audienciaId]['por_audiencia_parte'][$audienciaParteId])) {
                    $respuesta['por_audiencia'][$audienciaId]['por_audiencia_parte'][$audienciaParteId] = [
                        'multa_generada' => true,
                        'multa_estado' => $estado,
                        'multas_por_estado' => [],
                    ];
                }

                if (!isset($respuesta['por_audiencia'][$audienciaId]['por_audiencia_parte'][$audienciaParteId]['multas_por_estado'][$estado])) {
                    $respuesta['por_audiencia'][$audienciaId]['por_audiencia_parte'][$audienciaParteId]['multas_por_estado'][$estado] = 0;
                }
                $respuesta['por_audiencia'][$audienciaId]['por_audiencia_parte'][$audienciaParteId]['multas_por_estado'][$estado]++;
            }

            if ($parteId > 0) {
                if (!isset($respuesta['por_audiencia'][$audienciaId]['por_parte'][$parteId])) {
                    $respuesta['por_audiencia'][$audienciaId]['por_parte'][$parteId] = [
                        'multa_generada' => true,
                        'multa_estado' => $estado,
                        'multas_por_estado' => [],
                    ];
                }

                if (!isset($respuesta['por_audiencia'][$audienciaId]['por_parte'][$parteId]['multas_por_estado'][$estado])) {
                    $respuesta['por_audiencia'][$audienciaId]['por_parte'][$parteId]['multas_por_estado'][$estado] = 0;
                }
                $respuesta['por_audiencia'][$audienciaId]['por_parte'][$parteId]['multas_por_estado'][$estado]++;
            }
        }

        $respuesta['audiencias_con_multa'] = count($respuesta['por_audiencia']);

        return $respuesta;
    }

    private function getSedesFiltro(Request $request)
    {
        $sedes = $request->query('sedes');

        if ($sedes === null || $sedes === '') {
            return $this->getCentrosPermitidos($request);
        }

        if (is_string($sedes)) {
            $sedes = explode(',', $sedes);
        }

        if (!is_array($sedes)) {
            return $this->getCentrosPermitidos($request);
        }

        $sedes = array_values(array_filter(array_map(function ($sedeId) {
            return (int) trim((string) $sedeId);
        }, $sedes), function ($sedeId) {
            return $sedeId > 0;
        }));

        if (empty($sedes)) {
            return $this->getCentrosPermitidos($request);
        }

        $sedesValidas = Centro::whereIn('id', $sedes)
            ->pluck('id')
            ->map(function ($id) {
                return (int) $id;
            })
            ->toArray();

        return !empty($sedesValidas) ? $sedesValidas : $this->getCentrosPermitidos($request);
    }

    private function getPeriodicidadConfig($periodicidad)
    {
        if ($periodicidad === 'quincena') {
            return [
                'period_key' => "TO_CHAR(s.created_at, 'YYYY-MM') || '-Q' || CASE WHEN EXTRACT(DAY FROM s.created_at) <= 15 THEN '1' ELSE '2' END",
                'period_label' => "TO_CHAR(s.created_at, 'Mon YYYY') || ' ' || CASE WHEN EXTRACT(DAY FROM s.created_at) <= 15 THEN 'Q1' ELSE 'Q2' END",
                'period_order' => "DATE_TRUNC('month', s.created_at) + CASE WHEN EXTRACT(DAY FROM s.created_at) <= 15 THEN INTERVAL '0 day' ELSE INTERVAL '15 day' END",
                'periodicidad' => 'quincena',
            ];
        }

        return [
            'period_key' => "TO_CHAR(s.created_at, 'YYYY-MM')",
            'period_label' => "TO_CHAR(s.created_at, 'Mon YYYY')",
            'period_order' => "DATE_TRUNC('month', s.created_at)",
            'periodicidad' => 'mes',
        ];
    }

    private function getDimensionConfig($dimension)
    {
        if ($dimension === 'objeto_solicitud') {
            $objetoColumns = [];
            if (Schema::hasTable('objeto_solicitudes')) {
                if (Schema::hasColumn('objeto_solicitudes', 'nombre')) {
                    $objetoColumns[] = 'os.nombre';
                }
                if (Schema::hasColumn('objeto_solicitudes', 'name')) {
                    $objetoColumns[] = 'os.name';
                }
            }

            if (empty($objetoColumns)) {
                $objetoColumns[] = "'Sin objeto'";
            }

            return [
                'dimension' => 'objeto_solicitud',
                'id_select' => 'os.id',
                'label_select' => 'COALESCE(' . implode(', ', $objetoColumns) . ", 'Sin objeto')",
                'joins' => function ($query) {
                    return $query
                        ->leftJoin('objeto_solicitud_solicitud as oss', function ($join) {
                            $join->on('oss.solicitud_id', '=', 's.id');
                        })
                        ->leftJoin('objeto_solicitudes as os', 'os.id', '=', 'oss.objeto_solicitud_id');
                },
            ];
        }

        if ($dimension === 'conciliador') {
            return [
                'dimension' => 'conciliador',
                'id_select' => 'COALESCE(ca.conciliador_id, a.conciliador_id)',
                'label_select' => "COALESCE(TRIM(CONCAT(COALESCE(p.nombre, ''), ' ', COALESCE(p.primer_apellido, ''), ' ', COALESCE(p.segundo_apellido, ''))), 'Sin conciliador')",
                'joins' => function ($query) {
                    return $query
                        ->leftJoin('expedientes as e', 'e.solicitud_id', '=', 's.id')
                        ->leftJoin('audiencias as a', 'a.expediente_id', '=', 'e.id')
                        ->leftJoin('conciliadores_audiencias as ca', function ($join) {
                            $join->on('ca.audiencia_id', '=', 'a.id')
                                ->whereNull('ca.deleted_at');
                        })
                        ->leftJoin('conciliadores as con', function ($join) {
                            $join->on('con.id', '=', DB::raw('COALESCE(ca.conciliador_id, a.conciliador_id)'));
                        })
                        ->leftJoin('personas as p', 'p.id', '=', 'con.persona_id');
                },
            ];
        }

        return [
            'dimension' => 'sede',
            'id_select' => 's.centro_id',
            'label_select' => "COALESCE(c.nombre, 'Sede no identificada')",
            'joins' => function ($query) {
                return $query->leftJoin('centros as c', 'c.id', '=', 's.centro_id');
            },
        ];
    }

    private function getSolicitudMontoExpression()
    {
        $candidates = ['monto', 'monto_total', 'cuantia', 'cantidad_reclamada', 'salario'];

        foreach ($candidates as $column) {
            if (Schema::hasColumn('solicitudes', $column)) {
                return "COALESCE(s.{$column}, 0)";
            }
        }

        return '0';
    }

    private function getStatsBaseFiltros(Request $request)
    {
        $sedesFiltro = $this->getSedesFiltro($request);
        $fechaInicio = Carbon::parse($request->query('fecha_inicio', Carbon::now()->startOfYear()->toDateString()))->startOfDay();
        $fechaFin = Carbon::parse($request->query('fecha_fin', Carbon::now()->toDateString()))->endOfDay();

        $filtroRemotas = $this->parseFiltroBooleano($request->query('remotas', 'todas'));
        $filtroConfirmadas = $this->parseFiltroBooleano($request->query('confirmadas', 'todas'));
        $filtroInmediatas = $this->parseFiltroBooleano($request->query('inmediatas', 'todas'));

        $tipoSolicitud = $request->query('tipo_solicitud_id');
        $tipoSolicitud = ($tipoSolicitud !== null && $tipoSolicitud !== '') ? (int) $tipoSolicitud : null;
        if ($tipoSolicitud !== null && $tipoSolicitud <= 0) {
            $tipoSolicitud = null;
        }

        return [
            'sedes' => $sedesFiltro,
            'fecha_inicio' => $fechaInicio,
            'fecha_fin' => $fechaFin,
            'remotas' => $filtroRemotas,
            'confirmadas' => $filtroConfirmadas,
            'inmediatas' => $filtroInmediatas,
            'tipo_solicitud_id' => $tipoSolicitud,
        ];
    }

    private function applyStatsBaseFiltros($query, $filtros, $alias = 's')
    {
        $query->whereNull("{$alias}.deleted_at")
            ->whereIn("{$alias}.centro_id", $filtros['sedes'])
            ->whereBetween("{$alias}.created_at", [$filtros['fecha_inicio'], $filtros['fecha_fin']])
            ->when($filtros['remotas'] !== null, function ($q) use ($filtros, $alias) {
                $q->where("{$alias}.virtual", $filtros['remotas']);
            })
            ->when($filtros['confirmadas'] !== null, function ($q) use ($filtros, $alias) {
                $q->where("{$alias}.ratificada", $filtros['confirmadas']);
            })
            ->when($filtros['inmediatas'] !== null, function ($q) use ($filtros, $alias) {
                $q->where("{$alias}.inmediata", $filtros['inmediatas']);
            })
            ->when($filtros['tipo_solicitud_id'] !== null, function ($q) use ($filtros, $alias) {
                $q->where("{$alias}.tipo_solicitud_id", $filtros['tipo_solicitud_id']);
            });

        return $query;
    }

    private function getRangoMensualFiltro(Request $request, $fechaInicioDefault, $fechaFinDefault)
    {
        $mesInicioParam = $request->query('mes_inicio');
        $mesFinParam = $request->query('mes_fin');

        if ($mesInicioParam === null && $mesFinParam === null) {
            return [
                'fecha_inicio' => $fechaInicioDefault,
                'fecha_fin' => $fechaFinDefault,
                'anio' => null,
                'mes_inicio' => null,
                'mes_fin' => null,
            ];
        }

        $anio = (int) $request->query('anio', Carbon::now()->year);
        $mesInicio = (int) ($mesInicioParam ?? 1);
        $mesFin = (int) ($mesFinParam ?? Carbon::now()->month);

        $mesInicio = max(1, min(12, $mesInicio));
        $mesFin = max(1, min(12, $mesFin));

        if ($mesInicio > $mesFin) {
            $tmp = $mesInicio;
            $mesInicio = $mesFin;
            $mesFin = $tmp;
        }

        return [
            'fecha_inicio' => Carbon::create($anio, $mesInicio, 1)->startOfDay(),
            'fecha_fin' => Carbon::create($anio, $mesFin, 1)->endOfMonth()->endOfDay(),
            'anio' => $anio,
            'mes_inicio' => $mesInicio,
            'mes_fin' => $mesFin,
        ];
    }

    /**
     * Audiencias desagregadas por genero y terminacion, filtrando por datos de solicitud.
     */
    public function getStatsAudienciasGeneroTerminacion(Request $request)
    {
        $filtros = $this->getStatsBaseFiltros($request);

        $rangoMensual = $this->getRangoMensualFiltro($request, $filtros['fecha_inicio'], $filtros['fecha_fin']);
        $filtros['fecha_inicio'] = $rangoMensual['fecha_inicio'];
        $filtros['fecha_fin'] = $rangoMensual['fecha_fin'];

        $generoLabelExpr = "'Sin genero'";
        if (Schema::hasTable('generos')) {
            if (Schema::hasColumn('generos', 'nombre')) {
                $generoLabelExpr = "COALESCE(g.nombre, 'Sin genero')";
            } elseif (Schema::hasColumn('generos', 'name')) {
                $generoLabelExpr = "COALESCE(g.name, 'Sin genero')";
            }
        }

        $terminacionKeyExpr = "CASE
            WHEN a.resolucion_id = 1 THEN 'convenios'
            WHEN a.resolucion_id = 2 THEN 'reagendas'
            WHEN a.resolucion_id = 3 AND a.tipo_terminacion_audiencia_id = 3 THEN 'incomparecencia'
            WHEN a.resolucion_id = 3 THEN 'no_convenios'
            WHEN a.resolucion_id = 4 THEN 'archivados'
            ELSE 'pendientes'
        END";

        $terminacionLabelExpr = "CASE
            WHEN a.resolucion_id = 1 THEN 'Convenios'
            WHEN a.resolucion_id = 2 THEN 'Reagendas'
            WHEN a.resolucion_id = 3 AND a.tipo_terminacion_audiencia_id = 3 THEN 'Incomparecencia'
            WHEN a.resolucion_id = 3 THEN 'No Convenios'
            WHEN a.resolucion_id = 4 THEN 'Archivados'
            ELSE 'Pendientes'
        END";

        $terminacionCatalogo = collect([
            ['id' => 'convenios', 'label' => 'Convenios'],
            ['id' => 'no_convenios', 'label' => 'No Convenios'],
            ['id' => 'incomparecencia', 'label' => 'Incomparecencia'],
            ['id' => 'reagendas', 'label' => 'Reagendas'],
            ['id' => 'archivados', 'label' => 'Archivados'],
            ['id' => 'pendientes', 'label' => 'Pendientes'],
        ]);

        $query = DB::table('solicitudes as s')
            ->join('expedientes as e', function ($join) {
                $join->on('e.solicitud_id', '=', 's.id')
                    ->whereNull('e.deleted_at');
            })
            ->join('audiencias as a', function ($join) {
                $join->on('a.expediente_id', '=', 'e.id')
                    ->whereNull('a.deleted_at');
            })
            ->join('partes as ps', function ($join) {
                $join->on('ps.solicitud_id', '=', 's.id')
                    ->whereNull('ps.deleted_at');
                $join->where(function ($partesQuery) {
                    $partesQuery->where(function ($q) {
                        $q->where('s.tipo_solicitud_id', 1)
                            ->where('ps.tipo_parte_id', 1);
                    })->orWhere(function ($q) {
                        $q->whereIn('s.tipo_solicitud_id', [2, 3])
                            ->where('ps.tipo_parte_id', 2);
                    });
                });
            })
            ->leftJoin('generos as g', 'g.id', '=', 'ps.genero_id');

        $this->applyStatsBaseFiltros($query, $filtros, 's');

        $series = $query
            ->selectRaw('ps.genero_id as genero_id')
            ->selectRaw("{$generoLabelExpr} as genero")
            ->selectRaw("{$terminacionKeyExpr} as terminacion_id")
            ->selectRaw("{$terminacionLabelExpr} as terminacion")
            ->selectRaw('EXTRACT(MONTH FROM s.created_at) as mes')
            ->selectRaw('COUNT(DISTINCT (a.id, ps.id)) as total_audiencias')
            ->groupByRaw("ps.genero_id, {$generoLabelExpr}, {$terminacionKeyExpr}, {$terminacionLabelExpr}, EXTRACT(MONTH FROM s.created_at)")
            ->orderBy('genero', 'asc')
            ->orderBy('mes', 'asc')
            ->orderBy('terminacion', 'asc')
            ->get()
            ->map(function ($row) {
                return [
                    'genero_id' => $row->genero_id ? (int) $row->genero_id : null,
                    'genero' => $row->genero,
                    'terminacion_id' => $row->terminacion_id,
                    'terminacion' => $row->terminacion,
                    'mes' => (int) $row->mes,
                    'total_audiencias' => (int) $row->total_audiencias,
                ];
            })
            ->values();

        $porGenero = $series->groupBy('genero')->map(function ($items, $genero) use ($terminacionCatalogo) {
            $terminacionesPorGenero = $terminacionCatalogo->map(function ($cat) use ($items) {
                // Sumar todos los meses correspondientes a cada terminación
                $totalCat = $items->where('terminacion_id', $cat['id'])->sum('total_audiencias');

                return [
                    'terminacion_id' => $cat['id'],
                    'terminacion' => $cat['label'],
                    'total_audiencias' => (int) $totalCat,
                ];
            })->values();

            return [
                'genero' => $genero,
                'total_audiencias' => (int) $items->sum('total_audiencias'),
                'terminaciones' => $terminacionesPorGenero,
            ];
        })->values();

        $terminacionesPorId = $series->groupBy('terminacion_id')->map(function ($items) {
            return [
                'terminacion_id' => $items->first()['terminacion_id'],
                'terminacion' => $items->first()['terminacion'],
                'total_audiencias' => (int) $items->sum('total_audiencias'),
            ];
        });

        $terminaciones = $terminacionCatalogo->map(function ($cat) use ($terminacionesPorId) {
            $item = $terminacionesPorId->get($cat['id']);

            return [
                'terminacion_id' => $cat['id'],
                'terminacion' => $cat['label'],
                'total_audiencias' => (int) ($item['total_audiencias'] ?? 0),
            ];
        })->values();

        $generosOrdenados = $porGenero->pluck('genero')->values();
        $seriesIndexada = [];
        foreach ($series as $item) {
            if (!isset($seriesIndexada[$item['terminacion_id']][$item['genero']])) {
                $seriesIndexada[$item['terminacion_id']][$item['genero']] = 0;
            }
            // Importante: Sumar todo porque vienen datos de varios meses
            $seriesIndexada[$item['terminacion_id']][$item['genero']] += $item['total_audiencias'];
        }

        $datasets = $terminacionCatalogo->map(function ($terminacionItem) use ($generosOrdenados, $seriesIndexada) {
            $terminacionId = $terminacionItem['id'];
            $data = [];

            foreach ($generosOrdenados as $genero) {
                $data[] = (int) ($seriesIndexada[$terminacionId][$genero] ?? 0);
            }

            return [
                'id' => $terminacionId,
                'label' => $terminacionItem['label'],
                'data' => $data,
            ];
        })->values();

        return response()->json([
            'filters' => [
                'sedes' => $filtros['sedes'],
                'fecha_inicio' => $filtros['fecha_inicio']->toDateString(),
                'fecha_fin' => $filtros['fecha_fin']->toDateString(),
                'anio' => $rangoMensual['anio'],
                'mes_inicio' => $rangoMensual['mes_inicio'],
                'mes_fin' => $rangoMensual['mes_fin'],
                'remotas' => $filtros['remotas'],
                'confirmadas' => $filtros['confirmadas'],
                'inmediatas' => $filtros['inmediatas'],
                'tipo_solicitud_id' => $filtros['tipo_solicitud_id'],
            ],
            'totals' => [
                'total_audiencias' => (int) $series->sum('total_audiencias'),
                'generos' => $porGenero,
                'terminaciones' => $terminaciones,
                'terminacion_catalogo' => $terminacionCatalogo->values(),
            ],
            'chart_pivot' => [
                'x_axis' => $generosOrdenados,
                'datasets' => $datasets,
            ],
            'series' => $series,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Conteos de solicitudes por sede.
     */
    public function getStatsSolicitudesConteos(Request $request)
    {
        $filtros = $this->getStatsBaseFiltros($request);

        $query = DB::table('solicitudes as s')
            ->leftJoin('centros as c', 'c.id', '=', 's.centro_id');

        $this->applyStatsBaseFiltros($query, $filtros, 's');

        $series = $query
            ->selectRaw('s.centro_id as sede_id')
            ->selectRaw("COALESCE(c.nombre, 'Sede no identificada') as sede")
            ->selectRaw('COUNT(DISTINCT s.id) as total_solicitudes')
            ->selectRaw('COUNT(DISTINCT CASE WHEN s.ratificada = true THEN s.id END) as total_confirmadas')
            ->selectRaw('COUNT(DISTINCT CASE WHEN s.ratificada = false THEN s.id END) as total_no_confirmadas')
            ->selectRaw('COUNT(DISTINCT CASE WHEN s.virtual = true THEN s.id END) as total_remotas')
            ->selectRaw('COUNT(DISTINCT CASE WHEN s.virtual = false THEN s.id END) as total_presenciales')
            ->selectRaw('COUNT(DISTINCT CASE WHEN s.tipo_solicitud_id = 1 THEN s.id END) as total_trabajador')
            ->selectRaw('COUNT(DISTINCT CASE WHEN s.tipo_solicitud_id = 2 THEN s.id END) as total_patron_individual')
            ->selectRaw('COUNT(DISTINCT CASE WHEN s.tipo_solicitud_id = 3 THEN s.id END) as total_patron_colectivo')
            ->groupBy('s.centro_id', 'c.nombre')
            ->orderBy('sede', 'asc')
            ->get()
            ->map(function ($row) {
                return [
                    'sede_id' => $row->sede_id ? (int) $row->sede_id : null,
                    'sede' => $row->sede,
                    'total_solicitudes' => (int) $row->total_solicitudes,
                    'total_confirmadas' => (int) $row->total_confirmadas,
                    'total_no_confirmadas' => (int) $row->total_no_confirmadas,
                    'total_remotas' => (int) $row->total_remotas,
                    'total_presenciales' => (int) $row->total_presenciales,
                    'total_trabajador' => (int) $row->total_trabajador,
                    'total_patron_individual' => (int) $row->total_patron_individual,
                    'total_patron_colectivo' => (int) $row->total_patron_colectivo,
                ];
            })
            ->values();

        return response()->json([
            'filters' => [
                'sedes' => $filtros['sedes'],
                'fecha_inicio' => $filtros['fecha_inicio']->toDateString(),
                'fecha_fin' => $filtros['fecha_fin']->toDateString(),
                'remotas' => $filtros['remotas'],
                'confirmadas' => $filtros['confirmadas'],
                'inmediatas' => $filtros['inmediatas'],
                'tipo_solicitud_id' => $filtros['tipo_solicitud_id'],
            ],
            'totals' => [
                'total_solicitudes' => (int) $series->sum('total_solicitudes'),
                'total_confirmadas' => (int) $series->sum('total_confirmadas'),
                'total_no_confirmadas' => (int) $series->sum('total_no_confirmadas'),
                'total_remotas' => (int) $series->sum('total_remotas'),
                'total_presenciales' => (int) $series->sum('total_presenciales'),
            ],
            'series' => $series,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Conteos de audiencias y resoluciones por sede.
     */
    public function getStatsAudienciasConteos(Request $request)
    {
        $filtros = $this->getStatsBaseFiltros($request);

        $query = DB::table('solicitudes as s')
            ->leftJoin('centros as c', 'c.id', '=', 's.centro_id')
            ->leftJoin('expedientes as e', function ($join) {
                $join->on('e.solicitud_id', '=', 's.id')
                    ->whereNull('e.deleted_at');
            })
            ->leftJoin('audiencias as a', function ($join) {
                $join->on('a.expediente_id', '=', 'e.id')
                    ->whereNull('a.deleted_at');
            });

        $this->applyStatsBaseFiltros($query, $filtros, 's');

        $series = $query
            ->selectRaw('s.centro_id as sede_id')
            ->selectRaw("COALESCE(c.nombre, 'Sede no identificada') as sede")
            ->selectRaw('COUNT(DISTINCT a.id) as total_audiencias')
            ->selectRaw('COUNT(DISTINCT CASE WHEN a.resolucion_id = 1 THEN a.id END) as hubo_convenio')
            ->selectRaw('COUNT(DISTINCT CASE WHEN a.resolucion_id = 2 THEN a.id END) as reagendada')
            ->selectRaw('COUNT(DISTINCT CASE WHEN a.resolucion_id = 3 THEN a.id END) as no_hubo_convenio')
            ->selectRaw('COUNT(DISTINCT CASE WHEN a.resolucion_id = 4 THEN a.id END) as archivada')
            ->selectRaw('COUNT(DISTINCT CASE WHEN a.tipo_terminacion_audiencia_id = 3 THEN a.id END) as incomparecencia')
            ->groupBy('s.centro_id', 'c.nombre')
            ->orderBy('sede', 'asc')
            ->get()
            ->map(function ($row) {
                $totalAudiencias = (int) $row->total_audiencias;
                $incomparecencia = (int) $row->incomparecencia;

                return [
                    'sede_id' => $row->sede_id ? (int) $row->sede_id : null,
                    'sede' => $row->sede,
                    'total_audiencias' => $totalAudiencias,
                    'hubo_convenio' => (int) $row->hubo_convenio,
                    'reagendada' => (int) $row->reagendada,
                    'no_hubo_convenio' => (int) $row->no_hubo_convenio,
                    'archivada' => (int) $row->archivada,
                    'incomparecencia' => $incomparecencia,
                    'tasa_incomparecencia' => $totalAudiencias > 0 ? round(($incomparecencia / $totalAudiencias) * 100, 2) : 0,
                ];
            })
            ->values();

        $totalAudiencias = (int) $series->sum('total_audiencias');
        $totalIncomparecencias = (int) $series->sum('incomparecencia');

        return response()->json([
            'filters' => [
                'sedes' => $filtros['sedes'],
                'fecha_inicio' => $filtros['fecha_inicio']->toDateString(),
                'fecha_fin' => $filtros['fecha_fin']->toDateString(),
                'remotas' => $filtros['remotas'],
                'confirmadas' => $filtros['confirmadas'],
                'inmediatas' => $filtros['inmediatas'],
                'tipo_solicitud_id' => $filtros['tipo_solicitud_id'],
            ],
            'totals' => [
                'total_audiencias' => $totalAudiencias,
                'hubo_convenio' => (int) $series->sum('hubo_convenio'),
                'reagendada' => (int) $series->sum('reagendada'),
                'no_hubo_convenio' => (int) $series->sum('no_hubo_convenio'),
                'archivada' => (int) $series->sum('archivada'),
                'incomparecencia' => $totalIncomparecencias,
                'tasa_incomparecencia' => $totalAudiencias > 0 ? round(($totalIncomparecencias / $totalAudiencias) * 100, 2) : 0,
            ],
            'series' => $series,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Conteos de productividad por conciliador.
     */
    public function getStatsConciliadoresConteos(Request $request)
    {
        $filtros = $this->getStatsBaseFiltros($request);

        $asignacionesBase = DB::table('audiencias as a')
            ->selectRaw('a.id as audiencia_id, a.expediente_id, a.conciliador_id, a.resolucion_id, a.tipo_terminacion_audiencia_id')
            ->whereNull('a.deleted_at')
            ->whereNotNull('a.conciliador_id');

        $asignacionesSecundarias = DB::table('audiencias as a')
            ->join('conciliadores_audiencias as ca', function ($join) {
                $join->on('ca.audiencia_id', '=', 'a.id')
                    ->whereNull('ca.deleted_at');
            })
            ->selectRaw('a.id as audiencia_id, a.expediente_id, ca.conciliador_id, a.resolucion_id, a.tipo_terminacion_audiencia_id')
            ->whereNull('a.deleted_at');

        $asignaciones = $asignacionesBase->unionAll($asignacionesSecundarias);

        $query = DB::table('solicitudes as s')
            ->join('expedientes as e', function ($join) {
                $join->on('e.solicitud_id', '=', 's.id')
                    ->whereNull('e.deleted_at');
            })
            ->joinSub($asignaciones, 'ac', function ($join) {
                $join->on('ac.expediente_id', '=', 'e.id');
            })
            ->join('conciliadores as con', 'con.id', '=', 'ac.conciliador_id')
            ->leftJoin('personas as p', 'p.id', '=', 'con.persona_id')
            ->leftJoin('centros as c', 'c.id', '=', 'con.centro_id');

        $this->applyStatsBaseFiltros($query, $filtros, 's');

        $series = $query
            ->selectRaw('ac.conciliador_id as conciliador_id')
            ->selectRaw("COALESCE(TRIM(CONCAT(COALESCE(p.nombre, ''), ' ', COALESCE(p.primer_apellido, ''), ' ', COALESCE(p.segundo_apellido, ''))), CONCAT('Conciliador #', ac.conciliador_id)) as conciliador")
            ->selectRaw("COALESCE(c.nombre, 'Sin sede') as sede")
            ->selectRaw('COUNT(DISTINCT ac.audiencia_id) as total_audiencias')
            ->selectRaw('COUNT(DISTINCT CASE WHEN ac.resolucion_id = 1 THEN ac.audiencia_id END) as convenios')
            ->selectRaw('COUNT(DISTINCT CASE WHEN ac.resolucion_id IN (2, 3) AND COALESCE(ac.tipo_terminacion_audiencia_id, 0) <> 3 THEN ac.audiencia_id END) as no_convenios')
            ->selectRaw('COUNT(DISTINCT CASE WHEN ac.resolucion_id = 4 THEN ac.audiencia_id END) as archivadas')
            ->selectRaw('COUNT(DISTINCT CASE WHEN ac.resolucion_id IN (2, 3) AND ac.tipo_terminacion_audiencia_id = 3 THEN ac.audiencia_id END) as incomparecencias')
            ->groupBy('ac.conciliador_id', 'p.nombre', 'p.primer_apellido', 'p.segundo_apellido', 'c.nombre')
            ->orderBy('convenios', 'desc')
            ->get()
            ->map(function ($row) {
                $convenios = (int) $row->convenios;
                $noConvenios = (int) $row->no_convenios;
                $incomparecencias = (int) $row->incomparecencias;
                $efectividades = $this->calcularEfectividades($convenios, $noConvenios, $incomparecencias);

                return [
                    'conciliador_id' => (int) $row->conciliador_id,
                    'conciliador' => trim($row->conciliador),
                    'sede' => $row->sede,
                    'total_audiencias' => (int) $row->total_audiencias,
                    'convenios' => $convenios,
                    'no_convenios' => $noConvenios,
                    'archivadas' => (int) $row->archivadas,
                    'incomparecencias' => $incomparecencias,
                    'efectividad_conciliacion' => $efectividades['porcentaje_efectividad_ccl'],
                    'tasa_conciliacion_federacion' => $efectividades['tasa_conciliacion_federacion'],
                    'porcentaje_efectividad_ccl' => $efectividades['porcentaje_efectividad_ccl'],
                ];
            })
            ->values();

        return response()->json([
            'filters' => [
                'sedes' => $filtros['sedes'],
                'fecha_inicio' => $filtros['fecha_inicio']->toDateString(),
                'fecha_fin' => $filtros['fecha_fin']->toDateString(),
                'remotas' => $filtros['remotas'],
                'confirmadas' => $filtros['confirmadas'],
                'inmediatas' => $filtros['inmediatas'],
                'tipo_solicitud_id' => $filtros['tipo_solicitud_id'],
            ],
            'totals' => [
                'total_conciliadores' => $series->count(),
                'total_audiencias' => (int) $series->sum('total_audiencias'),
                'total_convenios' => (int) $series->sum('convenios'),
                'total_no_convenios' => (int) $series->sum('no_convenios'),
                'total_incomparecencias' => (int) $series->sum('incomparecencias'),
            ],
            'ranking' => $series,
        ], 200, [], JSON_UNESCAPED_UNICODE);
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

        $metricasMultas = $this->getMetricasMultasPorAudiencias($audiencias->pluck('id')->all());

        $audienciasTransformadas = $audiencias->map(function($a) use ($metricasMultas) {
            $sala = $a->salasAudiencias && $a->salasAudiencias->count() > 0 
                    ? $a->salasAudiencias->first()->sala->sala 
                    : 'Sin sala asignada';

            $multasAudiencia = $metricasMultas['por_audiencia'][$a->id] ?? null;
            $multasPorAudienciaParte = $multasAudiencia['por_audiencia_parte'] ?? [];
            $multasPorParte = $multasAudiencia['por_parte'] ?? [];
                
                $notificacionesPartes = [];
                if ($a->audienciaParte) {
                    $notificacionesPartes = $a->audienciaParte->map(function ($ap) use ($multasPorAudienciaParte, $multasPorParte) {
                        $multaGenerada = null;

                        if (isset($multasPorAudienciaParte[$ap->id])) {
                            $multaGenerada = $multasPorAudienciaParte[$ap->id];
                        } elseif (isset($multasPorParte[$ap->parte_id])) {
                            $multaGenerada = $multasPorParte[$ap->parte_id];
                        }

                        return [
                            'parte_id' => $ap->parte_id,
                            'tipo_parte' => $ap->parte ? $ap->parte->tipo_parte_id : null,
                            'tipo_notificacion' => $ap->tipo_notificacion ? $ap->tipo_notificacion->nombre : 'Sin tipo',
                            'fecha_notificacion' => $ap->fecha_notificacion,
                            'estatus_notificacion' => $ap->finalizado ?: 'Pendiente',
                            'detalle_notificacion' => $ap->detalle,
                            'multa' => (bool) $ap->multa,
                            'multa_generada' => (bool) ($multaGenerada['multa_generada'] ?? false),
                            'multa_estado' => $multaGenerada['multa_estado'] ?? null,
                            'multas_por_estado' => $multaGenerada['multas_por_estado'] ?? []
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
                    'multas' => [
                        'tiene_multa_generada' => (bool) ($multasAudiencia['tiene_multa_generada'] ?? false),
                        'total_multas_generadas' => (int) ($multasAudiencia['total_multas'] ?? 0),
                        'multas_por_estado' => $multasAudiencia['multas_por_estado'] ?? [],
                    ],
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

        $metricasMultas = $this->getMetricasMultasPorAudiencias($audiencias->pluck('id')->all());

        $efectividades = $this->calcularEfectividades($convenios, $noConvenios, $noConveniosIncomparecencia);

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
            'multas_generadas' => (int) $metricasMultas['multas_generadas'],
            'audiencias_con_multa' => (int) $metricasMultas['audiencias_con_multa'],
            'multas_por_estado' => $metricasMultas['multas_por_estado'],
            'porcentaje_efectividad_general' => $efectividades['tasa_conciliacion_federacion'],
            'porcentaje_efectividad_conciliacion' => $efectividades['porcentaje_efectividad_ccl'],
            'tasa_conciliacion_federacion' => $efectividades['tasa_conciliacion_federacion'],
            'porcentaje_efectividad_ccl' => $efectividades['porcentaje_efectividad_ccl']
        ]);
    }

    /**
     * Obtiene un resumen general agrupado por días en un periodo determinado.
     * Muestra total de audiencias en el día y agrupación por resoluciones.
     */
    public function getResumenGeneral(Request $request)
    {
        ini_set('max_execution_time', 300);
        ini_set('memory_limit', '1024M');

        $centrosFiltro = $this->getCentrosPermitidos($request);
        $conciliadoresActivos = $this->getConciliadoresActivos();

        try {
            $fechaInicio = Carbon::parse($request->query('fecha_inicio', Carbon::now()->startOfWeek()->toDateString()))->toDateString();
            $fechaFin = Carbon::parse($request->query('fecha_fin', Carbon::now()->endOfWeek()->toDateString()))->toDateString();
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Formato de fecha inválido. Use YYYY-MM-DD en fecha_inicio y fecha_fin.'
            ], 422);
        }

        if ($fechaInicio > $fechaFin) {
            return response()->json([
                'error' => 'fecha_inicio no puede ser mayor que fecha_fin.'
            ], 422);
        }
        
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

        $resumenDiarioIndexado = [];
        $resumenSedesIndexado = [];

        $totalGeneral = 0;
        $totalGeneralInmediatas = 0;
        $resolucionesGenerales = [];
        $resolucionesGeneralesPorParte = [];
        $estatusNotificacionesGenerales = [];
        $multasGenerales = 0;
        $audienciasConMultaGenerales = 0;
        $multasPorEstadoGenerales = [];

        $query->select('id', 'fecha_audiencia', 'resolucion_id', 'conciliador_id', 'expediente_id', 'tipo_terminacion_audiencia_id')
            ->with([
                'resolucion:id,nombre',
                'conciliador:id,centro_id',
                'conciliador.centro:id,nombre',
                'conciliadoresAudiencias:id,audiencia_id,conciliador_id',
                'conciliadoresAudiencias.conciliador:id,centro_id',
                'conciliadoresAudiencias.conciliador.centro:id,nombre',
                'audienciaParte:id,audiencia_id,finalizado,parte_id',
                'audienciaParte.parte:id,tipo_parte_id',
                'expediente:id,solicitud_id',
                'expediente.solicitud:id,inmediata',
            ])
            ->chunkById(300, function ($audienciasLote) use (
                &$resumenDiarioIndexado,
                &$resumenSedesIndexado,
                &$totalGeneral,
                &$totalGeneralInmediatas,
                &$resolucionesGenerales,
                &$resolucionesGeneralesPorParte,
                &$estatusNotificacionesGenerales,
                &$multasGenerales,
                &$audienciasConMultaGenerales,
                &$multasPorEstadoGenerales
            ) {
                $metricasMultasLote = $this->getMetricasMultasPorAudiencias($audienciasLote->pluck('id')->all());
                $multasPorAudienciaLote = $metricasMultasLote['por_audiencia'] ?? [];

                foreach ($audienciasLote as $audiencia) {
                    $fecha = $audiencia->fecha_audiencia
                        ? Carbon::parse($audiencia->fecha_audiencia)->toDateString()
                        : 'Sin fecha';

                    $metricasMultaAudiencia = $multasPorAudienciaLote[$audiencia->id] ?? null;
                    $totalMultasAudiencia = (int) ($metricasMultaAudiencia['total_multas'] ?? 0);
                    $multasEstadoAudiencia = $metricasMultaAudiencia['multas_por_estado'] ?? [];
                    $tieneMultaAudiencia = $totalMultasAudiencia > 0;

                    $esInmediata = (bool) (
                        $audiencia->expediente
                        && $audiencia->expediente->solicitud
                        && $audiencia->expediente->solicitud->inmediata
                    );

                    $nombreResolucion = 'Sin resolución';
                    if (in_array((int) $audiencia->resolucion_id, [2, 3], true) && (int) $audiencia->tipo_terminacion_audiencia_id === 3) {
                        $nombreResolucion = 'No hubo convenio por incomparecencia';
                    } elseif ($audiencia->resolucion && $audiencia->resolucion->nombre) {
                        $nombreResolucion = $audiencia->resolucion->nombre;
                    }

                    $categoriaResolucion = 'sin_resolucion';
                    if ((int) $audiencia->resolucion_id === 1) {
                        $categoriaResolucion = 'convenios';
                    } elseif (in_array((int) $audiencia->resolucion_id, [2, 3], true)) {
                        $categoriaResolucion = (int) $audiencia->tipo_terminacion_audiencia_id === 3
                            ? 'no_convenios_incomparecencia'
                            : 'no_convenios';
                    } elseif ((int) $audiencia->resolucion_id === 4) {
                        $categoriaResolucion = 'archivados';
                    }

                    $pesoPorParte = $this->getPesoAudienciaPorParte($audiencia);

                    $sede = 'Sede no identificada';
                    if ($audiencia->conciliador && $audiencia->conciliador->centro) {
                        $sede = $audiencia->conciliador->centro->nombre;
                    } elseif ($audiencia->conciliadoresAudiencias && $audiencia->conciliadoresAudiencias->count() > 0) {
                        $asignacion = $audiencia->conciliadoresAudiencias->first();
                        if ($asignacion && $asignacion->conciliador && $asignacion->conciliador->centro) {
                            $sede = $asignacion->conciliador->centro->nombre;
                        }
                    }

                    if (!isset($resumenDiarioIndexado[$fecha])) {
                        $resumenDiarioIndexado[$fecha] = [
                            'fecha' => $fecha,
                            'total_audiencias' => 0,
                            'total_audiencias_inmediatas' => 0,
                            'total_audiencias_ordinarias' => 0,
                            'resoluciones' => [],
                            'resoluciones_por_parte' => [],
                            'estatus_notificaciones' => [],
                            'multas_generadas' => 0,
                            'audiencias_con_multa' => 0,
                            'multas_por_estado' => [],
                        ];
                    }

                    if (!isset($resumenSedesIndexado[$sede])) {
                        $resumenSedesIndexado[$sede] = [
                            'sede' => $sede,
                            'total_audiencias' => 0,
                            'total_audiencias_inmediatas' => 0,
                            'total_audiencias_ordinarias' => 0,
                            'resoluciones' => [],
                            'resoluciones_por_parte' => [],
                            'estatus_notificaciones' => [],
                            'multas_generadas' => 0,
                            'audiencias_con_multa' => 0,
                            'multas_por_estado' => [],
                            'desglose_resoluciones' => [
                                'convenios' => 0,
                                'no_convenios' => 0,
                                'no_convenios_incomparecencia' => 0,
                                'archivados' => 0,
                                'sin_resolucion' => 0,
                            ],
                            'desglose_resoluciones_por_parte' => [
                                'convenios' => 0,
                                'no_convenios' => 0,
                                'no_convenios_incomparecencia' => 0,
                                'archivados' => 0,
                                'sin_resolucion' => 0,
                            ],
                        ];
                    }

                    $resumenDiarioIndexado[$fecha]['total_audiencias']++;
                    $resumenSedesIndexado[$sede]['total_audiencias']++;
                    $totalGeneral++;

                    if ($tieneMultaAudiencia) {
                        $resumenDiarioIndexado[$fecha]['audiencias_con_multa']++;
                        $resumenSedesIndexado[$sede]['audiencias_con_multa']++;
                        $audienciasConMultaGenerales++;
                    }

                    if ($totalMultasAudiencia > 0) {
                        $resumenDiarioIndexado[$fecha]['multas_generadas'] += $totalMultasAudiencia;
                        $resumenSedesIndexado[$sede]['multas_generadas'] += $totalMultasAudiencia;
                        $multasGenerales += $totalMultasAudiencia;

                        $this->mergeConteoMap($resumenDiarioIndexado[$fecha]['multas_por_estado'], $multasEstadoAudiencia);
                        $this->mergeConteoMap($resumenSedesIndexado[$sede]['multas_por_estado'], $multasEstadoAudiencia);
                        $this->mergeConteoMap($multasPorEstadoGenerales, $multasEstadoAudiencia);
                    }

                    if ($esInmediata) {
                        $resumenDiarioIndexado[$fecha]['total_audiencias_inmediatas']++;
                        $resumenSedesIndexado[$sede]['total_audiencias_inmediatas']++;
                        $totalGeneralInmediatas++;
                    }

                    if (!isset($resumenDiarioIndexado[$fecha]['resoluciones'][$nombreResolucion])) {
                        $resumenDiarioIndexado[$fecha]['resoluciones'][$nombreResolucion] = 0;
                    }
                    $resumenDiarioIndexado[$fecha]['resoluciones'][$nombreResolucion]++;

                    if (!isset($resumenDiarioIndexado[$fecha]['resoluciones_por_parte'][$nombreResolucion])) {
                        $resumenDiarioIndexado[$fecha]['resoluciones_por_parte'][$nombreResolucion] = 0;
                    }
                    $resumenDiarioIndexado[$fecha]['resoluciones_por_parte'][$nombreResolucion] += $pesoPorParte;

                    if (!isset($resumenSedesIndexado[$sede]['resoluciones'][$nombreResolucion])) {
                        $resumenSedesIndexado[$sede]['resoluciones'][$nombreResolucion] = 0;
                    }
                    $resumenSedesIndexado[$sede]['resoluciones'][$nombreResolucion]++;

                    if (!isset($resumenSedesIndexado[$sede]['resoluciones_por_parte'][$nombreResolucion])) {
                        $resumenSedesIndexado[$sede]['resoluciones_por_parte'][$nombreResolucion] = 0;
                    }
                    $resumenSedesIndexado[$sede]['resoluciones_por_parte'][$nombreResolucion] += $pesoPorParte;

                    $resumenSedesIndexado[$sede]['desglose_resoluciones'][$categoriaResolucion]++;
                    $resumenSedesIndexado[$sede]['desglose_resoluciones_por_parte'][$categoriaResolucion] += $pesoPorParte;

                    if (!isset($resolucionesGenerales[$nombreResolucion])) {
                        $resolucionesGenerales[$nombreResolucion] = 0;
                    }
                    $resolucionesGenerales[$nombreResolucion]++;

                    if (!isset($resolucionesGeneralesPorParte[$nombreResolucion])) {
                        $resolucionesGeneralesPorParte[$nombreResolucion] = 0;
                    }
                    $resolucionesGeneralesPorParte[$nombreResolucion] += $pesoPorParte;

                    if ($audiencia->audienciaParte) {
                        foreach ($audiencia->audienciaParte as $audienciaParte) {
                            $estatus = $audienciaParte->finalizado ?: 'Pendiente';

                            if (!isset($resumenDiarioIndexado[$fecha]['estatus_notificaciones'][$estatus])) {
                                $resumenDiarioIndexado[$fecha]['estatus_notificaciones'][$estatus] = 0;
                            }
                            $resumenDiarioIndexado[$fecha]['estatus_notificaciones'][$estatus]++;

                            if (!isset($resumenSedesIndexado[$sede]['estatus_notificaciones'][$estatus])) {
                                $resumenSedesIndexado[$sede]['estatus_notificaciones'][$estatus] = 0;
                            }
                            $resumenSedesIndexado[$sede]['estatus_notificaciones'][$estatus]++;

                            if (!isset($estatusNotificacionesGenerales[$estatus])) {
                                $estatusNotificacionesGenerales[$estatus] = 0;
                            }
                            $estatusNotificacionesGenerales[$estatus]++;
                        }
                    }
                }
            });

        ksort($resumenDiarioIndexado);
        $resumenDiario = array_values(array_map(function ($item) {
            $item['total_audiencias_ordinarias'] = $item['total_audiencias'] - $item['total_audiencias_inmediatas'];
            return $item;
        }, $resumenDiarioIndexado));

        ksort($resumenSedesIndexado, SORT_NATURAL | SORT_FLAG_CASE);
        $resumenSedes = array_values(array_map(function ($item) {
            $item['total_audiencias_ordinarias'] = $item['total_audiencias'] - $item['total_audiencias_inmediatas'];
            return $item;
        }, $resumenSedesIndexado));

        $totalGeneralOrdinarias = $totalGeneral - $totalGeneralInmediatas;

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
                'estatus_notificaciones' => $estatusNotificacionesGenerales,
                'multas_generadas' => $multasGenerales,
                'audiencias_con_multa' => $audienciasConMultaGenerales,
                'multas_por_estado' => $multasPorEstadoGenerales,
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
        ini_set('max_execution_time', 300);
        ini_set('memory_limit', '1024M');

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

            $efectividades = $this->calcularEfectividades($convenios, $noConvenios, $noConveniosIncomp);
            
            // Total general en ponderado por parte
            $totalGeneralPonderado = $convenios + $noConvenios + $noConveniosIncomp + $item['archivados_por_parte'] + $item['sin_resolucion_por_parte'];

            $item['porcentaje_efectividad_conciliacion'] = $efectividades['porcentaje_efectividad_ccl'];
            $item['porcentaje_efectividad_general'] = $efectividades['tasa_conciliacion_federacion'];
            $item['porcentaje_efectividad_ccl'] = $efectividades['porcentaje_efectividad_ccl'];
            $item['tasa_conciliacion_federacion'] = $efectividades['tasa_conciliacion_federacion'];
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
        $filtroGenero = $request->query('genero', 'todos');

        $generoNombre = 'Todos';
        if ($filtroGenero !== 'todos' && $filtroGenero !== null) {
            $generoObj = \App\Genero::find($filtroGenero);
            if ($generoObj) {
                $generoNombre = $generoObj->nombre ?? $generoObj->name ?? 'Desconocido';
            }
        }

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

        if ($filtroGenero !== 'todos' && $filtroGenero !== null) {
            $query->whereHas('solicitantes', function ($q) use ($filtroGenero) {
                $q->where('genero_id', $filtroGenero);
            });
        }

        $queryConteo = clone $query;
        $conteoPorSede = $queryConteo
            ->select('centro_id', DB::raw('COUNT(*) as total'))
            ->groupBy('centro_id')
            ->pluck('total', 'centro_id');

        $idsSolicitudes = $query->pluck('id');
        $desgloseGeneroPorSede = DB::table('partes')
            ->join('solicitudes', 'partes.solicitud_id', '=', 'solicitudes.id')
            ->leftJoin('generos', 'partes.genero_id', '=', 'generos.id')
            ->whereIn('partes.solicitud_id', $idsSolicitudes)
            ->where('partes.tipo_parte_id', 1)
            ->select(
                'solicitudes.centro_id', 
                'generos.id as genero_id', 
                DB::raw('COALESCE(generos.nombre, \'No especificado\') as genero_nombre'), 
                DB::raw('COUNT(DISTINCT partes.solicitud_id) as total')
            )
            ->groupBy('solicitudes.centro_id', 'generos.id', 'generos.nombre')
            ->get()
            ->groupBy('centro_id');

        $centros = Centro::whereIn('id', $centrosFiltro)
            ->select('id', 'nombre')
            ->orderBy('nombre')
            ->get();

        $barras = $centros->map(function ($centro) use ($conteoPorSede, $desgloseGeneroPorSede) {
            $desglose = [];
            if (isset($desgloseGeneroPorSede[$centro->id])) {
                $desglose = $desgloseGeneroPorSede[$centro->id]->map(function($g) {
                    return [
                        'genero_id' => $g->genero_id,
                        'genero_nombre' => $g->genero_nombre,
                        'total' => (int) $g->total
                    ];
                })->values();
            }

            return [
                'centro_id' => (int) $centro->id,
                'sede' => $centro->nombre,
                'solicitudes_generadas' => (int) ($conteoPorSede[$centro->id] ?? 0),
                'desglose_genero' => $desglose
            ];
        })->values();

        // Desglose total por género general
        $desgloseTotalGenero = collect();
        foreach ($barras as $barra) {
            foreach ($barra['desglose_genero'] as $dg) {
                $nombre = $dg['genero_nombre'];
                if (!$desgloseTotalGenero->has($nombre)) {
                    $desgloseTotalGenero->put($nombre, [
                        'genero_nombre' => $nombre,
                        'total' => 0
                    ]);
                }
                $item = $desgloseTotalGenero->get($nombre);
                $item['total'] += $dg['total'];
                $desgloseTotalGenero->put($nombre, $item);
            }
        }

        return response()->json([
            'filtros' => [
                'anio' => $anio,
                'mes_inicio' => $mesInicio,
                'mes_fin' => $mesFin,
                'fecha_inicio' => $fechaInicio->toDateString(),
                'fecha_fin' => $fechaFin->toDateString(),
                'remotas' => $filtroRemotas,
                'confirmadas' => $filtroConfirmadas,
                'genero_id' => $filtroGenero,
                'genero_nombre' => $generoNombre,
                'centros_incluidos' => $centrosFiltro,
            ],
            'grafica_barras' => [
                'labels' => $barras->pluck('sede')->values(),
                'values' => $barras->pluck('solicitudes_generadas')->values(),
                'total_solicitudes' => (int) $barras->sum('solicitudes_generadas'),
                'desglose_genero_total' => $desgloseTotalGenero->values(),
                'series' => $barras,
            ],
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    /**
     * Endpoint dimensional para volumen historico de solicitudes.
     * Permite agrupar por sede, objeto_solicitud o conciliador y por mes o quincena.
     */
    public function getStatsVolumen(Request $request)
    {
        $filtros = $this->getStatsBaseFiltros($request);

        $dimensionRequest = strtolower((string) $request->query('dimension', 'sede'));
        $periodicidadRequest = strtolower((string) $request->query('periodicidad', 'mes'));

        $dimension = in_array($dimensionRequest, ['sede', 'objeto_solicitud', 'conciliador']) ? $dimensionRequest : 'sede';
        $periodicidad = in_array($periodicidadRequest, ['mes', 'quincena']) ? $periodicidadRequest : 'mes';

        $periodConfig = $this->getPeriodicidadConfig($periodicidad);
        $dimensionConfig = $this->getDimensionConfig($dimension);
        $montoExpr = $this->getSolicitudMontoExpression();

        $query = Solicitud::query()->from('solicitudes as s');

        $joinHandler = $dimensionConfig['joins'];
        $joinHandler($query);

        $this->applyStatsBaseFiltros($query, $filtros, 's');

        $query->when($dimension === 'objeto_solicitud', function ($q) {
                $q->whereNotNull('os.id');
            })
            ->when($dimension === 'conciliador', function ($q) {
                $q->whereRaw('COALESCE(ca.conciliador_id, a.conciliador_id) IS NOT NULL');
            });

        $periodKey = $periodConfig['period_key'];
        $periodLabel = $periodConfig['period_label'];
        $periodOrder = $periodConfig['period_order'];
        $dimensionId = $dimensionConfig['id_select'];
        $dimensionLabel = $dimensionConfig['label_select'];

        $series = $query
            ->selectRaw("{$periodKey} AS period_key")
            ->selectRaw("{$periodLabel} AS period_label")
            ->selectRaw("{$periodOrder} AS period_order")
            ->selectRaw("{$dimensionId} AS dimension_id")
            ->selectRaw("{$dimensionLabel} AS dimension_label")
            ->selectRaw('COUNT(DISTINCT s.id) AS total_solicitudes')
            ->selectRaw('COUNT(DISTINCT CASE WHEN s.ratificada = true THEN s.id END) AS total_confirmadas')
            ->selectRaw('COUNT(DISTINCT CASE WHEN s.ratificada = false THEN s.id END) AS total_no_confirmadas')
            ->selectRaw("SUM({$montoExpr}) AS monto_total")
            ->selectRaw("SUM(CASE WHEN EXISTS (
                SELECT 1
                FROM expedientes ex
                INNER JOIN audiencias au ON au.expediente_id = ex.id
                WHERE ex.solicitud_id = s.id
                  AND au.tipo_terminacion_audiencia_id = 3
                  AND au.deleted_at IS NULL
            ) THEN 1 ELSE 0 END) AS incomparecencias")
            ->selectRaw("SUM(CASE WHEN EXISTS (
                SELECT 1
                FROM expedientes ex
                INNER JOIN audiencias au ON au.expediente_id = ex.id
                WHERE ex.solicitud_id = s.id
                AND au.resolucion_id = 1
                AND au.deleted_at IS NULL
            ) THEN 1 ELSE 0 END) AS convenios")
            ->selectRaw("SUM(CASE WHEN EXISTS (
                SELECT 1
                FROM expedientes ex
                INNER JOIN audiencias au ON au.expediente_id = ex.id
                WHERE ex.solicitud_id = s.id
                AND au.resolucion_id = 3
                AND au.deleted_at IS NULL
            ) THEN 1 ELSE 0 END) AS no_hubo_convenio")
            ->selectRaw("SUM(CASE WHEN EXISTS (
                SELECT 1
                FROM expedientes ex
                INNER JOIN audiencias au ON au.expediente_id = ex.id
                WHERE ex.solicitud_id = s.id
                AND au.resolucion_id = 4
                AND au.deleted_at IS NULL
            ) THEN 1 ELSE 0 END) AS archivadas")
            ->selectRaw("SUM(CASE WHEN EXISTS (
                SELECT 1
                FROM expedientes ex
                INNER JOIN audiencias au ON au.expediente_id = ex.id
                WHERE ex.solicitud_id = s.id
                AND au.resolucion_id = 2
                AND au.deleted_at IS NULL
            ) THEN 1 ELSE 0 END) AS reagendadas")
            ->groupByRaw("{$periodKey}, {$periodLabel}, {$periodOrder}, {$dimensionId}, {$dimensionLabel}")
            ->orderByRaw("{$periodOrder} ASC")
            ->orderBy('dimension_label', 'asc')
            ->get()
            ->map(function ($row) {
                $totalSolicitudes = (int) $row->total_solicitudes;
                $incomparecencias = (int) $row->incomparecencias;

                return [
                    'period_key' => $row->period_key,
                    'period_label' => $row->period_label,
                    'dimension_id' => $row->dimension_id !== null ? (int) $row->dimension_id : null,
                    'dimension_label' => $row->dimension_label,
                    'total_solicitudes' => $totalSolicitudes,
                    'total_confirmadas' => (int) $row->total_confirmadas,
                    'total_no_confirmadas' => (int) $row->total_no_confirmadas,
                    'monto_total' => (float) $row->monto_total,
                    'incomparecencias' => $incomparecencias,
                    'convenios' => (int) $row->convenios,
                    'no_hubo_convenio' => (int) $row->no_hubo_convenio,
                    'archivadas' => (int) $row->archivadas,
                    'reagendadas' => (int) $row->reagendadas,
                    'tasa_incomparecencia' => $totalSolicitudes > 0
                        ? round(($incomparecencias / $totalSolicitudes) * 100, 2)
                        : 0,
                ];
            })
            ->values();

        $periodosOrdenados = $series
            ->map(function ($item) {
                return [
                    'period_key' => $item['period_key'],
                    'period_label' => $item['period_label'],
                ];
            })
            ->unique('period_key')
            ->values();

        $dimensionesOrdenadas = $series
            ->pluck('dimension_label')
            ->unique()
            ->values();

        $seriesIndexada = [];
        foreach ($series as $item) {
            $dimensionLabel = $item['dimension_label'];
            $periodKey = $item['period_key'];
            $seriesIndexada[$dimensionLabel][$periodKey] = $item;
        }

        $datasets = $dimensionesOrdenadas->map(function ($dimensionLabel) use ($periodosOrdenados, $seriesIndexada) {
            $dataSolicitudes = [];
            $dataMontos = [];
            $dataIncomparecencias = [];
            $dataTasaIncomparecencia = [];

            foreach ($periodosOrdenados as $periodo) {
                $periodKey = $periodo['period_key'];
                $valor = $seriesIndexada[$dimensionLabel][$periodKey] ?? null;

                $dataSolicitudes[] = $valor ? (int) $valor['total_solicitudes'] : 0;
                $dataMontos[] = $valor ? (float) $valor['monto_total'] : 0;
                $dataIncomparecencias[] = $valor ? (int) $valor['incomparecencias'] : 0;
                $dataTasaIncomparecencia[] = $valor ? (float) $valor['tasa_incomparecencia'] : 0;
            }

            return [
                'label' => $dimensionLabel,
                'data' => $dataSolicitudes,
                'monto_data' => $dataMontos,
                'incomparecencias_data' => $dataIncomparecencias,
                'tasa_incomparecencia_data' => $dataTasaIncomparecencia,
                'confirmadas_data' => $periodosOrdenados->map(function ($periodo) use ($seriesIndexada, $dimensionLabel) {
                    $valor = $seriesIndexada[$dimensionLabel][$periodo['period_key']] ?? null;
                    return $valor ? (int) $valor['total_confirmadas'] : 0;
                })->values(),
                'convenios_data' => $periodosOrdenados->map(function ($periodo) use ($seriesIndexada, $dimensionLabel) {
                    $valor = $seriesIndexada[$dimensionLabel][$periodo['period_key']] ?? null;
                    return $valor ? (int) $valor['convenios'] : 0;
                })->values(),
            ];
        })->values();

        $totalesSolicitudes = (int) $series->sum('total_solicitudes');
        $totalesIncomparecencias = (int) $series->sum('incomparecencias');

        return response()->json([
            'filters' => [
                'sedes' => $filtros['sedes'],
                'fecha_inicio' => $filtros['fecha_inicio']->toDateString(),
                'fecha_fin' => $filtros['fecha_fin']->toDateString(),
                'dimension' => $dimension,
                'periodicidad' => $periodConfig['periodicidad'],
                'remotas' => $filtros['remotas'],
                'confirmadas' => $filtros['confirmadas'],
                'inmediatas' => $filtros['inmediatas'],
                'tipo_solicitud_id' => $filtros['tipo_solicitud_id'],
            ],
            'totals' => [
                'total_solicitudes' => $totalesSolicitudes,
                'total_confirmadas' => (int) $series->sum('total_confirmadas'),
                'total_no_confirmadas' => (int) $series->sum('total_no_confirmadas'),
                'monto_total' => (float) $series->sum('monto_total'),
                'incomparecencias' => $totalesIncomparecencias,
                'convenios' => (int) $series->sum('convenios'),
                'no_hubo_convenio' => (int) $series->sum('no_hubo_convenio'),
                'archivadas' => (int) $series->sum('archivadas'),
                'reagendadas' => (int) $series->sum('reagendadas'),
                'tasa_incomparecencia' => $totalesSolicitudes > 0
                    ? round(($totalesIncomparecencias / $totalesSolicitudes) * 100, 2)
                    : 0,
                'periodos' => $periodosOrdenados->pluck('period_key')->values(),
                'dimensiones' => $dimensionesOrdenadas,
            ],
            'chart_pivot' => [
                'x_axis' => $periodosOrdenados->pluck('period_label')->values(),
                'x_axis_keys' => $periodosOrdenados->pluck('period_key')->values(),
                'datasets' => $datasets,
            ],
            'series' => $series,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }
}