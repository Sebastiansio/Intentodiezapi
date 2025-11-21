<?php

namespace App\Services;

use App\Audiencia;
use App\AudienciaParte;
use App\Expediente;
use App\Filters\AudienciaFilter;
use App\Filters\AudienciaParteFilter;
use App\Filters\ResolucionPagoDiferidoFilter;
use App\Filters\SolicitudFilter;
use App\ResolucionPagoDiferido;
use App\Solicitud;
use Illuminate\Support\Facades\DB;

class ReporteOperativoService
{
    /**
     * ID de solicitante en catálogo tipo_partes
     */
    const SOLICITANTE_ID = 1;

    /**
     * ID de tipo de solicitud individual en catálogo de tipos_solicitudes
     */
    const SOLICITUD_INDIVIDUAL = 1;

    /**
     * ID de tipo de solicitud patronal individual en catálogo de tipos_solicitudes
     */
    const SOLICITUD_PATRONAL_INDIVIDUAL = 2;

    /**
     * ID de error de captura en catálogo tipo_incidencia_solicitudes
     */
    const ERROR_DE_CAPTURA = 1;

    /**
     * ID de solicitud deuplicada en catálogo de tipo_incidencias_solicitudes
     */
    const SOLICITUD_DUPLICADA = 2;

    /**
     * ID de solicitud deuplicada en catálogo de tipo_incidencias_solicitudes
     */
    const SOLICITUD_NO_RATIFICADA = 3;

    /**
     * ID de otros en catálogo de tipo_incidencias_solicitudes
     */
    const OTRA_INCIDENCIA = 5;

    /**
     * ID de la incompetencia de tipo_incidencias_solicitudes
     */
    const INCOMPETENCIA_EN_RATIFICACION = 4;

    /**
     * ID de la incompetencia en audiencia en catálogo de tipos de terminaciones de audiencias
     */
    const TERMINACION_INCOMPETENCIA_EN_AUDIENCIA = 6;

    const INCOMPETENCIA_EN_AUDIENCIA = 13;

    /**
     * ID de tipo de parte
     */
    const CITADO = 2;

    /**
     * ID de tipo de citatorios
     */
    const CITATORIO_POR_SOLICITANTE = 1;

    const CITATORIO_POR_NOTIFICADOR = 2;

    const CITATORIO_POR_NOTIFICADOR_ACOMPANIADO = 3;

    const CITATORIO_POR_EBUZON = 4;

    /**
     * ID de archivado en catálogo de resoluciones, se utiliza en archivado por falta de interés de la audiencia
     */
    const ARCHIVADO = 4;

    /**
     * ID de hubo convenio en terminaciones bilaterales
     */
    const TERMINACIONES_BILATERALES_CONVENIO = 3;

    /**
     * ID de hubo convenio en tabla resoluciones
     */
    const RESOLUCIONES_HUBO_CONVENIO = 1;

    /**
     * ID de no hubo convenio en tabla resoluciones
     */
    const RESOLUCIONES_NO_HUBO_CONVENIO = 3;

    /**
     * Sobre las solicitudes presentadas
     *
     * @return mixed
     */
    public function solicitudes($request, bool $tipos = true)
    {

        $q = (new SolicitudFilter(Solicitud::query(), $request))
            ->searchWith(Solicitud::class)
            ->filter(false);

        // Las solicitudes
        if ($request->get('fecha_inicial')) {
            $q->whereRaw('fecha_recepcion::date >= ?', $request->get('fecha_inicial'));
        }
        if ($request->get('fecha_final')) {
            $q->whereRaw('fecha_recepcion::date <= ?', $request->get('fecha_final'));
        }

        //Dejamos fuera los no consultables
        $this->noReportables($q);
        $q->leftJoin('expedientes', 'expedientes.solicitud_id', '=', 'solicitudes.id');
        $q->whereNull('expedientes.deleted_at');
        if ($tipos) {
            $this->filtroTipoSolicitud($request, $q);
        }

        //dd($this->debugSql($q));
        return $q;
    }

