<?php


namespace App\Services;


use App\Centro;
use App\Conciliador;
use App\Traits\EstilosSpreadsheets;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;
use PhpOffice\PhpSpreadsheet\Chart\Layout;
use PhpOffice\PhpSpreadsheet\Chart\Legend;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Title;

class ExcelReporteOperativoService
{
    use EstilosSpreadsheets;

    /**
     * @var ReporteOperativoService
     */
    protected $service;

    /**
     * @var array Centros que se van a reportar
     */
    protected $centros_activos;

    /**
     * @var array Conciliadores que se van a reportar
     */
    protected $conciliadores;

    /**
     * Días para el archivado de no ratificadas
     */
    const DIAS_PARA_ARCHIVAR = 5;

    /**
     * @var integer Ancho de encabezados de tablas
     */
    private $head_height = 75;

    /**
     * ExcelReporteOperativoService constructor.
     */
    public function __construct(ReporteOperativoService $service)
    {
        $this->service = $service;
        $this->centros_activos = Centro::whereNotNull('desde')->orderBy('abreviatura')->get()->pluck('abreviatura')
            ->toArray();

        $this->conciliadores = Conciliador::with('persona')->has('audiencias')->get()->map(function ($conciliador){
            return (object)[
                'id' => $conciliador->id,
                'nombre' => $conciliador->persona->fullName,
                'primer_apellido' => $conciliador->persona->primer_apellido,
            ];
        })->sortBy('primer_apellido');
    }

