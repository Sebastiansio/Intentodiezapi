<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Conciliador;
use App\Audiencia;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * Obtiene todos los conciliadores con su nombre.
     */
    public function getConciliadores(Request $request)
    {
        $centrosPermitidos = [38, 48, 39]; // Filtros de centros solicitados

        // Tomar fechas de la petición para filtrar audiencias, por default la semana actual
        $fechaInicio = $request->query('fecha_inicio', Carbon::now()->startOfWeek()->toDateString());
        $fechaFin = $request->query('fecha_fin', Carbon::now()->endOfWeek()->toDateString());

        $conciliadores = Conciliador::with(['persona', 'centro'])
            ->whereIn('centro_id', $centrosPermitidos)
            // Filtramos para traer solo conciliadores que tengan audiencias en este periodo y que no sean inmediatas
            ->whereHas('audiencias', function ($query) use ($fechaInicio, $fechaFin) {
                $query->whereBetween('fecha_audiencia', [$fechaInicio, $fechaFin])
                      ->whereHas('expediente.solicitud', function ($subQuery) {
                          $subQuery->where('inmediata', false);
                      });
            })
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

        return response()->json($conciliadores);
    }

    /**
     * Obtiene las audiencias de un conciliador en la semana actual (o según fechas).
     */
    public function getAudiencias(Request $request, $id)
    {
        $centrosPermitidos = [38, 48, 39];

        // Validar que el conciliador exista y pertenezca a los centros permitidos
        $conciliadorValido = Conciliador::whereIn('centro_id', $centrosPermitidos)->find($id);

        if (!$conciliadorValido) {
            return response()->json(['error' => 'Conciliador no encontrado o no pertenece a los centros solicitados'], 404);
        }

        $fechaInicio = $request->query('fecha_inicio', Carbon::now()->startOfWeek()->toDateString());
        $fechaFin = $request->query('fecha_fin', Carbon::now()->endOfWeek()->toDateString());

        $audiencias = Audiencia::whereBetween('fecha_audiencia', [$fechaInicio, $fechaFin])
            ->where(function($query) use ($id) {
                $query->where('conciliador_id', $id)
                      ->orWhereHas('conciliadoresAudiencias', function($q) use ($id) {
                          $q->where('conciliador_id', $id);
                      });
            })
            ->whereHas('expediente.solicitud', function ($query) {
                // Filtramos que la solicitud no sea inmediata
                $query->where('inmediata', false);
            })
            ->select('id', 'expediente_id', 'resolucion_id', 'fecha_audiencia', 'hora_inicio', 'hora_fin')
            ->with(['expediente:id,folio,anio', 'salasAudiencias.sala:id,sala', 'resolucion:id,nombre'])
            ->orderBy('fecha_audiencia')
            ->orderBy('hora_inicio')
            ->get()
            ->map(function($a) {
                $sala = $a->salasAudiencias && $a->salasAudiencias->count() > 0 
                        ? $a->salasAudiencias->first()->sala->sala 
                        : 'Sin sala asignada';
                return [
                    'id' => $a->id,
                    'expediente' => $a->expediente ? $a->expediente->folio : 'Sin expediente',
                    'anio' => $a->expediente ? $a->expediente->anio : '',
                    'fecha' => $a->fecha_audiencia,
                    'hora_inicio' => $a->hora_inicio,
                    'hora_fin' => $a->hora_fin,
                    'sala' => $sala,
                    'estado_audiencia_id' => null, // La columna estado_audiencia_id no existe en la base de datos
                    'resolucion_id' => $a->resolucion_id,
                    'resolucion_nombre' => $a->resolucion ? $a->resolucion->nombre : 'Sin resolución'
                ];
            });

        // Usamos JSON_UNESCAPED_SLASHES para evitar que las barras / en el expediente se escapen con \
        return response()->json($audiencias, 200, [], JSON_UNESCAPED_SLASHES);
    }

    /**
     * Obtiene las estadísticas de efectividad de las resoluciones de un conciliador.
     */
    public function getEstadisticas(Request $request, $id)
    {
        $centrosPermitidos = [38, 48, 39];

        if ($id !== 'todos' && $id != 0) {
            $conciliadorValido = Conciliador::whereIn('centro_id', $centrosPermitidos)->find($id);
            if (!$conciliadorValido) {
                return response()->json(['error' => 'Conciliador no encontrado o no pertenece a los centros solicitados'], 404);
            }
        }

        $fechaInicio = $request->query('fecha_inicio', Carbon::now()->startOfWeek()->toDateString());
        $fechaFin = $request->query('fecha_fin', Carbon::now()->endOfWeek()->toDateString());

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
            // Obtenemos todos los IDs válidos de los conciliadores de esos centros
            $conciliadoresIdsValidos = Conciliador::whereIn('centro_id', $centrosPermitidos)->pluck('id')->toArray();
            
            $audienciasQuery->where(function($query) use ($conciliadoresIdsValidos) {
                $query->whereIn('conciliador_id', $conciliadoresIdsValidos)
                      ->orWhereHas('conciliadoresAudiencias', function($q) use ($conciliadoresIdsValidos) {
                          $q->whereIn('conciliador_id', $conciliadoresIdsValidos);
                      });
            });
        }

        $audiencias = $audienciasQuery->select('id', 'resolucion_id')->get();

        $totalAudiencias = $audiencias->count();

        // Contadores según las resoluciones (1: Convenio, 2 y 3: No convenio, 4: Archivado)
        $convenios = $audiencias->where('resolucion_id', 1)->count();
        $noConvenios = $audiencias->whereIn('resolucion_id', [2, 3])->count();
        $archivados = $audiencias->where('resolucion_id', 4)->count();
        $sinResolucion = $audiencias->whereNull('resolucion_id')->count();

        // Calculamos la efectividad (Convenios vs total de audiencias con resolución válida para el cálculo)
        // Generalmente, la efectividad es: Convenios / (Convenios + No Convenios) o Convenios / Total General
        // Lo calcularemos sobre el Total General para mayor precisión.
        $efectividadGeneral = $totalAudiencias > 0 ? round(($convenios / $totalAudiencias) * 100, 2) : 0;
        
        // Alternativamente: Efectividad de Conciliación (descartando sin resolución y archivados)
        $totalConciliados = $convenios + $noConvenios;
        $efectividadReal = $totalConciliados > 0 ? round(($convenios / $totalConciliados) * 100, 2) : 0;

        return response()->json([
            'total_audiencias' => $totalAudiencias,
            'convenios' => $convenios,
            'no_convenios' => $noConvenios,
            'archivados' => $archivados,
            'sin_resolucion' => $sinResolucion,
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
        $centrosPermitidos = [38, 48, 39];
        
        $centroIdRequest = $request->query('centro_id');
        if ($centroIdRequest && in_array($centroIdRequest, $centrosPermitidos)) {
            $centrosFiltro = [$centroIdRequest];
        } else {
            $centrosFiltro = $centrosPermitidos;
        }

        $fechaInicio = $request->query('fecha_inicio', Carbon::now()->startOfWeek()->toDateString());
        $fechaFin = $request->query('fecha_fin', Carbon::now()->endOfWeek()->toDateString());

        // Identificar IDs de conciliadores válidos según los centros
        $conciliadoresIdsValidos = Conciliador::whereIn('centro_id', $centrosFiltro)->pluck('id')->toArray();

        // Obtener nombres de los centros filtrados
        $centrosInfo = \App\Centro::whereIn('id', $centrosFiltro)->select('id', 'nombre')->get();

        // Obtener todas las audiencias aplicando filtros y relaciones (y que NO sean de solicitud inmediata)
        $audiencias = Audiencia::whereBetween('fecha_audiencia', [$fechaInicio, $fechaFin])
            ->where(function($query) use ($conciliadoresIdsValidos) {
                $query->whereIn('conciliador_id', $conciliadoresIdsValidos)
                      ->orWhereHas('conciliadoresAudiencias', function($q) use ($conciliadoresIdsValidos) {
                          $q->whereIn('conciliador_id', $conciliadoresIdsValidos);
                      });
            })
            ->whereHas('expediente.solicitud', function ($query) {
                $query->where('inmediata', false);
            })
            ->select('id', 'fecha_audiencia', 'resolucion_id', 'conciliador_id')
            ->with(['resolucion', 'conciliador.centro', 'conciliadoresAudiencias.conciliador.centro'])
            ->orderBy('fecha_audiencia', 'asc')
            ->get();

        // Agrumamos la data por día (fecha_audiencia)
        $resumenDiario = $audiencias->groupBy('fecha_audiencia')->map(function ($audienciasDelDia, $fecha) {
            $totalDelDia = $audienciasDelDia->count();
            
            // Sub-agrupamos por nombre de la resolución
            $resolucionesAgrupadas = $audienciasDelDia->groupBy(function ($a) {
                return $a->resolucion ? $a->resolucion->nombre : 'Sin resolución';
            })->map(function ($grupo) {
                return $grupo->count();
            });

            return [
                'fecha' => $fecha,
                'total_audiencias' => $totalDelDia,
                'resoluciones' => $resolucionesAgrupadas
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
            
            $resolucionesAgrupadasSede = $audienciasDeSede->groupBy(function ($a) {
                return $a->resolucion ? $a->resolucion->nombre : 'Sin resolución';
            })->map(function ($grupo) {
                return $grupo->count();
            });

            return [
                'sede' => $sede,
                'total_audiencias' => $totalDeSede,
                'resoluciones' => $resolucionesAgrupadasSede
            ];
        })->values();

        // Para darle un valor extra al front, mandamos también un consolidado de todo el periodo sumado
        $totalGeneral = $audiencias->count();
        $resolucionesGenerales = $audiencias->groupBy(function ($a) {
            return $a->resolucion ? $a->resolucion->nombre : 'Sin resolución';
        })->map->count();

        return response()->json([
            'resumen_periodo' => [
                'fecha_inicio' => $fechaInicio,
                'fecha_fin' => $fechaFin,
                'centros_incluidos' => $centrosInfo,
                'total_audiencias' => $totalGeneral,
                'resoluciones' => $resolucionesGenerales,
            ],
            'desglose_diario' => $resumenDiario,
            'desglose_sedes' => $resumenSedes
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }
}