    public function solicitudesPresentadas($request, $tipos = true)
    {

        $q = (new SolicitudFilter(Solicitud::query(), $request))
            ->searchWith(Solicitud::class)
            ->filter(false);

        // Las solicitudes
        if ($request->get('fecha_inicial')) {
            $q->whereRaw('fecha_recepcion::date >= ?', $request->get('fecha_inicial'));
        }
        if ($request->get('fecha_final')) {
            $q->whereRaw('fecha_recepcion::date <= ?', $request->get('fecha_final'));
        }

        //Dejamos fuera los no consultables
        $q->whereRaw('tipo_incidencia_solicitud_id is distinct from ?', self::ERROR_DE_CAPTURA);
        $q->whereRaw('tipo_incidencia_solicitud_id is distinct from ?', self::SOLICITUD_DUPLICADA);
        $q->whereRaw('tipo_incidencia_solicitud_id is distinct from ?', self::SOLICITUD_NO_RATIFICADA);
        $q->whereRaw('tipo_incidencia_solicitud_id is distinct from ?', self::OTRA_INCIDENCIA);

        if ($tipos) {
            $this->filtroTipoSolicitud($request, $q);
        }

        //dd($this->debugSql($q));
        return $q;
    }

    /**
     * Sobre las solicitudes y su ratificacion
     *
     * @return mixed
     */
    public function solicitudesRatificacion($request)
    {
        $q = (new SolicitudFilter(Solicitud::query(), $request))
            ->searchWith(Solicitud::class)
            ->filter(false);

        // Las solicitudes
        if ($request->get('fecha_inicial')) {
            $q->whereRaw('fecha_ratificacion::date >= ?', $request->get('fecha_inicial'));
        }
        if ($request->get('fecha_final')) {
            $q->whereRaw('fecha_ratificacion::date <= ?', $request->get('fecha_final'));
        }

        //Dejamos fuera los no consultables
        $this->noReportables($q);
        $q->leftJoin('expedientes', 'expedientes.solicitud_id', '=', 'solicitudes.id');
        $q->whereNull('expedientes.deleted_at');

        $this->filtroTipoSolicitud($request, $q);

        return $q;
    }

    /**
     * Convenios
     *
     * @return mixed
     */
    public function convenios($request)
    {
        $q = (new AudienciaFilter(Audiencia::query(), $request))
            ->searchWith(Audiencia::class)
            ->filter(false);

        //Las solicitudes confirmadas se evaluan por fecha de ratificacion
        if ($request->get('fecha_inicial')) {
            $q->whereRaw('fecha_audiencia::date >= ?', $request->get('fecha_inicial'));
        }
        if ($request->get('fecha_final')) {
            $q->whereRaw('fecha_audiencia::date <= ?', $request->get('fecha_final'));
        }

        $q->select('centros.abreviatura', 'solicitudes.id as solicitud_id', 'expedientes.folio as expediente',
            'audiencias.id as audiencia_id',
            'fecha_audiencia', 'numero_audiencia', 'monto', 'resolucion_parte_conceptos.id as resolucion_parte_conceptos_id'
            // ,'tipo_propuesta_pago_id'
        );

        $q->join('expedientes', 'expedientes.id', '=', 'audiencias.expediente_id');
        $q->join('solicitudes', 'expedientes.solicitud_id', '=', 'solicitudes.id');

        $q->join('centros', 'solicitudes.centro_id', '=', 'centros.id');
        //$q->leftJoin('tipo_terminacion_audiencias','audiencias.tipo_terminacion_audiencia_id','=','tipo_terminacion_audiencias.id');
        $q->leftJoin('resoluciones', 'audiencias.resolucion_id', '=', 'resoluciones.id');
        $q->leftJoin('audiencias_partes', 'audiencias_partes.audiencia_id', '=', 'audiencias.id');
        $q->leftJoin('resolucion_parte_conceptos', 'resolucion_parte_conceptos.audiencia_parte_id', '=', 'audiencias_partes.id');

        //Se filtran las no reportables
        $this->noReportables($q);
        $q->whereNull('expedientes.deleted_at');
        $q->whereNull('resolucion_parte_conceptos.deleted_at');
        $q->whereNotNull('resolucion_parte_conceptos.id');

        $this->filtroTipoSolicitud($request, $q);

        return $q;
    }