    /**
     * Genera el reporte operativo en excel
     * @param $sheet
     * @param $request
     */
    public function reporte($sheet, $request)
    {

        # Solicitudes confirmadas
        $qSolicitudesRatificadas = (clone $this->service->solicitudes($request));
        $sheet->setCellValue('B2', $qSolicitudesRatificadas->where('ratificada', true)->count());

        # Archivadas por no confirmación
        // regla de negocio: No ratificados por más de 7 días desde su creación.
        $qSolicitudesArchivadasNoConfirmacion = (clone $this->service->solicitudes($request));
        $sheet->setCellValue('B3', $qSolicitudesArchivadasNoConfirmacion->where('ratificada', false)
            ->whereRaw("(solicitudes.fecha_recepcion::date + '". self::DIAS_PARA_ARCHIVAR." days'::interval)::date  <= CURRENT_DATE" )->count());

        # En posibilidad de confirmarse porque están en el plazo
        $qSolicitudesPorConfirmar = (clone $this->service->solicitudes($request));
        $sheet->setCellValue('B4', $qSolicitudesPorConfirmar->where('ratificada', false)
            ->whereRaw("(solicitudes.fecha_recepcion::date + '". self::DIAS_PARA_ARCHIVAR." days'::interval)::date  > CURRENT_DATE" )->count());

        # Solicitudes presentadas
        $qSolicitudesPresentadas = (clone $this->service->solicitudesPresentadas($request));
        $sheet->setCellValue('B5', $qSolicitudesPresentadas->count());

        # Solicitudes por concepto: total, confirmadas, archivadas por no confirmar, por confirmar
        # tipo de solicitud trabajador o patron

        $pptrab = ($this->service->solicitudes($request,false))->where('tipo_solicitud_id', ReportesService::SOLICITUD_INDIVIDUAL);
        $sheet->setCellValue('B8', $pptrab->count());

        $sheet->setCellValue('C8', $pptrab->where('ratificada', true)->count());
        $sheet->setCellValue('D8', ($this->service->solicitudes($request,false))->where('tipo_solicitud_id', ReportesService::SOLICITUD_INDIVIDUAL)->where('ratificada', false)->whereRaw("(fecha_recepcion::date + '". self::DIAS_PARA_ARCHIVAR." days'::interval)::date  <= CURRENT_DATE" )->count());
        $sheet->setCellValue('E8', ($this->service->solicitudes($request,false))->where('tipo_solicitud_id', ReportesService::SOLICITUD_INDIVIDUAL)->where('ratificada', false)->whereRaw("(fecha_recepcion::date + '". self::DIAS_PARA_ARCHIVAR." days'::interval)::date  > CURRENT_DATE" )->count());

        $pppatr = ($this->service->solicitudes($request, false))->where('tipo_solicitud_id', ReportesService::SOLICITUD_PATRONAL_INDIVIDUAL);
        $sheet->setCellValue('B9', $pppatr->count());
        $sheet->setCellValue('C9', $pppatr->where('ratificada',true)->count());
        $sheet->setCellValue('D9', ($this->service->solicitudes($request))->where('tipo_solicitud_id', ReportesService::SOLICITUD_PATRONAL_INDIVIDUAL)->where('ratificada', false)->whereRaw("(fecha_recepcion::date + '". self::DIAS_PARA_ARCHIVAR." days'::interval)::date  <= CURRENT_DATE" )->count());
        $sheet->setCellValue('E9', ($this->service->solicitudes($request))->where('tipo_solicitud_id', ReportesService::SOLICITUD_PATRONAL_INDIVIDUAL)->where('ratificada', false)->whereRaw("(fecha_recepcion::date + '". self::DIAS_PARA_ARCHIVAR." days'::interval)::date  > CURRENT_DATE" )->count());

        # con asistencia de personal o por los usuarios
        $caspc = ($this->service->solicitudes($request))->whereNotNull('captura_user_id');
        $sheet->setCellValue('B12', $caspc->count());
        $sheet->setCellValue('C12', $caspc->where('ratificada', true)->count());
        $sheet->setCellValue('D12', ($this->service->solicitudes($request))->whereNotNull('captura_user_id')->where('ratificada', false)->whereRaw("(fecha_recepcion::date + '". self::DIAS_PARA_ARCHIVAR." days'::interval)::date  <= CURRENT_DATE" )->count());
        $sheet->setCellValue('E12', ($this->service->solicitudes($request))->whereNotNull('captura_user_id')->where('ratificada', false)->whereRaw("(fecha_recepcion::date + '". self::DIAS_PARA_ARCHIVAR." days'::interval)::date  > CURRENT_DATE" )->count());

        $sol = ($this->service->solicitudes($request))->whereNull('captura_user_id');
        $sheet->setCellValue('B13', $sol->count());
        $sheet->setCellValue('C13', $sol->where('ratificada', true)->count());
        $sheet->setCellValue('D13', ($this->service->solicitudes($request))->whereNull('captura_user_id')->where('ratificada', false)->whereRaw("(fecha_recepcion::date + '". self::DIAS_PARA_ARCHIVAR." days'::interval)::date  <= CURRENT_DATE" )->count());
        $sheet->setCellValue('E13', ($this->service->solicitudes($request))->whereNull('captura_user_id')->where('ratificada', false)->whereRaw("(fecha_recepcion::date + '". self::DIAS_PARA_ARCHIVAR." days'::interval)::date  > CURRENT_DATE" )->count());

        # por género
        //Hombres
        $solgenh = ReportesService::caracteristicasSolicitante(($this->service->solicitudes($request)), 'partes', ReportesService::GENERO_MASCULINO_ID,null,null);
        $solgenhpr = ReportesService::caracteristicasSolicitante(($this->service->solicitudes($request)), 'partes', ReportesService::GENERO_MASCULINO_ID,null,null);
        $solgenhar = ReportesService::caracteristicasSolicitante(($this->service->solicitudes($request)), 'partes', ReportesService::GENERO_MASCULINO_ID,null,null);

        $solgenhcit = ReportesService::caracteristicasSolicitante(($this->service->solicitudes($request)), 'partes', ReportesService::GENERO_MASCULINO_ID,null,null);
        $solgenhprcit = ReportesService::caracteristicasSolicitante(($this->service->solicitudes($request)), 'partes', ReportesService::GENERO_MASCULINO_ID,null,null);
        $solgenharcit = ReportesService::caracteristicasSolicitante(($this->service->solicitudes($request)), 'partes', ReportesService::GENERO_MASCULINO_ID,null,null);

        $sheet->setCellValue('B16', $solgenh->count());
        $sheet->setCellValue('C16', $solgenh->where('ratificada', true)->count());
        $sheet->setCellValue('D16', $solgenhar->where('ratificada', false)->whereRaw("(fecha_recepcion::date + '". self::DIAS_PARA_ARCHIVAR." days'::interval)::date  <= CURRENT_DATE" )->count());
        $sheet->setCellValue('E16', $solgenhpr->where('ratificada', false)->whereRaw("(fecha_recepcion::date + '". self::DIAS_PARA_ARCHIVAR." days'::interval)::date  > CURRENT_DATE" )->count());
        //Mujeres
        $solgenm = ReportesService::caracteristicasSolicitante(($this->service->solicitudes($request)), 'partes', ReportesService::GENERO_FEMENINO_ID,null,null);
        $solgenmpr = ReportesService::caracteristicasSolicitante(($this->service->solicitudes($request)), 'partes', ReportesService::GENERO_FEMENINO_ID,null,null);
        $solgenmar = ReportesService::caracteristicasSolicitante(($this->service->solicitudes($request)), 'partes', ReportesService::GENERO_FEMENINO_ID,null,null);
        $sheet->setCellValue('B17', $solgenm->count());
        $sheet->setCellValue('C17', $solgenm->where('ratificada', true)->count());
        $sheet->setCellValue('D17', $solgenmar->where('ratificada', false)->whereRaw("(fecha_recepcion::date + '". self::DIAS_PARA_ARCHIVAR." days'::interval)::date  <= CURRENT_DATE" )->count());
        $sheet->setCellValue('E17', $solgenmpr->where('ratificada', false)->whereRaw("(fecha_recepcion::date + '". self::DIAS_PARA_ARCHIVAR." days'::interval)::date  > CURRENT_DATE" )->count());

        # Por grupo etáreo
        /** @var array[Builder] $solget Arreglo que contiene objetos Builder para las solicitudes totalizadas de grupo etario  */
        $solget=[];
        /** @var array[Builder] $solget Arreglo que contiene objetos Builder para las solicitudes por ratificar  */
        $solgetpr=[];
        /** @var array[Builder] $solgetpr Arreglo que contiene objetos Builder para las solicitudes archivadas  */
        $solgetar=[];
        /** @var integer $rowget Fila de grupo etario */
        $rowget = 21;
        foreach(ReportesService::gruposEtarios() as $grupo => $descripcion){
            $solget[$grupo] =  (ReportesService::caracteristicasSolicitante(clone $this->service->solicitudes($request), 'partes', null, $grupo,null));
            $solgetpr[$grupo] =  (ReportesService::caracteristicasSolicitante(clone $this->service->solicitudes($request), 'partes', null, $grupo,null));
            $solgetar[$grupo] =  (ReportesService::caracteristicasSolicitante(clone $this->service->solicitudes($request), 'partes', null, $grupo,null));
            $sheet->setCellValue('B'.$rowget, $solget[$grupo]->count());
            $sheet->setCellValue('C'.$rowget, $solget[$grupo]->where('ratificada', true)->count());
            $sheet->setCellValue('D'.$rowget, $solgetar[$grupo]->where('ratificada', false)->whereRaw("(fecha_recepcion::date + '". self::DIAS_PARA_ARCHIVAR." days'::interval)::date  <= CURRENT_DATE" )->count());
            $sheet->setCellValue('E'.$rowget, $solgetpr[$grupo]->where('ratificada', false)->whereRaw("(fecha_recepcion::date + '". self::DIAS_PARA_ARCHIVAR." days'::interval)::date  > CURRENT_DATE" )->count());
            $rowget++;
        }

        # Por "tipo de conflicto" esto es objeto de la conciliacón

        // Hay una particularidad en esta tabla, sólo dos conceptos comparten nombre para patrones y trabajadores las demás columnas aparecerán en cero

        /** @var int $rowobj row donde empieza la tabla de objetos */
        $rowobj = $rowget + 1;
        $sheet->setCellValue('A'.$rowobj, "Solicitudes por concepto (total, confirmadas, no confirmadas y por confirmar) Tipo de Conflicto");
        $sheet->setCellValue('B'.$rowobj, "TOTAL PATRÓN");
        $sheet->setCellValue('C'.$rowobj, "TOTAL TRABAJADOR");
        $sheet->setCellValue('D'.$rowobj, "PRESENTADAS");

        /** @var int $rowinidat row donde empiezam los datos de la tabla, esta se utiliza para indicar desde qué punto empieza la copia de estilo */
        $rowinidat = $rowobj +1;

        // Copiamos el estilo del último encabezado fijo a los generados dinámicamente
        $sheet->duplicateStyle($sheet->getStyle('A20'),'A'.$rowobj.':D'.$rowobj);

        $rowobj++;

        /** @var array indica si se ha visto o no el nombre del objeto */
        $visto = [];

        foreach(ReportesService::getObjetosFiltrables(true) as $id => $objeto){

            $cant = $this->service->solicitudes($request)->whereHas('objeto_solicitudes', function ($q) use ($objeto) {
                $q->where('objeto_solicitud_id', $objeto->id);
            })->count();

            if(!isset($visto[$objeto->nombre])) {
                $visto[$objeto->nombre] = true;
                $sheet->setCellValue('A' . $rowobj, $objeto->nombre);
                $sheet->setCellValue('B' . $rowobj, 0);
                $sheet->setCellValue('C' . $rowobj, 0);
                $sheet->setCellValue('D' . $rowobj, "=SUM(B$rowobj:C$rowobj)");
                if ($objeto->tipo_objeto_solicitudes_id == ReportesService::SOLICITUD_PATRONAL_INDIVIDUAL) {
                    $sheet->setCellValue('B' . $rowobj, $cant);
                } else {
                    $sheet->setCellValue('C' . $rowobj, $cant);
                }
                $rowobj++;
                continue;
            }

            if ($objeto->tipo_objeto_solicitudes_id == ReportesService::SOLICITUD_PATRONAL_INDIVIDUAL) {
                $sheet->setCellValue('B' . ($rowobj -1), $cant);
            } else {
                $sheet->setCellValue('C' . ($rowobj -1), $cant);
            }

        }

        // Copiamos el estilo de la última celda de datos de la tabla fija a la tabla generada dinámicamente
        $sheet->duplicateStyle($sheet->getStyle('A21'),'A'.$rowinidat.':A'.($rowobj-1));
        $sheet->duplicateStyle($sheet->getStyle('B21'),'B'.$rowinidat.':D'.($rowobj-1));

        # Por "concepto" esto es por industria según catalogo del poder judicial

        //Solicitudes por Rama Industrila

        /** @var int $rowind row donde empieza la tabla de objetos */
        $rowind = $rowobj + 1;
        $sheet->setCellValue('A'.$rowind, "Solicitudes por concepto (total, confirmadas, no confirmadas y por confirmar) Rama Industrial");
        $sheet->setCellValue('B'.$rowind, "PRESENTADAS");
        $sheet->setCellValue('C'.$rowind, "CONFIRMADAS");
        $sheet->setCellValue('D'.$rowind, "ARCHIVADAS POR NO CONFIRMACIÓN");
        $sheet->setCellValue('E'.$rowind, "POR CONFIRMAR");

        $rowinidat = $rowind;
        // Copiamos el estilo del último encabezado fijo a los generados dinámicamente
        $sheet->duplicateStyle($sheet->getStyle('A20'),'A'.$rowind.':E'.$rowind);

        $rowind++;

        $sheet->setCellValue('A'.$rowind, 0);
        $sheet->setCellValue('B'.$rowind, 0);
        $sheet->setCellValue('C'.$rowind, 0);
        $sheet->setCellValue('D'.$rowind, 0);
        $sheet->setCellValue('E'.$rowind, 0);

        foreach(ReportesService::getIndustria() as $industria_id => $industria){
            //dump($industria_id ." => ".$industria);
            $total = $this->service->solicitudes($request)
                ->whereHas('giroComercial', function ($q) use ($industria_id) {
                    $q->where('industria_id', $industria_id);
            })->count();

            $confirmadas = $this->service->solicitudes($request)->where('ratificada', true)
                ->whereHas('giroComercial', function ($q) use ($industria_id) {
                    $q->where('industria_id', $industria_id);
            })->count();

            $archivadas = (clone $this->service->solicitudes($request))->where('ratificada', false)
                ->whereRaw("(fecha_recepcion::date + '". self::DIAS_PARA_ARCHIVAR." days'::interval)::date  <= CURRENT_DATE" )
                ->whereHas('giroComercial', function ($q) use ($industria_id) {
                    $q->where('industria_id', $industria_id);
            })->count();

            $porconfirmar = (clone $this->service->solicitudes($request))->where('ratificada', false)
                ->whereRaw("(fecha_recepcion::date + '". self::DIAS_PARA_ARCHIVAR." days'::interval)::date  > CURRENT_DATE" )
                ->whereHas('giroComercial', function ($q) use ($industria_id) {
                    $q->where('industria_id', $industria_id);
            })->count();

            $sheet->setCellValue('A'.$rowind, $industria);
            $sheet->setCellValue('B'.$rowind, $total);
            $sheet->setCellValue('C'.$rowind, $confirmadas);
            $sheet->setCellValue('D'.$rowind, $archivadas);
            $sheet->setCellValue('E'.$rowind, $porconfirmar);

            $rowind++;
        }
        $sheet->setCellValue('A'.$rowind, "TOTAL");
        $sheet->setCellValue('B'.$rowind, "=SUM(B".($rowobj + 2).":B$rowind)");
        $sheet->setCellValue('C'.$rowind, "=SUM(C".($rowobj + 2).":C$rowind)");
        $sheet->setCellValue('D'.$rowind, "=SUM(D".($rowobj + 2).":D$rowind)");
        $sheet->setCellValue('E'.$rowind, "=SUM(E".($rowobj + 2).":E$rowind)");

        // Copiamos el estilo de la última celda de datos de la tabla fija a la tabla generada dinámicamente
        $sheet->duplicateStyle($sheet->getStyle('A21'),'A'.($rowinidat+1).':A'.($rowind));
        $sheet->duplicateStyle($sheet->getStyle('B21'),'B'.($rowinidat+1).':E'.($rowind));

        //////////////////////////////////////////////////////////////////////////////////////////////////////////
        # Sobre las solicitudes inmediatas (le llaman convenio con ratificación en el reporte)
        //////////////////////////////////////////////////////////////////////////////////////////////////////////

        # F2 Incompetencias
        $incompetencias = (clone $this->service->solicitudesRatificacion($request))->has('documentosComentadosComoIncompetencia')
            ->where('tipo_incidencia_solicitud_id', ReportesService::INCOMPETENCIA_EN_RATIFICACION);
        $sheet->setCellValue('G2', $incompetencias->count());

        # F3 Incompetencia detectada en audiencia
        $incompetenciasEnAudiencia = (clone $this->service->solicitudesRatificacion($request))->whereHas('expediente.audiencia.documentos', function ($qq){
            return $qq->where('clasificacion_archivo_id', ReportesService::INCOMPETENCIA_EN_AUDIENCIA);
        });
        $sheet->setCellValue('G3', $incompetenciasEnAudiencia->count());

        # F4 Competencias
        // Para saber las competentes entonces seleccionamos todas las solicitudes y eliminamos los ids de incompetencias detectadas en ratificación y en audiencia
        $solicitudesIncompetenciasIds = $incompetencias->get()->merge($incompetenciasEnAudiencia->get())->pluck('solicitudes.id')->toArray();
        $competencias = (clone $this->service->solicitudesRatificacion($request))->whereNotIn('solicitudes.id', $solicitudesIncompetenciasIds)->count();
        $sheet->setCellValue('G4', $competencias);

        # F7 Número de solicitudes inmediatas (ratificaciones le llaman en el reporte)
        $inmediatas = (clone $this->service->solicitudesRatificacion($request))->where('inmediata', true)->count();
        $sheet->setCellValue('G7', $inmediatas);

        # G8 Monto de convenio con ratificaciones
        $monto_hubo_convenio = (clone $this->service->convenios($request))->where('inmediata', true)
            ->where('resolucion_id', ReportesService::RESOLUCIONES_HUBO_CONVENIO)->get()
            ->sum('monto');
        $sheet->setCellValue('G8', $monto_hubo_convenio);

        # G9 número de beneficios o prestaciones no económicas derivadas de ratificación
        $convenio_no_economico = (clone $this->service->convenios($request))->where('inmediata', true)
            ->where('resolucion_id', ReportesService::RESOLUCIONES_HUBO_CONVENIO)
            ->whereIn('concepto_pago_resoluciones_id',[
                                                        ReportesService::CONCEPTO_PAGO_GRATIFICACION_EN_ESPECIE,
                                                        ReportesService::CONCEPTO_PAGO_RECONOCIMIENTO_DERECHOS,
                                                        ReportesService::CONCEPTO_PAGO_OTRO])
            ->where(function ($query) {
                $query->where('monto', 0)
                    ->orWhereNull('monto');
            })
            ->get()
            ->count();
        $sheet->setCellValue('G9', $convenio_no_economico);

        //////////////////////////////////////////////////////////////////
        # 3 Sobre los Citatorios
        ///////////////////////////////////////////////////////////

        # I2 Primeras audiencias para las que se emitió citatorio
        $primeras_aud_citatorio = (clone $this->service->citatorios($request))
            ->where('audiencias.numero_audiencia', 1)
            ->whereIn('tipo_notificacion_id', [
                ReportesService::CITATORIO_POR_SOLICITANTE,
                ReportesService::CITATORIO_POR_NOTIFICADOR,
                ReportesService::CITATORIO_POR_NOTIFICADOR_ACOMPANIADO
            ])
            ->whereHas('parte', function($q) { $q->where('tipo_parte_id', ReportesService::CITADO_ID );})
            ->whereHas('audiencia.expediente.solicitud', function ($q) {$q->where('inmediata',false);})
            ->get()
            ->unique('audiencia_id')
            ->count();
        $sheet->setCellValue('J2', $primeras_aud_citatorio);

        # I3 Número de citatorios emitidos para primera audiencia
        $citatorios_para_prim_aud = (clone $this->service->citatorios($request))
            ->where('audiencias.numero_audiencia', 1)->whereIn('tipo_notificacion_id', [
                ReportesService::CITATORIO_POR_SOLICITANTE,
                ReportesService::CITATORIO_POR_NOTIFICADOR,
                ReportesService::CITATORIO_POR_NOTIFICADOR_ACOMPANIADO])
            ->whereHas('parte', function($q) { $q->where('tipo_parte_id', ReportesService::CITADO_ID );})
            ->whereHas('audiencia.expediente.solicitud', function ($q) {$q->where('inmediata',false);})
            ->count();
        $sheet->setCellValue('J3', $citatorios_para_prim_aud);

        # I6 Del total de citatorios emitidos
        $total_citatorios_emitidos = (clone $this->service->citatorios($request))
            ->whereIn('tipo_notificacion_id', [
                ReportesService::CITATORIO_POR_SOLICITANTE,
                ReportesService::CITATORIO_POR_NOTIFICADOR,
                ReportesService::CITATORIO_POR_NOTIFICADOR_ACOMPANIADO])
            ->whereHas('parte', function($q) { $q->where('tipo_parte_id', ReportesService::CITADO_ID );})
            ->whereHas('audiencia.expediente.solicitud', function ($q) {$q->where('inmediata',false);})
            ->count();
        $sheet->setCellValue('J6', $total_citatorios_emitidos);

        # I7 Número de citatorios notificados por el solicitante
        $citatorios_x_solicitante = (clone $this->service->citatorios($request))
            ->whereIn('tipo_notificacion_id', [ReportesService::CITATORIO_POR_SOLICITANTE])
            ->whereHas('parte', function($q) { $q->where('tipo_parte_id', ReportesService::CITADO_ID );})
            ->whereHas('audiencia.expediente.solicitud', function ($q) {$q->where('inmediata',false);})
            ->count();
        $sheet->setCellValue('J7', $citatorios_x_solicitante);

        # I8 Número de citatorios notificados por personal del CFCRL
        $citatorios_x_notificador = (clone $this->service->citatorios($request))
            ->whereIn('tipo_notificacion_id', [ReportesService::CITATORIO_POR_NOTIFICADOR])
            ->whereHas('parte', function($q) { $q->where('tipo_parte_id', ReportesService::CITADO_ID );})
            ->whereHas('audiencia.expediente.solicitud', function ($q) {$q->where('inmediata',false);})
            ->count();
        $sheet->setCellValue('J8', $citatorios_x_notificador);

        # I9 Número de citatorios notificados por personal del CFCRL en compañía del solicitante
        $citatorios_x_notificador_acompaniado = (clone $this->service->citatorios($request))
            ->whereIn('tipo_notificacion_id', [ReportesService::CITATORIO_POR_NOTIFICADOR_ACOMPANIADO])
            ->whereHas('parte', function($q) { $q->where('tipo_parte_id', ReportesService::CITADO_ID );})
            ->whereHas('audiencia.expediente.solicitud', function ($q) {$q->where('inmediata',false);})
            ->count();
        $sheet->setCellValue('J9', $citatorios_x_notificador_acompaniado);

        //////////////////////////////////////////////////////////////////
        # 3 Sobre las audiencias
        ///////////////////////////////////////////////////////////

        # K2 Total de Audiencias de Conciliación
        $total_audiencias = (clone $this->service->audiencias($request))
            ->where('audiencias.finalizada', true)
            ->whereHas('expediente.solicitud', function ($q){$q->where('inmediata', false);})
            ->get();
        $sheet->setCellValue('L2', $total_audiencias->count());

        # K3 Conciliacion en 1a audiencia
        $concilia_en_1a = $total_audiencias->where('numero_audiencia', 1)->count();
        $sheet->setCellValue('L3', $concilia_en_1a);

        # K4 Conciliacion en 2a audiencia
        $concilia_en_2a = $total_audiencias->where('numero_audiencia', 2)->count();
        $sheet->setCellValue('L4', $concilia_en_2a);

        # K5 Conciliacion en 3a audiencia
        $concilia_en_3a = $total_audiencias->where('numero_audiencia', 3)->count();
        $sheet->setCellValue('L5', $concilia_en_3a);

        # K5 Conciliacion en 4a audiencia o mas
        $concilia_en_4a = $total_audiencias->where('numero_audiencia', '>',3)->count();
        $sheet->setCellValue('L6', $concilia_en_4a);

        # M3 Archivo por falta de interés
        $archivo_falta_interes = (clone $this->service->audiencias($request))
            ->whereHas('expediente.solicitud', function ($q){$q->where('inmediata', false);})
            ->where('resolucion_id', ReportesService::RESOLUCIONES_ARCHIVADO)
            ->count();
        $sheet->setCellValue('N3', $archivo_falta_interes);

        # M4 solicita nueva audiencia
        $sol_nueva_fecha = (clone $this->service->audiencias($request))
            ->whereHas('expediente.solicitud', function ($q){$q->where('inmediata', false);})
            ->where('resolucion_id', ReportesService::RESOLUCIONES_NO_CONVENIO_DESEA_AUDIENCIA)
            ->count();
        $sheet->setCellValue('N4', $sol_nueva_fecha);

        # M5 No conciliacion
        $no_conciliaciones = (clone $this->service->audiencias($request))
            ->whereHas('expediente.solicitud', function ($q){$q->where('inmediata', false);})
            ->where('resolucion_id', ReportesService::RESOLUCIONES_NO_HUBO_CONVENIO)
            ->count();
        $sheet->setCellValue('N5', $no_conciliaciones);

        # M6 Convenio
        $hubo_convenios = (clone $this->service->audiencias($request))
            ->whereHas('expediente.solicitud', function ($q){$q->where('inmediata', false);})
            ->where('resolucion_id', ReportesService::RESOLUCIONES_HUBO_CONVENIO)
            ->count();
        $sheet->setCellValue('N6', $hubo_convenios);

        //////////////////////////////////////////////////////////////////
        # 4 Sobre las audiencias Ver con Diana si los querys son correctos, o de donde se saca el dato de que no se presentó el citado o el solicitante
        ///////////////////////////////////////////////////////////

        # O3 Total de archivos por falta de interés

        $archivo_falta_interes = (clone $this->service->audiencias($request))
            ->where('resolucion_id', ReportesService::RESOLUCIONES_ARCHIVADO)
            ->whereIn('solicitudes.tipo_solicitud_id',[
                ReportesService::SOLICITUD_INDIVIDUAL, ReportesService::SOLICITUD_PATRONAL_INDIVIDUAL])
            ->count();
        $sheet->setCellValue('P2', $archivo_falta_interes);

        # O4 En cuántos no se presentó el solicitante trabajador

        $archivo_falta_interes_solicitante = (clone $this->service->audiencias($request))
            ->where('resolucion_id', ReportesService::RESOLUCIONES_ARCHIVADO)
            ->whereIn('solicitudes.tipo_solicitud_id',[ReportesService::SOLICITUD_INDIVIDUAL])
            ->count();
        $sheet->setCellValue('P3', $archivo_falta_interes_solicitante);

        # O5 En cuántos no se presentó el solicitante patrón

        $archivo_falta_interes_patron = (clone $this->service->audiencias($request))
            ->where('resolucion_id', ReportesService::RESOLUCIONES_ARCHIVADO)
            ->whereIn('solicitudes.tipo_solicitud_id',[ReportesService::SOLICITUD_PATRONAL_INDIVIDUAL])
            ->count();
        $sheet->setCellValue('P4', $archivo_falta_interes_patron);

        //////////////////////////////////////////////////////////////////
        # 5 Conclusión  Ver con Diana, si es la forma correcta de saber si no se presentó el citado o el solicitante
        ///////////////////////////////////////////////////////////

        # Q2 Total  de Constancias de No Conciliaciones
        $total_constancias_no_conciliacion = (clone $this->service->audiencias($request))
            ->where('resolucion_id', ReportesService::RESOLUCIONES_NO_HUBO_CONVENIO)
            ->count();
        $sheet->setCellValue('R2', $total_constancias_no_conciliacion);

        # Q3 Número de contancias de no conciliación por incomparecencia del citado
        $no_conciliacion_nocomparecencia = (clone $this->service->audiencias($request))
            ->where('resolucion_id', ReportesService::RESOLUCIONES_NO_HUBO_CONVENIO)
            ->where('tipo_terminacion_audiencia_id', ReportesService::TERMINACION_AUDIENCIA_NO_COMPARECENCIA_CITADO)
            ->count();
        $sheet->setCellValue('R3', $no_conciliacion_nocomparecencia);

        # Q4 Número de Constancias de No Conciliación por no acuerdo
        $sheet->setCellValue('R4', ($total_constancias_no_conciliacion - $no_conciliacion_nocomparecencia));

        /////////

        # S2 Total de convenios
        $total_convenios = (clone $this->service->audiencias($request))
            ->where('resolucion_id', ReportesService::RESOLUCIONES_HUBO_CONVENIO)
            ->whereHas('expediente.solicitud', function ($q){$q->where('inmediata', false);})
            ->whereHas('audienciaParte.parteConceptos', function ($q){$q->whereNotNull('id');})
            ->count();
        $sheet->setCellValue('T2', $total_convenios);

        # S3 Monto desglosado de los convenios
        $monto_convenios = (clone $this->service->convenios($request))
            ->where('inmediata', false)
            ->where('resolucion_id', ReportesService::RESOLUCIONES_HUBO_CONVENIO)
            ->whereHas('audienciaParte.parteConceptos', function ($q){$q->whereNotNull('id');})
            ->sum('monto');
        $sheet->setCellValue('T3', $monto_convenios);

        # S4 Beneficios o prestaciones no económicas
        $beneficios = (clone $this->service->convenios($request))
            ->where('resolucion_id', ReportesService::RESOLUCIONES_HUBO_CONVENIO)
            ->where('solicitudes.inmediata', false)
            ->whereIn('concepto_pago_resoluciones_id',[
                ReportesService::CONCEPTO_PAGO_GRATIFICACION_EN_ESPECIE,
                ReportesService::CONCEPTO_PAGO_RECONOCIMIENTO_DERECHOS,
                ReportesService::CONCEPTO_PAGO_OTRO])
            ->where(function ($query) {
                $query->where('monto', 0)
                    ->orWhereNull('monto');
            })
            ->get()
            ->unique('resolucion_parte_conceptos_id')
            ->count();
        $sheet->setCellValue('T4', $beneficios);

#GRAN TODO............ Qué significa número de convenios???
        # Número de convenios diferidos
        $num_convenios = (clone $this->service->pagosDiferidos($request))
            ->has('pagosDiferidos', '>=', 1)
            ->whereHas('expediente.solicitud', function ($q){ $q->where('inmediata', false);})
            ->where('resolucion_id', ReportesService::RESOLUCIONES_HUBO_CONVENIO)
            ->get()->unique('expediente_id')->count();
        $sheet->setCellValue('T7', $num_convenios);

        # Número de pagos diferidos
        $num_pagos_dif = (clone $this->service->pagosDiferidos($request))
            ->has('pagosDiferidos', '>=', 1)
            ->whereHas('expediente.solicitud', function ($q){ $q->where('inmediata', false);})
            ->where('resolucion_id', ReportesService::RESOLUCIONES_HUBO_CONVENIO)
            ->get()->count();
        $sheet->setCellValue('U7', $num_pagos_dif);

        # Monto pagos diferidos
        $monto_pagos_dif = (clone $this->service->pagosDiferidos($request))
            ->has('pagosDiferidos', '>=', 1)
            ->whereHas('expediente.solicitud', function ($q){ $q->where('inmediata', false);})
            ->get()
            ->map(function ($k, $v){
                return $k->pagosDiferidos->sum('monto');
            });
        $sheet->setCellValue('V7', $monto_pagos_dif->sum());


        # Número de convenios totales
        $num_convenios_tt = $this->service->convenios($request)
            ->where('solicitudes.inmediata', false)
            ->where('resolucion_id', ReportesService::RESOLUCIONES_HUBO_CONVENIO)->get()->unique('solicitud_id');

        # Número de pagos totales
        $num_pagos_tt = $this->service->convenios($request)
            ->where('solicitudes.inmediata', false)
            ->where('resolucion_id', ReportesService::RESOLUCIONES_HUBO_CONVENIO)->get();

        # Monto pagos totales
        $monto_pagos_tt = $num_pagos_tt->sum('monto');

        $sheet->setCellValue('T9', $num_convenios_tt->count());
        $sheet->setCellValue('U9', $num_pagos_tt->count());
        $sheet->setCellValue('V9', $monto_pagos_tt);

        # Pagos a la firma del convenio debe ser la resta entre unos T9,U9,V9 y T7,U7 y V7
        # Ver hasta abajo lo que se sobreescribe con operaciones de pagos diferidos!!
        $sheet->setCellValue('T8', $num_convenios_tt->count() - $num_convenios);
        $sheet->setCellValue('U8', $num_pagos_tt->count() - $num_pagos_dif);
        $sheet->setCellValue('V8', $monto_pagos_tt - $monto_pagos_dif->sum());

        $num_tot_pagos_parciales = (clone $this->service->pagos($request))
            ->get()->count();
        $sheet->setCellValue('T10', $num_tot_pagos_parciales);


        $num_cumplimientos = (clone $this->service->pagosDiferidos($request))
            ->has('pagosDiferidos', '>=', 1)
            ->whereHas('expediente.solicitud', function ($q){ $q->where('inmediata', false);})
            ->whereHas('pagosDiferidos', function ($q){
                $q->where('pagado', true);
            })
            ->where('resolucion_id', ReportesService::RESOLUCIONES_HUBO_CONVENIO)
            ->get()->count();
        $sheet->setCellValue('T11', $num_cumplimientos);

        $num_incumplimientos = (clone $this->service->pagosDiferidos($request))
            ->whereHas('expediente.solicitud', function ($q){ $q->where('inmediata', false);})
            ->has('pagosDiferidos', '>=', 1)
            ->whereHas('pagosDiferidos', function ($q){
                $q->where('pagado', false);
            })
            ->where('resolucion_id', ReportesService::RESOLUCIONES_HUBO_CONVENIO)
            ->get()->count();;
        $sheet->setCellValue('T12', $num_incumplimientos);

        $num_vencidos = (clone $this->service->pagosDiferidos($request))
            ->whereHas('expediente.solicitud', function ($q){ $q->where('inmediata', false);})
            ->has('pagosDiferidos', '>=', 1)
            ->whereHas('pagosDiferidos', function ($q){
                $q->whereNull('pagado');
                $q->where('fecha_pago', '<', date('Y-m-d'));
            })
            ->where('resolucion_id', ReportesService::RESOLUCIONES_HUBO_CONVENIO)
            ->get()->count();
        $sheet->setCellValue('T13', $num_vencidos);

        $num_vigentes = (clone $this->service->pagosDiferidos($request))
            ->whereHas('expediente.solicitud', function ($q){ $q->where('inmediata', false);})
            ->has('pagosDiferidos', '>=', 1)
            ->whereHas('pagosDiferidos', function ($q){
                $q->whereNull('pagado');
                $q->where('fecha_pago', '>=', date('Y-m-d'));
            })
            ->where('resolucion_id', ReportesService::RESOLUCIONES_HUBO_CONVENIO)
            ->get()->count();
        $sheet->setCellValue('T14', $num_vigentes);

        # Total de pagos diferidos
        # Suma de pagos
        $num_pagos_dif = $num_vigentes+$num_vencidos+$num_incumplimientos+$num_cumplimientos;
        $sheet->setCellValue('T10', $num_pagos_dif);

        $sheet->setCellValue('U7', $num_pagos_dif);

        $sheet->setCellValue('U8', $num_pagos_tt->count() - $num_pagos_dif);

    }


