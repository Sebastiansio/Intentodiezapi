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
    public function getConciliadores()
    {
        $centrosPermitidos = [38, 48, 39]; // Filtros de centros solicitados

        $conciliadores = Conciliador::with('persona')
            ->whereIn('centro_id', $centrosPermitidos)
            ->get()
            ->map(function($c) {
                $nombre = $c->persona 
                          ? trim($c->persona->nombre . ' ' . $c->persona->primer_apellido . ' ' . $c->persona->segundo_apellido)
                          : 'Sin nombre';
                return [
                    'id' => $c->id,
                    'nombre' => $nombre,
                    'centro_id' => $c->centro_id
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
            ->with(['expediente', 'salasAudiencias.sala'])
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
                    'estado_audiencia_id' => $a->estado_audiencia_id,
                    'resolucion_id' => $a->resolucion_id
                ];
            });

        return response()->json($audiencias);
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

        $audiencias = $audienciasQuery->get();

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
}