    ///////////////////////////////////////////////////////////////////////////
    /**
     * No conciliación
     *
     * @return mixed
     */
    public function noConciliacion($request)
    {
        $q = (new SolicitudFilter(Solicitud::query(), $request))
            ->searchWith(Solicitud::class)
            ->filter(false);

        //Las solicitudes confirmadas se evaluan por fecha de ratificacion
        if ($request->get('fecha_inicial')) {
            $q->whereRaw('fecha_audiencia::date >= ?', $request->get('fecha_inicial'));
        }
        if ($request->get('fecha_final')) {
            $q->whereRaw('fecha_audiencia::date <= ?', $request->get('fecha_final'));
        }

        $q->select('centros.abreviatura', 'solicitudes.id as solicitud_id', 'expedientes.folio as expediente',
            'audiencias.id as audiencia_id',
            'audiencias.resolucion_id as resolucion_id',
            'solicitudes.inmediata',
            'resoluciones.nombre as resolucion',
            'audiencias.finalizada as audiencia_finalizada',
            'audiencias.tipo_terminacion_audiencia_id',
            'tipo_terminacion_audiencias.nombre as tipo_terminacion',
            'fecha_audiencia', 'numero_audiencia',
            'monto'
        );

        $q->join('expedientes', 'expedientes.solicitud_id', '=', 'solicitudes.id');
        $q->join('audiencias', 'expedientes.id', '=', 'audiencias.expediente_id');
        $q->join('centros', 'solicitudes.centro_id', '=', 'centros.id');
        $q->leftJoin('tipo_terminacion_audiencias', 'audiencias.tipo_terminacion_audiencia_id', '=', 'tipo_terminacion_audiencias.id');
        $q->leftJoin('resoluciones', 'audiencias.resolucion_id', '=', 'resoluciones.id');
        $q->leftJoin('audiencias_partes', 'audiencias_partes.audiencia_id', '=', 'audiencias.id');
        $q->leftJoin('resolucion_parte_conceptos', 'resolucion_parte_conceptos.audiencia_parte_id', '=', 'audiencias_partes.id');

        //Se agregan las consultas para conciliador
        $q->addSelect('audiencias.conciliador_id', 'personas.nombre as conciliador_nombre', 'personas.primer_apellido as conciliador_primer_apellido',
            'personas.segundo_apellido as conciliador_segundo_apellido');
        $q->leftJoin('conciliadores', 'conciliadores.id', '=', 'audiencias.conciliador_id');
        $q->leftJoin('personas', 'personas.id', '=', 'conciliadores.persona_id');

        //Si viene parámetro filtrable de conciliadores entonces limitamos la consulta a esos conciliadores
        if ($request->get('conciliadores')) {
            if (! empty($request->get('conciliadores'))) {
                $q->whereIn('audiencias.conciliador_id', $request->get('conciliadores'));
            }
        }

        //Se aplican filtros por características del solicitante
        $this->filtroPorCaracteristicasSolicitanteSolicitud($request, $q);

        //Se filtran las no reportables
        $this->noReportables($q);
        $q->whereNull('expedientes.deleted_at');

        // Sólo las de trabajador y patron individual por default.
        $this->filtroTipoSolicitud($request, $q);

        // Por tipo de industria
        $this->filtroPorIndustrias($request, $q);

        $q->where('audiencias.resolucion_id', self::RESOLUCIONES_NO_HUBO_CONVENIO);

        if ($request->get('tipo_reporte') == 'agregado') {
            $res = $q->get();
        } else {
            $res = $q->get()->sortBy('abreviatura');
        }

        return $res;
    }