    /**
     * Indicadores de eficiencia o eficacia, según la mtra. Gianni
     * Indicadores por centro
     * @param $sheet
     * @param $request
     */
    public function indicadoresPorCentro($sheet, $request)
    {
        $head_height = $this->head_height;
        $body_height = $head_height/2;

        ####
        # Por centro: número de convenios de conciliación no inmediata / constancias de no conciliación.

        $conciliacion_normal = (clone $this->service->audiencias($request))->with('expediente.solicitud.centro')
            ->whereHas('expediente.solicitud',function ($q){
                $q->where('inmediata', false);
            })
            ->where('resolucion_id', ReportesService::RESOLUCIONES_HUBO_CONVENIO)
            ->get()
            ->sortBy('abreviatura')
            ->groupBy('abreviatura')
            ->map(function($item){
                return $item->count();
            })
        ;

        $noconciliacion_normal = (clone $this->service->audiencias($request))
            ->with('expediente.solicitud.centro')->whereHas('expediente.solicitud',function ($q){
            $q->where('inmediata', false);
        })
            ->where('resolucion_id', ReportesService::RESOLUCIONES_NO_HUBO_CONVENIO)
            ->get()
            ->sortBy('abreviatura')
            ->groupBy('abreviatura')
            ->map(function($item){
                return $item->count();
            })
        ;

        $row_inicio = 4;

        $sheet->setCellValue('A'.($row_inicio -2), 'Centro');
        $sheet->setCellValue('B'.($row_inicio -2), "Constancia\nno conciliación normal");
        $sheet->setCellValue('C'.($row_inicio -2), "Convenio\nconciliación normal");
        $sheet->setCellValue('D'.($row_inicio -2), 'Ratio');

        $sheet->getStyle('A' . ($row_inicio -2) . ':D' . ($row_inicio -2))->applyFromArray($this->tf1(14));
        $sheet->getStyle('A'.($row_inicio -2).':F'.($row_inicio -2))->getAlignment()->setWrapText(true);
        $sheet->getRowDimension(($row_inicio -2))->setRowHeight($head_height);

        $sheet->getColumnDimension('A')->setAutoSize(true);
        $sheet->getColumnDimension('B')->setAutoSize(true);
        $sheet->getColumnDimension('C')->setAutoSize(true);
        $sheet->getColumnDimension('D')->setAutoSize(true);
        $sheet->getColumnDimension('E')->setAutoSize(true);
        $sheet->getColumnDimension('F')->setAutoSize(true);

        $sheet->getStyle('A' . $row_inicio . ':D' . ($row_inicio + count($this->centros_activos) +1))->applyFromArray($this->rog(12));

        $row_indicador = $row_inicio;
        foreach($this->centros_activos as $centro){
            $sheet->setCellValue('A'.$row_indicador, $centro);
            $sheet->setCellValue('B'.$row_indicador, isset($noconciliacion_normal[$centro])?$noconciliacion_normal[$centro]:0);
            $sheet->setCellValue('C'.$row_indicador, isset($conciliacion_normal[$centro])?$conciliacion_normal[$centro]:0);
            $sheet->setCellValue('D'.$row_indicador, '=ROUND(C'.$row_indicador."/B".$row_indicador.",2)");

            $sheet->getRowDimension(($row_indicador))->setRowHeight($body_height);
            $row_indicador++;
        }

        $sheet->getRowDimension(($row_indicador))->setRowHeight($body_height);
        $sheet->setCellValue('A'.$row_indicador, 'TOTAL');
        $sheet->setCellValue('B'.$row_indicador, '=SUM(B'.$row_inicio.":B".($row_indicador -1).")");
        $sheet->setCellValue('C'.$row_indicador, '=SUM(C'.$row_inicio.":C".($row_indicador -1).")");
        $sheet->setCellValue('D'.$row_indicador, '=ROUND(C'.$row_indicador."/B".$row_indicador.",2)");

        // Gráficas
        // CONVENIOS Y CONSTANCIAS DE NO CONCILIACIÓN (PROCEDIMIENTO NORMAL)
        // NÚMERO DE CONVENIOS Y CONSTANCIAS DE NO CONCILIACIÓN (PROCEDIMIENTO NORMAL)
        // RATIO CONVENIOS / CONSTANCIAS DE NO CONCILIACIÓN (PROCEDIMIENTO NORMAL)
        $this->pay($sheet, 'H'.($row_inicio -2),'P'.$row_indicador, 'CONVENIOS Y CONSTANCIAS DE NO CONCILIACIÓN (PROCEDIMIENTO NORMAL)',($row_inicio -2),$row_indicador, ($row_inicio -2));
        $this->columnasApiladas($sheet, 'Q'.($row_inicio -2),'Z'.$row_indicador, 'NÚMERO DE CONVENIOS Y CONSTANCIAS DE NO CONCILIACIÓN (PROCEDIMIENTO NORMAL)',($row_inicio -2),$row_inicio, $row_indicador);
        $this->columnas($sheet, 'AA'.($row_inicio -2),'AK'.$row_indicador, 'RATIO CONVENIOS / CONSTANCIAS DE NO CONCILIACIÓN (PROCEDIMIENTO NORMAL)',($row_inicio -2),$row_inicio, $row_indicador);

        ####
        # Por centro: convenios de conciliacón totales (inmediatas y no inmediatas) / convenios de no conciliación totales (inm y no inm)

        $conciliacion = (clone $this->service->audiencias($request))->with('expediente.solicitud.centro')
            ->where('resolucion_id', ReportesService::RESOLUCIONES_HUBO_CONVENIO)
            ->get()
            ->sortBy('abreviatura')
            ->groupBy('abreviatura')
            ->map(function($item){
                return $item->count();
            })
        ;

        $no_conciliacion = (clone $this->service->audiencias($request))
            ->where('resolucion_id', ReportesService::RESOLUCIONES_NO_HUBO_CONVENIO)
            ->get()
            ->sortBy('abreviatura')
            ->groupBy('abreviatura')
            ->map(function($item){
                return $item->count();
            })
        ;

        $row_indicador_totales = $row_indicador + 2;
        $sheet->setCellValue('A'.$row_indicador_totales, 'Centro');
        $sheet->setCellValue('B'.$row_indicador_totales, "Constancias\nno conciliación");
        $sheet->setCellValue('C'.$row_indicador_totales, "Convenios\nconciliación");
        $sheet->setCellValue('D'.$row_indicador_totales, 'Ratio');

        $sheet->duplicateStyle($sheet->getStyle('A'.($row_inicio -2)),'A'.$row_indicador_totales.':D'.$row_indicador_totales);
        $sheet->getRowDimension(($row_indicador_totales))->setRowHeight($head_height);

        $row_indicador_totales = $row_indicador_totales + 2;
        $sheet->getStyle('A' . $row_indicador_totales . ':D' . ($row_indicador_totales + count($this->centros_activos) +1))->applyFromArray($this->rog(12));

        $row_inicio_indicador_totales = $row_indicador_totales;

        foreach($this->centros_activos as $centro){
            $sheet->setCellValue('A'.$row_indicador_totales, $centro);
            $sheet->setCellValue('B'.$row_indicador_totales, isset($no_conciliacion[$centro]) ? $no_conciliacion[$centro] : 0);
            $sheet->setCellValue('C'.$row_indicador_totales, isset($conciliacion[$centro]) ? $conciliacion[$centro] : 0);
            $sheet->setCellValue('D'.$row_indicador_totales, '=ROUND(C'.$row_indicador_totales."/B".$row_indicador_totales.",2)");

            $sheet->getRowDimension(($row_indicador_totales))->setRowHeight($body_height);
            $row_indicador_totales++;
        }

        $sheet->getRowDimension(($row_indicador_totales))->setRowHeight($body_height);
        $sheet->setCellValue('A'.$row_indicador_totales, 'TOTAL');
        $sheet->setCellValue('B'.$row_indicador_totales, '=SUM(B'.$row_inicio_indicador_totales.":B".$row_indicador_totales.")");
        $sheet->setCellValue('C'.$row_indicador_totales, '=SUM(C'.$row_inicio_indicador_totales.":C".$row_indicador_totales.")");
        $sheet->setCellValue('D'.$row_indicador_totales, '=ROUND(C'.$row_indicador_totales."/B".$row_indicador_totales.",2)");

        # Gráficos
        // CONVENIOS Y CONSTANCIAS DE NO CONCILIACIÓN
        // NÚMERO DE CONVENIOS Y CONSTANCIAS DE NO CONCILIACIÓN
        // RATIO CONVENIOS / CONSTANCIAS DE NO CONCILIACIÓN
        $this->pay($sheet, 'H'.($row_inicio_indicador_totales -2),'P'.$row_indicador_totales, 'CONVENIOS Y CONSTANCIAS DE NO CONCILIACIÓN',($row_inicio_indicador_totales -2),$row_indicador_totales, ($row_inicio_indicador_totales -2));
        $this->columnasApiladas($sheet, 'Q'.($row_inicio_indicador_totales -2),'Z'.$row_indicador_totales, 'NÚMERO DE CONVENIOS Y CONSTANCIAS DE NO CONCILIACIÓN',($row_inicio_indicador_totales -2),$row_inicio_indicador_totales, $row_indicador_totales);
        $this->columnas($sheet, 'AA'.($row_inicio_indicador_totales -2),'AK'.$row_indicador_totales, 'RATIO CONVENIOS / CONSTANCIAS DE NO CONCILIACIÓN',($row_inicio_indicador_totales -2),$row_inicio_indicador_totales, $row_indicador_totales);

        #####
        # Por centro: número de convenios de conciliación no inmediata / constancias de no conciliación.

        $no_conciliacion_no_comparecencia = (clone $this->service->audiencias($request))->with('expediente.solicitud.centro')
            ->whereHas('expediente.solicitud',function ($q){
                $q->where('inmediata', false);
            })
            ->where('resolucion_id', ReportesService::RESOLUCIONES_NO_HUBO_CONVENIO)
            ->where('tipo_terminacion_audiencia_id', ReportesService::TERMINACION_AUDIENCIA_NO_COMPARECENCIA_CITADO)
            ->get()
            ->sortBy('abreviatura')
            ->groupBy('abreviatura')
            ->map(function($item){
                return $item->count();
            })
        ;

        $row_indicador_nocomparecencia = $row_indicador_totales + 2;
        $sheet->setCellValue('A'.$row_indicador_nocomparecencia, 'Centro');
        $sheet->setCellValue('B'.$row_indicador_nocomparecencia, "Constancias \nno conciliación normal");
        $sheet->setCellValue('C'.$row_indicador_nocomparecencia, "Convenio \nconciliación normal");
        $sheet->setCellValue('D'.$row_indicador_nocomparecencia, "Constancias \nno conciliación normal\n por no arreglo");
        $sheet->setCellValue('E'.$row_indicador_nocomparecencia, "Constancias \nno conciliación normal\n por no comparecencia\n del citado");
        $sheet->setCellValue('F'.$row_indicador_nocomparecencia, 'Ratio');

        $sheet->duplicateStyle($sheet->getStyle('A'.($row_inicio_indicador_totales -2)),'A'.$row_indicador_nocomparecencia.':F'.$row_indicador_nocomparecencia);
        $sheet->getRowDimension(($row_indicador_nocomparecencia))->setRowHeight($head_height);

        $row_indicador_nocomparecencia = $row_indicador_nocomparecencia + 2;
        $sheet->getStyle('A' . $row_indicador_nocomparecencia . ':F' . ($row_indicador_nocomparecencia + count($this->centros_activos) +1))->applyFromArray($this->rog(12));
        $row_inicio_inidicador_nocompetencia = $row_indicador_nocomparecencia;

        foreach($this->centros_activos as $centro){
            $sheet->setCellValue('A'.$row_indicador_nocomparecencia, $centro);
            $sheet->setCellValue('B'.$row_indicador_nocomparecencia, isset($noconciliacion_normal[$centro]) ? $noconciliacion_normal[$centro] : 0);
            $sheet->setCellValue('C'.$row_indicador_nocomparecencia, isset($conciliacion_normal[$centro]) ? $conciliacion_normal[$centro] : 0);
            $sheet->setCellValue('D'.$row_indicador_nocomparecencia, '=B'.$row_indicador_nocomparecencia."-E".$row_indicador_nocomparecencia);
            $sheet->setCellValue('E'.$row_indicador_nocomparecencia, isset($no_conciliacion_no_comparecencia[$centro]) ? $no_conciliacion_no_comparecencia[$centro] : 0);
            $sheet->setCellValue('F'.$row_indicador_nocomparecencia, '=ROUND(C'.$row_indicador_nocomparecencia."/(B".$row_indicador_nocomparecencia."-E".$row_indicador_nocomparecencia."),2)");

            $sheet->getRowDimension(($row_indicador_nocomparecencia))->setRowHeight($body_height);
            $row_indicador_nocomparecencia++;
        }

        $sheet->getRowDimension(($row_indicador_nocomparecencia))->setRowHeight($body_height);
        $sheet->setCellValue('A'.$row_indicador_nocomparecencia, 'TOTAL');
        $sheet->setCellValue('B'.$row_indicador_nocomparecencia, '=SUM(B'.$row_inicio_inidicador_nocompetencia.":B".$row_indicador_nocomparecencia.")");
        $sheet->setCellValue('C'.$row_indicador_nocomparecencia, '=SUM(C'.$row_inicio_inidicador_nocompetencia.":C".$row_indicador_nocomparecencia.")");
        $sheet->setCellValue('D'.$row_indicador_nocomparecencia, '=SUM(D'.$row_inicio_inidicador_nocompetencia.":D".$row_indicador_nocomparecencia.")");
        $sheet->setCellValue('E'.$row_indicador_nocomparecencia, '=SUM(E'.$row_inicio_inidicador_nocompetencia.":E".$row_indicador_nocomparecencia.")");
        $sheet->setCellValue('F'.$row_indicador_nocomparecencia, '=ROUND(C'.$row_indicador_nocomparecencia."/(B".$row_indicador_nocomparecencia."-E".$row_indicador_nocomparecencia."),2)");

        # Gráficos
        // CONVENIOS Y CONSTANCIAS DE NO CONCILIACIÓN POR NO ARREGLO Y POR NO COMPARECENCIA DEL CITADO (PROCEDIMIENTO NORMAL)
        // RATIO CONVENIOS / CONSTANCIAS DE NO CONCILIACIÓN POR NO ARREGLO (PROCEDIMIENTO NORMAL)
        // NÚMERO DE CONVENIOS Y CONSTANCIAS DE NO CONCILIACIÓN POR NO ARREGLO Y POR NO COMPARECENCIA DEL CITADO (PROCEDIMIENTO NORMAL)
        $this->pay($sheet, 'H'.($row_inicio_inidicador_nocompetencia -2),'P'.$row_indicador_nocomparecencia, 'CONVENIOS Y CONSTANCIAS DE NO CONCILIACIÓN POR NO ARREGLO Y POR NO COMPARECENCIA DEL CITADO (PROCEDIMIENTO NORMAL)',
            ($row_inicio_inidicador_nocompetencia -2), $row_indicador_nocomparecencia, ($row_inicio_inidicador_nocompetencia -2), true);

        $this->columnasApiladas($sheet, 'Q'.($row_inicio_inidicador_nocompetencia -2),'Z'.$row_indicador_nocomparecencia, 'NÚMERO DE CONVENIOS Y CONSTANCIAS DE NO CONCILIACIÓN POR NO ARREGLO Y POR NO COMPARECENCIA DEL CITADO (PROCEDIMIENTO NORMAL)',
            ($row_inicio_inidicador_nocompetencia -2), $row_inicio_inidicador_nocompetencia, $row_indicador_nocomparecencia, true);

        $this->columnas($sheet, 'AA'.($row_inicio_inidicador_nocompetencia -2),'AK'.$row_indicador_nocomparecencia, 'RATIO CONVENIOS / CONSTANCIAS DE NO CONCILIACIÓN POR NO ARREGLO (PROCEDIMIENTO NORMAL)',
            ($row_inicio_inidicador_nocompetencia -2), $row_inicio_inidicador_nocompetencia, $row_indicador_nocomparecencia, true);

    }

    /**
     * Indicadores de eficiencia o eficacia según la Mtra. Gianni
     * Indicadores por conciliador
     * @param $sheet
     * @param $request
     */
    public function indicadoresPorConciliador($sheet, $request)
    {
        $head_height = $this->head_height;

        ####
        # Por conciliador: número de convenios de conciliación no inmediata / constancias de no conciliación.

        $conciliacion_normal = (clone $this->service->audiencias($request))
            ->with('conciliador.persona')->whereHas('expediente.solicitud',function ($q){
                $q->where('inmediata', false);
            })
            ->where('resolucion_id', ReportesService::RESOLUCIONES_HUBO_CONVENIO)
            ->get()
            ->groupBy('conciliador_id')
            ->map(function($item){
                return $item->count();
            })
        ;

        $noconciliacion_normal = (clone $this->service->audiencias($request))
            ->with('conciliador.persona')->whereHas('expediente.solicitud', function ($q){
                $q->where('inmediata', false);
            })
            ->where('resolucion_id', ReportesService::RESOLUCIONES_NO_HUBO_CONVENIO)
            ->get()
            ->groupBy('conciliador_id')
            ->map(function($item){
                return $item->count();
            })
        ;

        $row_inicio = 4;

        $sheet->setCellValue('A'.($row_inicio -2), 'Conciliador');
        $sheet->setCellValue('B'.($row_inicio -2), "Constancia no\n conciliación normal");
        $sheet->setCellValue('C'.($row_inicio -2), "Convenio \nconciliación normal");
        $sheet->setCellValue('D'.($row_inicio -2), 'Ratio');

        $sheet->getStyle('A' . ($row_inicio -2) . ':D' . ($row_inicio -2))->applyFromArray($this->tf1(14));
        $sheet->getStyle('A'.($row_inicio -2).':F'.($row_inicio -2))->getAlignment()->setWrapText(true);
        $sheet->getRowDimension(($row_inicio -2))->setRowHeight($head_height);

        $sheet->getColumnDimension('A')->setAutoSize(true);
        $sheet->getColumnDimension('B')->setAutoSize(true);
        $sheet->getColumnDimension('C')->setAutoSize(true);
        $sheet->getColumnDimension('D')->setAutoSize(true);
        $sheet->getColumnDimension('E')->setAutoSize(true);
        $sheet->getColumnDimension('F')->setAutoSize(true);

        $sheet->getStyle('A' . $row_inicio . ':D' . ($row_inicio + count($this->conciliadores) +1))->applyFromArray($this->rog(12));

        $row_indicador = $row_inicio;
        foreach($this->conciliadores as $conciliador){
            $sheet->setCellValue('A'.$row_indicador, mb_strtoupper($conciliador->nombre));
            $sheet->setCellValue('B'.$row_indicador, isset($noconciliacion_normal[$conciliador->id])?$noconciliacion_normal[$conciliador->id]:0);
            $sheet->setCellValue('C'.$row_indicador, isset($conciliacion_normal[$conciliador->id])?$conciliacion_normal[$conciliador->id]:0);
            $sheet->setCellValue('D'.$row_indicador, '=ROUND(C'.$row_indicador."/B".$row_indicador.",2)");
            $row_indicador++;
        }

        $sheet->setCellValue('A'.$row_indicador, 'TOTAL');
        $sheet->setCellValue('B'.$row_indicador, '=SUM(B'.$row_inicio.":B".($row_indicador -1).")");
        $sheet->setCellValue('C'.$row_indicador, '=SUM(C'.$row_inicio.":C".($row_indicador -1).")");
        $sheet->setCellValue('D'.$row_indicador, '=ROUND(C'.$row_indicador."/B".$row_indicador.",2)");

        ####
        # Por conciliador: convenios de conciliacón totales (inmediatas y no inmediatas) / convenios de no conciliación totales (inm y no inm)

        $conciliacion = (clone $this->service->audiencias($request))->with('conciliador.persona')
            ->where('resolucion_id', ReportesService::RESOLUCIONES_HUBO_CONVENIO)
            ->get()
            ->groupBy('conciliador_id')
            ->map(function($item){
                return $item->count();
            })
        ;

        $no_conciliacion = (clone $this->service->audiencias($request))->with('conciliador.persona')
            ->where('resolucion_id', ReportesService::RESOLUCIONES_NO_HUBO_CONVENIO)
            ->get()
            ->groupBy('conciliador_id')
            ->map(function($item){
                return $item->count();
            })
        ;

        $row_indicador_totales = $row_indicador + 2;
        $row_inicio_indicador_totales = $row_indicador_totales;
        $sheet->setCellValue('A'.$row_indicador_totales, 'Conciliador');
        $sheet->setCellValue('B'.$row_indicador_totales, "Constancias \nno conciliación");
        $sheet->setCellValue('C'.$row_indicador_totales, "Convenios \nconciliación");
        $sheet->setCellValue('D'.$row_indicador_totales, 'Ratio');

        $sheet->duplicateStyle($sheet->getStyle('A'.($row_inicio -2)),'A'.$row_indicador_totales.':D'.$row_indicador_totales);
        $sheet->getRowDimension(($row_indicador_totales))->setRowHeight($head_height);

        $row_indicador_totales = $row_indicador_totales + 2;

        $sheet->getStyle('A' . $row_indicador_totales . ':D' . ($row_indicador_totales + count($this->conciliadores) +1))->applyFromArray($this->rog(12));

        foreach($this->conciliadores as $conciliador){
            $sheet->setCellValue('A'.$row_indicador_totales, mb_strtoupper($conciliador->nombre));
            $sheet->setCellValue('B'.$row_indicador_totales, isset($no_conciliacion[$conciliador->id]) ? $no_conciliacion[$conciliador->id] : 0);
            $sheet->setCellValue('C'.$row_indicador_totales, isset($conciliacion[$conciliador->id]) ? $conciliacion[$conciliador->id] : 0);
            $sheet->setCellValue('D'.$row_indicador_totales, '=ROUND(C'.$row_indicador_totales."/B".$row_indicador_totales.",2)");
            $row_indicador_totales++;
        }
        $sheet->setCellValue('A'.$row_indicador_totales, 'TOTAL');
        $sheet->setCellValue('B'.$row_indicador_totales, '=SUM(B'.$row_inicio_indicador_totales.":B".$row_indicador_totales.")");
        $sheet->setCellValue('C'.$row_indicador_totales, '=SUM(C'.$row_inicio_indicador_totales.":C".$row_indicador_totales.")");
        $sheet->setCellValue('D'.$row_indicador_totales, '=ROUND(C'.$row_indicador_totales."/B".$row_indicador_totales.",2)");

        ####
        # Por conciliador: número de convenios de conciliación no inmediata / constancias de no conciliación.

        $no_conciliacion_no_comparecencia = (clone $this->service->audiencias($request))->with('conciliador.persona')
            ->whereHas('expediente.solicitud',function ($q){
                $q->where('inmediata', false);
            })
            ->where('resolucion_id', ReportesService::RESOLUCIONES_NO_HUBO_CONVENIO)
            ->where('tipo_terminacion_audiencia_id', ReportesService::TERMINACION_AUDIENCIA_NO_COMPARECENCIA_CITADO)
            ->get()
            ->groupBy('conciliador_id')
            ->map(function($item){
                return $item->count();
            })
        ;

        $row_indicador_nocomparecencia = $row_indicador_totales + 2;
        $sheet->setCellValue('A'.$row_indicador_nocomparecencia, 'Conciliador');
        $sheet->setCellValue('B'.$row_indicador_nocomparecencia, "Constancias no\n conciliación normal");
        $sheet->setCellValue('C'.$row_indicador_nocomparecencia, "Convenio \nconciliación normal");
        $sheet->setCellValue('D'.$row_indicador_nocomparecencia, "Constancias no \nconciliación normal\n por no arreglo");
        $sheet->setCellValue('E'.$row_indicador_nocomparecencia, "Constancias no \nconciliación normal por \nno comparecencia\n del citado");
        $sheet->setCellValue('F'.$row_indicador_nocomparecencia, 'Ratio');

        $sheet->duplicateStyle($sheet->getStyle('A'.($row_inicio -2)),'A'.$row_indicador_nocomparecencia.':F'.$row_indicador_nocomparecencia);
        $sheet->getRowDimension(($row_indicador_nocomparecencia))->setRowHeight($head_height);

        $row_indicador_nocomparecencia = $row_indicador_nocomparecencia + 2;
        $sheet->getStyle('A' . $row_indicador_nocomparecencia . ':F' . ($row_indicador_nocomparecencia + count($this->conciliadores) +1))->applyFromArray($this->rog(12));

        $row_inicio_inidicador_nocompetencia = $row_indicador_nocomparecencia;
        foreach($this->conciliadores as $conciliador){
            $sheet->setCellValue('A'.$row_indicador_nocomparecencia, mb_strtoupper($conciliador->nombre));
            $sheet->setCellValue('B'.$row_indicador_nocomparecencia, isset($noconciliacion_normal[$conciliador->id]) ? $noconciliacion_normal[$conciliador->id] : 0);
            $sheet->setCellValue('C'.$row_indicador_nocomparecencia, isset($conciliacion_normal[$conciliador->id]) ? $conciliacion_normal[$conciliador->id] : 0);
            $sheet->setCellValue('D'.$row_indicador_nocomparecencia, '=B'.$row_indicador_nocomparecencia."-E".$row_indicador_nocomparecencia);
            $sheet->setCellValue('E'.$row_indicador_nocomparecencia, isset($no_conciliacion_no_comparecencia[$conciliador->id]) ? $no_conciliacion_no_comparecencia[$conciliador->id] : 0);
            $sheet->setCellValue('F'.$row_indicador_nocomparecencia, '=ROUND(C'.$row_indicador_nocomparecencia."/(B".$row_indicador_nocomparecencia."-E".$row_indicador_nocomparecencia."),2)");

            $row_indicador_nocomparecencia++;
        }

        $sheet->setCellValue('A'.$row_indicador_nocomparecencia, 'TOTAL');
        $sheet->setCellValue('B'.$row_indicador_nocomparecencia, '=SUM(B'.$row_inicio_inidicador_nocompetencia.":B".$row_indicador_nocomparecencia.")");
        $sheet->setCellValue('C'.$row_indicador_nocomparecencia, '=SUM(C'.$row_inicio_inidicador_nocompetencia.":C".$row_indicador_nocomparecencia.")");
        $sheet->setCellValue('D'.$row_indicador_nocomparecencia, '=SUM(D'.$row_inicio_inidicador_nocompetencia.":D".$row_indicador_nocomparecencia.")");
        $sheet->setCellValue('E'.$row_indicador_nocomparecencia, '=SUM(E'.$row_inicio_inidicador_nocompetencia.":E".$row_indicador_nocomparecencia.")");
        $sheet->setCellValue('F'.$row_indicador_nocomparecencia, '=ROUND(C'.$row_indicador_nocomparecencia."/(B".$row_indicador_nocomparecencia."-E".$row_indicador_nocomparecencia."),2)");

    }