    /**
     * Audiencias
     *
     * @return mixed
     */
    public function audiencias($request)
    {
        $q = (new AudienciaFilter(Audiencia::query(), $request))
            ->searchWith(Audiencia::class)
            ->filter(false);

        //Las audiencias se evalúan por fecha de audiencia
        if ($request->get('fecha_inicial')) {
            $q->whereRaw('fecha_audiencia::date >= ?', $request->get('fecha_inicial'));
        }
        if ($request->get('fecha_final')) {
            $q->whereRaw('fecha_audiencia::date <= ?', $request->get('fecha_final'));
        }

        $q->select('audiencias.id as audiencia_id', 'centros.abreviatura', 'audiencias.fecha_audiencia', 'audiencias.conciliador_id',
            'solicitudes.id as solicitud_id', 'audiencias.finalizada as audiencia_finalizada', 'audiencias.numero_audiencia');

        //Seleccionamos la abreviatura del nombre y su cuenta
        $q->join('expedientes', 'expedientes.id', '=', 'audiencias.expediente_id');
        $q->join('solicitudes', 'solicitudes.id', '=', 'expedientes.solicitud_id');
        $q->join('centros', 'solicitudes.centro_id', '=', 'centros.id');

        //Se filtran las no reportables
        $this->noReportables($q);
        $q->whereNull('expedientes.deleted_at');
        $q->whereNull('audiencias.deleted_at');
        $q->whereNull('solicitudes.deleted_at');

        $this->filtroTipoSolicitud($request, $q);

        return $q;
    }

    /**
     * Citatorios
     *
     * @return mixed
     */
    public function citatorios($request)
    {
        $q = (new AudienciaParteFilter(AudienciaParte::query(), $request))
            ->searchWith(AudienciaParte::class)
            ->filter(false);

        //Las audiencias se evalúan por fecha de audiencia
        if ($request->get('fecha_inicial')) {
            $q->whereRaw('audiencias_partes.created_at::date >= ?', $request->get('fecha_inicial'));
        }
        if ($request->get('fecha_final')) {
            $q->whereRaw('audiencias_partes.created_at::date <= ?', $request->get('fecha_final'));
        }

        $q->select('audiencias.id as audiencia_id', 'audiencias.fecha_audiencia',
            'audiencias_partes.parte_id', 'audiencias_partes.tipo_notificacion_id',
            'audiencias.finalizada as audiencia_finalizada', 'audiencias.numero_audiencia');

        $q->join('audiencias', 'audiencias.id', '=', 'audiencias_partes.audiencia_id');
        $q->join('expedientes', 'expedientes.id', '=', 'audiencias.expediente_id');
        $q->join('solicitudes', 'solicitudes.id', '=', 'expedientes.solicitud_id');

        //Se filtran las no reportables
        $this->noReportables($q);
        $q->whereNull('expedientes.deleted_at');
        $q->whereNull('solicitudes.deleted_at');
        $q->whereNull('audiencias.deleted_at');
        $q->whereNull('audiencias_partes.deleted_at');
        $this->filtroTipoSolicitud($request, $q);

        return $q;
    }

    /**
     * Pagos diferidos
     *
     * @return mixed
     */
    public function pagos($request)
    {
        $q = (new ResolucionPagoDiferidoFilter(ResolucionPagoDiferido::query(), $request))
            ->searchWith(ResolucionPagoDiferido::class)
            ->filter(false);

        //Las solicitudes confirmadas se evaluan por fecha de ratificacion
        if ($request->get('fecha_inicial')) {
            $q->whereRaw('fecha_audiencia::date >= ?', $request->get('fecha_inicial'));
        }
        if ($request->get('fecha_final')) {
            $q->whereRaw('fecha_audiencia::date <= ?', $request->get('fecha_final'));
        }

        $q->select('audiencias.id as audiencia_id', 'expedientes.folio as expediente', 'centros.abreviatura',
            'audiencias.fecha_audiencia', 'solicitudes.id as solicitud_id',
            'resolucion_pagos_diferidos.fecha_pago', 'resolucion_pagos_diferidos.pagado'
        );

        $q->join('audiencias', 'audiencias.id', '=', 'resolucion_pagos_diferidos.audiencia_id');
        $q->join('expedientes', 'expedientes.id', '=', 'audiencias.expediente_id');
        $q->join('solicitudes', 'solicitudes.id', '=', 'expedientes.solicitud_id');
        $q->join('centros', 'solicitudes.centro_id', '=', 'centros.id');

        //Se filtran las no reportables
        $this->noReportables($q);
        $q->whereNull('expedientes.deleted_at');

        // Sólo las de trabajador y patron individual por default.
        $this->filtroTipoSolicitud($request, $q);

        return $q;
    }

    public function pagosDiferidos($request)
    {

        $q = (new AudienciaFilter(Audiencia::query(), $request))
            ->searchWith(Audiencia::class)
            ->filter(false);

        //Las audiencias se evalúan por fecha de audiencia
        if ($request->get('fecha_inicial')) {
            $q->whereRaw('fecha_audiencia::date >= ?', $request->get('fecha_inicial'));
        }
        if ($request->get('fecha_final')) {
            $q->whereRaw('fecha_audiencia::date <= ?', $request->get('fecha_final'));
        }

        $q->with('pagosDiferidos');

        return $q;
    }

    //////////////////////////////////////////////////////////////////////////////////////////////////////////////

    /**
     * Excluye los no reportables
     *
     * @return mixed
     */
    private function noReportables($q)
    {
        //Eliminamos las incidencias duplicadas y errores de captura
        $q->whereRaw('tipo_incidencia_solicitud_id is distinct from ?', self::ERROR_DE_CAPTURA);
        $q->whereRaw('tipo_incidencia_solicitud_id is distinct from ?', self::SOLICITUD_DUPLICADA);
        $q->whereRaw('tipo_incidencia_solicitud_id is distinct from ?', self::OTRA_INCIDENCIA);

        return $q;
    }

    /**
     * Filtra por características de la parte solicitante
     *
     * @return mixed
     */
    private function filtroPorCaracteristicasSolicitanteSolicitud($request, $q, string $modelo = 'partes')
    {
        if ($request->get('genero_id') || (is_array($request->get('grupo_id')) && count($request->get('grupo_id'))) || $request->get('tipo_persona_id')) {
            $genero_id = $request->get('genero_id');
            $grupo_id = $request->get('grupo_id');
            $tipo_persona_id = $request->get('tipo_persona_id');
            $modelo = trim($modelo);
            $q->whereHas($modelo, function ($q) use ($genero_id, $grupo_id, $tipo_persona_id) {

                // Por el género
                if ($genero_id) {
                    $q->where('genero_id', $genero_id);
                }

                // Por el  o los grupos etarios
                if (is_array($grupo_id) && count($grupo_id)) {

                    $q->where(function ($qq) use ($grupo_id) {
                        foreach ($grupo_id as $idx => $grupo) {
                            [$ini, $fin] = explode('-', $grupo);
                            if ($idx == 0) {
                                $qq->whereRaw('edad::integer BETWEEN ? AND ?', [$ini, $fin]);
                            } else {
                                $qq->orWhereRaw('edad::integer BETWEEN ? AND ?', [$ini, $fin]);
                            }
                        }
                    });

                }
                // Por el tipo de persona
                if ($tipo_persona_id) {
                    $q->where('tipo_persona_id', $tipo_persona_id);
                }

                // Sólo se toman en cuenta los solicitantes
                $q->where('tipo_parte_id', self::SOLICITANTE_ID);
            });
        }

        return $q;
    }

    /**
     * Filtra características del solicitante para una audiencia
     *
     * @return mixed
     */
    private function filtroPorCaracteristicasSolicitanteAudienciaParte($request, $q)
    {
        if ($request->get('genero_id') || $request->get('edad_inicial') || $request->get('edad_final')) {
            $genero_id = $request->get('genero_id');
            $edad_inicial = $request->get('edad_inicial');
            $edad_final = $request->get('edad_final');

            if ($genero_id) {
                $q->where('partes.genero_id', $genero_id);
            }
            if ($edad_inicial) {
                $q->whereRaw('partes.edad::integer >= ?', $edad_inicial);
            }
            if ($edad_final) {
                $q->whereRaw('partes.edad::integer <= ?', $edad_final);
            }
            //Solo se toman en cuenta los solicitantes
            $q->where('partes.tipo_parte_id', self::SOLICITANTE_ID);
        }

        return $q;
    }

    /**
     * Filtra por tipo de solicitud, por default aplica para las solicitudes de tipo:
     * Solicitud individual
     * Solicitud patronal individual
     */
    public function filtroTipoSolicitud($request, $q)
    {
        //Sólo las de trabajador y patron individual si no viene nada del cuestionario de consultas
        if (! $request->get('tipo_solicitud_id')) {
            $q->whereIn(
                'solicitudes.tipo_solicitud_id',
                [self::SOLICITUD_INDIVIDUAL, self::SOLICITUD_PATRONAL_INDIVIDUAL]
            );
        } else {
            $q->where('solicitudes.tipo_solicitud_id', $request->get('tipo_solicitud_id'));
        }
    }