    /**
     * Construye el gráfico de pay
     * @param $worksheet
     * @param $tl
     * @param $br
     * @param $titulo
     * @param $dsl1
     * @param $dsv1
     * @param $dsx1
     */
    public function pay($worksheet, $tl, $br, $titulo, $dsl1, $dsv1, $dsx1, $tipo=null)
    {
        //if($tipo) dd([$dsl1, $dsv1, $dsx1]);

        if($tipo){
            $dataSeriesLabels1 = [
                new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, '\'Indicadores por centro\'!$A$'.$dsv1, null, 1),
            ];
            $dataSeriesValues1 = [
                new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, "'Indicadores por centro'!\$C\$$dsv1:\$E\$$dsv1", null, 4),
            ];
            $xAxisTickValues1 = [
                new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, "'Indicadores por centro'!\$C\$$dsx1:\$E\$$dsx1", null, 4),
            ];
        }
        else{
            $dataSeriesLabels1 = [
                new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, '\'Indicadores por centro\'!$A$'.$dsv1, null, 1),
            ];
            $dataSeriesValues1 = [
                new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, '\'Indicadores por centro\'!$B$'.$dsv1.':$C$'.$dsv1, null, 4),
            ];
            $xAxisTickValues1 = [
                new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, '\'Indicadores por centro\'!$B$'.$dsx1.':$C$'.$dsx1, null, 4),
            ];
        }

        $series1 = new DataSeries(
            DataSeries::TYPE_PIECHART_3D,
            null,
            range(0, count($dataSeriesValues1) - 1),
            $dataSeriesLabels1,
            $xAxisTickValues1,
            $dataSeriesValues1
        );
        $layout1 = new Layout();
        $layout1->setShowVal(true);
        $layout1->setShowPercent(true);
        $plotArea1 = new PlotArea($layout1, [$series1]);
        $legend1 = new Legend(Legend::POSITION_BOTTOM, null, false);
        $title1 = new Title($titulo);
        $chart1 = new Chart(
            'chart1',
            $title1,
            $legend1,
            $plotArea1,
            true,
            DataSeries::EMPTY_AS_GAP,
            null,
            null
        );
        $chart1->setTopLeftPosition($tl);
        $chart1->setBottomRightPosition($br);
        $worksheet->addChart($chart1);
    }

    /**
     * Construye el gráfico de columnas apiladas
     * @param $worksheet
     * @param $tl
     * @param $br
     * @param $titulo
     * @param $dsl1
     * @param $dsv1
     * @param $dsx1
     */
    public function columnasApiladas($worksheet, $tl, $br, $titulo, $dsl1, $dsv1, $dsx1, $tipo=null)
    {
        //if($tipo) dd([$dsl1, $dsv1, $dsx1]);
        if($tipo){
            $dataSeriesLabels = [
                new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, '\'Indicadores por centro\'!$C$'.$dsl1, null, 1),
                new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, '\'Indicadores por centro\'!$D$'.$dsl1, null, 1),
                new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, '\'Indicadores por centro\'!$E$'.$dsl1, null, 1),
            ];

            $xAxisTickValues = [
                new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, '\'Indicadores por centro\'!$A$'.$dsv1.':$A$'.($dsx1 -1), null, 4),
            ];

            $dataSeriesValues = [
                new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, '\'Indicadores por centro\'!$C$'.$dsv1.':$C$'.($dsx1 -1), null, 4),
                new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, '\'Indicadores por centro\'!$D$'.$dsv1.':$D$'.($dsx1 -1), null, 4),
                new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, '\'Indicadores por centro\'!$E$'.$dsv1.':$E$'.($dsx1 -1), null, 4),
            ];
        }
        else {
            $dataSeriesLabels = [
                new DataSeriesValues(
                    DataSeriesValues::DATASERIES_TYPE_STRING,
                    '\'Indicadores por centro\'!$B$' . $dsl1,
                    null,
                    1
                ),
                new DataSeriesValues(
                    DataSeriesValues::DATASERIES_TYPE_STRING,
                    '\'Indicadores por centro\'!$C$' . $dsl1,
                    null,
                    1
                ),
            ];

            $xAxisTickValues = [
                new DataSeriesValues(
                    DataSeriesValues::DATASERIES_TYPE_STRING,
                    '\'Indicadores por centro\'!$A$' . $dsv1 . ':$A$' . ($dsx1 - 1),
                    null,
                    4
                ),
            ];

            $dataSeriesValues = [
                new DataSeriesValues(
                    DataSeriesValues::DATASERIES_TYPE_NUMBER,
                    '\'Indicadores por centro\'!$B$' . $dsv1 . ':$B$' . ($dsx1 - 1),
                    null,
                    4
                ),
                new DataSeriesValues(
                    DataSeriesValues::DATASERIES_TYPE_NUMBER,
                    '\'Indicadores por centro\'!$C$' . $dsv1 . ':$C$' . ($dsx1 - 1),
                    null,
                    4
                ),
            ];
        }
        $series = new DataSeries(
            DataSeries::TYPE_BARCHART,
            DataSeries::GROUPING_STACKED,
            range(0, count($dataSeriesValues) - 1),
            $dataSeriesLabels,
            $xAxisTickValues,
            $dataSeriesValues
        );
        $series->setPlotDirection(DataSeries::DIRECTION_COL);
        $plotArea = new PlotArea(null, [$series]);
        $legend = new Legend(Legend::POSITION_BOTTOM, null, false);
        $title = new Title($titulo);

        $chart = new Chart(
            'chart1',
            $title,
            $legend,
            $plotArea,
            true,
            DataSeries::EMPTY_AS_GAP,
            null,
            null
        );

        $chart->setTopLeftPosition($tl);
        $chart->setBottomRightPosition($br);
        $worksheet->addChart($chart);
    }

    /**
     * Columnas del ratio
     * @param $worksheet
     * @param $tl
     * @param $br
     * @param $titulo
     * @param $dsl1
     * @param $dsv1
     * @param $dsx1
     */
    public function columnas($worksheet, $tl, $br, $titulo, $dsl1, $dsv1, $dsx1, $tipo=null)
    {
        if($tipo) {
            $dataSeriesLabels = [
                new DataSeriesValues(
                    DataSeriesValues::DATASERIES_TYPE_STRING,
                    '\'Indicadores por centro\'!$F$' . $dsl1,
                    null,
                    1
                ),
            ];

            $xAxisTickValues = [
                new DataSeriesValues(
                    DataSeriesValues::DATASERIES_TYPE_STRING,
                    '\'Indicadores por centro\'!$A$' . $dsv1 . ':$A$' . ($dsx1 - 1),
                    null,
                    4
                ),
            ];

            $dataSeriesValues = [
                new DataSeriesValues(
                    DataSeriesValues::DATASERIES_TYPE_NUMBER,
                    '\'Indicadores por centro\'!$F$' . $dsv1 . ':$F$' . ($dsx1 - 1),
                    null,
                    4
                ),
            ];
        }
        else{
            $dataSeriesLabels = [
                new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, '\'Indicadores por centro\'!$D$'.$dsl1, null, 1),
            ];

            $xAxisTickValues = [
                new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_STRING, '\'Indicadores por centro\'!$A$'.$dsv1.':$A$'.($dsx1 -1), null, 4),
            ];

            $dataSeriesValues = [
                new DataSeriesValues(DataSeriesValues::DATASERIES_TYPE_NUMBER, '\'Indicadores por centro\'!$D$'.$dsv1.':$D$'.($dsx1 -1), null, 4),
            ];
        }

        $series = new DataSeries(
            DataSeries::TYPE_BARCHART,
            DataSeries::GROUPING_STACKED,
            range(0, count($dataSeriesValues) - 1),
            $dataSeriesLabels,
            $xAxisTickValues,
            $dataSeriesValues
        );
        $series->setPlotDirection(DataSeries::DIRECTION_COL);
        $plotArea = new PlotArea(null, [$series]);
        $legend = new Legend(Legend::POSITION_BOTTOM, null, false);
        $title = new Title($titulo);

        $chart = new Chart(
            'chart1',
            $title,
            null,
            $plotArea,
            true,
            DataSeries::EMPTY_AS_GAP,
            null,
            null
        );

        $chart->setTopLeftPosition($tl);
        $chart->setBottomRightPosition($br);
        $worksheet->addChart($chart);
    }
}