    /**
     * Filtros comunes a todas las peticiones
     * filtroTipoSolicitud
     * filtroPorIndustrias
     * filtroPorCaracteristicasSolicitanteSolicitud
     * noReportables
     */
    public function filtrosComunesAplicables($request, $q, string $modelo = '')
    {
        // Sólo las de trabajador y patron individual por default.
        $this->filtroTipoSolicitud($request, $q);

        // Por tipo de industria
        $this->filtroPorIndustrias($request, $q, $modelo);

        //Se aplican filtros por características del solicitante
        $this->filtroPorCaracteristicasSolicitanteSolicitud($request, $q);

        //Dejamos fuera los no consultables
        $this->noReportables($q);
    }

    private function filtroPorCaracteristicasPartes($request, $q)
    {
        $fecha_inicial = $request->get('fecha_inicial');
        $fecha_final = $request->get('fecha_final');

        $q->with(['expediente', 'expediente.audiencia', 'expediente.audiencia.audienciaParte', 'expediente.audiencia.audienciaParte.parte'])
            ->whereHas('expediente.audiencia.audienciaParte', function ($q) use ($fecha_inicial, $fecha_final) {

                if ($fecha_inicial) {
                    $q->whereRaw('created_at::date <= ?', $fecha_inicial);
                }
                if ($fecha_final) {
                    $q->whereRaw('created_at::date >= ?', $fecha_final);
                }
            });

        return $q;
    }

    /**
     * Filtro por industrias
     *
     * @param  $modelo  string Es la ruta de relaciones para llegar desde el objeto principal que consulta a la solicitud
     *                 pej. Si el objeto principal que consulta es Audiencia dado que se va a regresar una colección de audiencias el modelo es:
     *                 expediente.solicitud
     * @return mixed
     */
    public function filtroPorIndustrias($request, $q, $modelo = '')
    {
        if (! empty($request->get('giro_id'))) {
            $giros = $request->get('giro_id');

            $q->with($modelo.'giroComercial')
                ->whereHas(
                    $modelo.'giroComercial',
                    function ($q) use ($giros) {
                        $q->whereIn('industria_id', $giros);
                    }
                );
        }

        return $q;
    }

    /**
     * Marca borrado lógico de expedientes duplicados por bug.
     * Se toma como bueno el primer registro (id más bajo en expedientes)
     *
     * @param  bool  $dry_run  Si se pasa true no se ejecuta el borrado sólo se regresan los borrables.
     */
    public function seteaDeletedAtDeExpedientesDuplicadosConMismaSolicitudId(bool $dry_run = false)
    {

        $dbh = Expediente::withTrashed()->select(DB::raw('count(*), solicitud_id'))
            ->groupBy('solicitud_id')
            ->havingRaw('count(*) > 1');
        $duplicados = $dbh->get();
        $ids_duplicados = $duplicados->pluck('solicitud_id')->toArray();

        $borrados = Expediente::onlyTrashed()->whereIn('solicitud_id', $ids_duplicados)->get();
        $idsborrados = $borrados->unique('solicitud_id')->pluck('solicitud_id')->toArray();

        $no_borrados = $duplicados->reject(function ($val, $key) use ($idsborrados) {
            return in_array($val->solicitud_id, $idsborrados);
        });
        $ids_no_borrados = $no_borrados->unique('solicitud_id')->pluck('solicitud_id')->toArray();

        $no_borrables = Expediente::whereIn('solicitud_id', $ids_no_borrados)->orderBy('solicitud_id')->orderBy('id')->get();

        $no_borrables_ids = $no_borrables->unique('solicitud_id')->pluck('solicitud_id', 'id')->toArray();

        $borrables = Expediente::whereIn('solicitud_id', $ids_duplicados)->whereNull('deleted_at')->get()->reject(function ($val, $key) use ($no_borrables_ids) {
            return isset($no_borrables_ids[$val->id]);
        });

        $borrables->each(function ($item, $key) use ($dry_run) {
            if (! $dry_run) {
                $item->delete();
            }
        });

        return $borrables;
    }
}
