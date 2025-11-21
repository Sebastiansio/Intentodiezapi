<?php

namespace App\Services;

use App\Centro;
use App\Traits\EstilosSpreadsheets;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ExcelReportesService
{
    use EstilosSpreadsheets;

    /**
     * Centros implementados en etapa x
     *
     * @var array
     */
    protected $imp = [];

    /**
     * Centros NO implementados en etapa x
     *
     * @var array
     */
    protected $noImp = [];

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->imp = Centro::whereNotNull('desde')->orderBy('abreviatura')->get()->pluck('abreviatura')->toArray();
        $this->noImp = Centro::whereNull('desde')->orderBy('abreviatura')->get()->pluck('abreviatura')->toArray();
    }

    /**
     * Construye la hoja de solicitudes presentadas
     */
    public function solicitudesPresentadas(Worksheet $sheet, $solicitudes, $request)
    {
        if ($request->get('tipo_reporte') == 'agregado') {
            // Seteo de encabezados
            $sheet->getStyle('A1')->applyFromArray($this->tituloH1());
            $sheet->getStyle('A3:B3')->applyFromArray($this->th1());
            $sheet->getColumnDimension('B')->setAutoSize(true);
            $sheet->setCellValue('A1', 'SOLICITUDES PRESENTADAS');
            $this->arrayToExcel([['CENTRO', 'PRESENTADAS']], $sheet, 3);

            $c = 4;
            // Procesamiento de los datos obtenidos, sólo se extraen la cantidad y la abreviatura
            // Si no existen en los centros de la primera etapa no se toman en cuenta los resultados por consiederarse outliers
            foreach ($solicitudes->pluck('count', 'abreviatura')->toArray() as $centro => $cantidad) {
                if (in_array($centro, $this->noImp)) {
                    continue;
                }
                $sheet->setCellValue('A'.$c, $centro);
                $sheet->setCellValue('B'.$c, $cantidad);
                $c++;
            }

            // Se agrega fórmula de totales al pie de la tabla
            $sheet->setCellValue('A'.$c, 'Total');
            $sheet->setCellValue('B'.$c, "=SUM(B3:B$c)")
                ->getStyle('B'.$c)->getNumberFormat()
                ->setFormatCode('#,##0');
            $sheet->getStyle('A3:B'.$c)->applyFromArray($this->tbody());
            $sheet->getStyle('A'.$c.':B'.$c)->applyFromArray($this->tf1());

            return;
        }

        // Los registros desagregados correspondientes a la consulta
        $encabezado = [
            'CENTRO',
            'FOLIO',
            'AÑO',
            'FECHA RECEPCIÓN',
            'FECHA CONFIRMACIÓN',
            'FECHA CONFLICTO',
            'INMEDIATA',
            'TIPO SOLICITUD',
            'OBJETO SOLICITUD',
            'INDUSTRIA',
            'SCIAN',
            'CÓDIGO (SCIAN)',
            'ID',
        ];

        // Seteo de encabezados
        $sheet->getStyle('A1')->applyFromArray($this->tituloH1());
        $sheet->getStyle('A3:L3')->applyFromArray($this->th1());
        $sheet->setCellValue('A1', 'SOLICITUDES PRESENTADAS (DESAGREGADO)');
        foreach ($this->excelColumnasRango(count($encabezado) - 1, 'B') as $columna) {
            $sheet->getColumnDimension($columna)->setAutoSize(true);
        }
        $this->arrayToExcel([$encabezado], $sheet, 3);

        // Procesamos los datos
        //dd($solicitudes);
        $s = $solicitudes->reject(
            function ($valor, $llave) {
                // Rechazamos cualquier dato no previsto en la primera etapa
                return in_array($valor->abreviatura, $this->noImp);
            }
        )->unique('id')->map(
            function ($v, $k) {
                // Extraemos los valores de los datos que vamos a poner en las columnas desagregadas únicamente
                $objeto = null;
                $objeto_solicitud = isset($v->objeto_solicitudes) ? $v->objeto_solicitudes->implode('nombre', ', ') : null;
                $industria_scian = isset($v->giroComercial) ? $v->giroComercial->nombre : null;
                $industria = isset($v->giroComercial->industria) ? $v->giroComercial->industria->nombre : null;
                $codigo_scian = isset($v->giroComercial) ? $v->giroComercial->codigo : null;

                return [
                    'abreviatura' => $v->abreviatura,
                    'folio' => $v->folio,
                    'anio' => $v->anio,
                    'fecha_recepcion' => $v->fecha_recepcion,
                    'fecha_confirmacion' => $v->fecha_ratificacion,
                    'fecha_conflicto' => $v->fecha_conflicto,
                    'inmediata' => $v->inmediata,
                    'tipo_solicitud' => isset($v->tipoSolicitud->nombre) ? $v->tipoSolicitud->nombre : null,
                    'objeto_solicitud' => $objeto_solicitud,
                    'industria' => $industria,
                    'scian' => $industria_scian,
                    'codigo_scian' => $codigo_scian,
                    'id' => $v->sid,
                ];
            }
        );
        //dump($s->toArray());
        //dd($s->toArray());
        $this->arrayToExcel($s->toArray(), $sheet, 4);
    }

    /**
     * Las solicitudes confirmadas
     */
    public function solicitudesConfirmadas($sheet, $solicitudes, $request)
    {
        if ($request->get('tipo_reporte') == 'agregado') {

            [$inmediata, $normal] = $solicitudes;

            $sheet->getStyle('A1')->applyFromArray($this->tituloH1());
            $sheet->getStyle('A3:D3')->applyFromArray($this->th1());
            $sheet->getColumnDimension('B')->setAutoSize(true);
            $sheet->getColumnDimension('C')->setAutoSize(true);
            $sheet->getColumnDimension('D')->setAutoSize(true);

            $sheet->setCellValue('A1', 'SOLICITUDES CONFIRMADAS');

            $sheet->setCellValue('A3', 'CENTRO');
            $sheet->setCellValue('B3', 'Confirmación de convenio');
            $sheet->setCellValue('C3', 'Procedimiento normal');
            $sheet->setCellValue('D3', 'Total');

            $c = 4;
            foreach ($this->imp as $centro) {
                $sheet->setCellValue('A'.$c, $centro);
                $sheet->setCellValue('B'.$c,
                    isset($inmediata[$centro]) ? count(
                        $inmediata[$centro]
                    ) : 0
                );
                $sheet->setCellValue(
                    'C'.$c,
                    isset($normal[$centro]) ? count($normal[$centro]) : 0
                );
                $sheet->setCellValue('D'.$c, "=SUM(B$c:C$c)");
                $c++;
            }
            $sheet->setCellValue('A'.$c, 'Total');
            $sheet->setCellValue('B'.$c, "=SUM(B4:B$c)")
                ->getStyle('B'.$c)->getNumberFormat()
                ->setFormatCode('#,##0');
            $sheet->setCellValue('C'.$c, "=SUM(C4:C$c)")
                ->getStyle('C'.$c)->getNumberFormat()
                ->setFormatCode('#,##0');
            $sheet->setCellValue('D'.$c, "=SUM(D4:D$c)")
                ->getStyle('D'.$c)->getNumberFormat()
                ->setFormatCode('#,##0');

            return;
        }

        // Los registros desagregados correspondientes a la consulta
        $encabezado = [
            'CENTRO',
            'FOLIO',
            'AÑO',
            'EXPEDIENTE',
            'FECHA RECEPCIÓN',
            'FECHA CONFIRMACIÓN',
            'FECHA CONFLICTO',
            'INMEDIATA',
            'TIPO SOLICITUD',
            'OBJETO SOLICITUD',
            'INDUSTRIA (SCIAN)',
            'CÓDIGO (SCIAN)',
            'ID',
        ];

        // Seteo de encabezados
        $sheet->getStyle('A1')->applyFromArray($this->tituloH1());
        $sheet->getStyle('A3:M3')->applyFromArray($this->th1());
        $sheet->setCellValue('A1', 'SOLICITUDES CONFIRMADAS (DESAGREGADO)');
        foreach ($this->excelColumnasRango(count($encabezado) - 1, 'B') as $columna) {
            $sheet->getColumnDimension($columna)->setAutoSize(true);
        }
        $this->arrayToExcel([$encabezado], $sheet, 3);

        // Procesamos los datos
        //dd($solicitudes);
        $s = $solicitudes->reject(
            function ($valor, $llave) {
                // Rechazamos cualquier dato no previsto en la primera etapa
                return in_array($valor->abreviatura, $this->noImp);
            }
        )->unique('id')->map(
            function ($v, $k) {
                // Extraemos los valores de los datos que vamos a poner en las columnas desagregadas únicamente
                $objeto = null;
                $objeto_solicitud = isset($v->objeto_solicitudes) ? $v->objeto_solicitudes->implode('nombre', ', ') : null;
                $industria = isset($v->giroComercial) ? $v->giroComercial->nombre : null;
                $codigo_scian = isset($v->giroComercial) ? $v->giroComercial->codigo : null;

                return [
                    'abreviatura' => $v->abreviatura,
                    'folio' => $v->folio,
                    'anio' => $v->anio,
                    'expediente' => $v->folio_unico,
                    'fecha_recepcion' => $v->fecha_recepcion,
                    'fecha_confirmacion' => $v->fecha_ratificacion,
                    'fecha_conflicto' => $v->fecha_conflicto,
                    'inmediata' => $v->inmediata,
                    'tipo_solicitud' => isset($v->tipoSolicitud->nombre) ? $v->tipoSolicitud->nombre : null,
                    'objeto_solicitud' => $objeto_solicitud,
                    'industria' => $industria,
                    'codigo_scian' => $codigo_scian,
                    'id' => $v->sid,
                    'deleted_at' => $v->deleted_at,
                ];
            }
        );
        $this->arrayToExcel($s->toArray(), $sheet, 4);
    }

    /**
     * Los citatorios emitidos
     */
    public function citatoriosEmitidos($citatoriosWorkSheet, $citatorios, $request)
    {
        if ($request->get('tipo_reporte') == 'agregado') {
            $citatoriosWorkSheet->getStyle('A1')->applyFromArray($this->tituloH1());
            $citatoriosWorkSheet->getStyle('I2')->applyFromArray($this->boldcenter());
            $citatoriosWorkSheet->getStyle('A3:L3')->applyFromArray($this->th1());
            $citatoriosWorkSheet->getColumnDimension('B')->setAutoSize(true);
            $citatoriosWorkSheet->getColumnDimension('C')->setAutoSize(true);
            $citatoriosWorkSheet->getColumnDimension('D')->setAutoSize(true);
            $citatoriosWorkSheet->getColumnDimension('E')->setAutoSize(true);
            $citatoriosWorkSheet->getColumnDimension('F')->setAutoSize(true);
            $citatoriosWorkSheet->getColumnDimension('G')->setAutoSize(true);
            $citatoriosWorkSheet->getColumnDimension('H')->setAutoSize(true);
            $citatoriosWorkSheet->getColumnDimension('I')->setAutoSize(true);
            $citatoriosWorkSheet->getColumnDimension('J')->setAutoSize(true);
            $citatoriosWorkSheet->getColumnDimension('K')->setAutoSize(true);
            $citatoriosWorkSheet->getColumnDimension('L')->setAutoSize(true);

            $citatoriosWorkSheet->setCellValue('A1', 'CITATORIOS EMITIDOS');

            $citatoriosWorkSheet->setCellValue('A3', 'CENTRO');
            $citatoriosWorkSheet->setCellValue('B3', 'Entrega solicitante');
            $citatoriosWorkSheet->setCellValue('C3', 'Entrega solicitante 1a');
            $citatoriosWorkSheet->setCellValue('D3', 'Entrega notificador');
            $citatoriosWorkSheet->setCellValue('E3', 'Entrega notificador 1a');
            $citatoriosWorkSheet->setCellValue('F3', 'Cita con notificador');
            $citatoriosWorkSheet->setCellValue('G3', 'Cita con notificador 1a');
            $citatoriosWorkSheet->setCellValue('H3', 'Total Citatorios');
            $citatoriosWorkSheet->setCellValue('I3', '1as audiencias');
            $citatoriosWorkSheet->setCellValue('J3', '2as audiencias');
            $citatoriosWorkSheet->setCellValue('K3', '3as audiencias');
            $citatoriosWorkSheet->setCellValue('L3', 'Total audiencias');

            $citatoriosWorkSheet->mergeCells('I2:L2');
            $citatoriosWorkSheet->setCellValue('I2', 'Número de audiencias para las que se emitió citatorio');
            $c = 4;
            foreach ($this->imp as $centro) {
                $citatoriosWorkSheet->setCellValue('A'.$c, $centro);

                // Entrega solicitante
                $citatoriosWorkSheet->setCellValue('B'.$c,
                    isset($citatorios['entrega_solicitante'][$centro]) ? $citatorios['entrega_solicitante'][$centro] : 0
                );
                $citatoriosWorkSheet->setCellValue('C'.$c,
                    isset($citatorios['entrega_solicitante_prim_aud'][$centro]) ? $citatorios['entrega_solicitante_prim_aud'][$centro] : 0
                );

                //Entrega notificador
                $citatoriosWorkSheet->setCellValue('D'.$c,
                    isset($citatorios['entrega_notificador'][$centro]) ? $citatorios['entrega_notificador'][$centro] : 0
                );
                $citatoriosWorkSheet->setCellValue('E'.$c,
                    isset($citatorios['entrega_notificador_prim_aud'][$centro]) ? $citatorios['entrega_notificador_prim_aud'][$centro] : 0
                );

                //Entrega notificador con cita
                $citatoriosWorkSheet->setCellValue('F'.$c,
                    isset($citatorios['entrega_notificador_cita'][$centro]) ? $citatorios['entrega_notificador_cita'][$centro] : 0
                );
                $citatoriosWorkSheet->setCellValue('G'.$c,
                    isset($citatorios['entrega_notificador_cita_prim_aud'][$centro]) ? $citatorios['entrega_notificador_cita_prim_aud'][$centro] : 0
                );

                $citatoriosWorkSheet->setCellValue('H'.$c, "=SUM(B$c,D$c,F$c)");

                $citatoriosWorkSheet->setCellValue(
                    'I'.$c,
                    isset($citatorios['citatorio_en_primera_audiencia'][$centro]) ? $citatorios['citatorio_en_primera_audiencia'][$centro] : 0
                );
                $citatoriosWorkSheet->setCellValue(
                    'J'.$c,
                    isset($citatorios['citatorio_en_segunda_audiencia'][$centro]) ? $citatorios['citatorio_en_segunda_audiencia'][$centro] : 0
                );
                $citatoriosWorkSheet->setCellValue(
                    'K'.$c,
                    isset($citatorios['citatorio_en_tercera_audiencia'][$centro]) ? $citatorios['citatorio_en_tercera_audiencia'][$centro] : 0
                );

                $citatoriosWorkSheet->setCellValue('L'.$c, "=SUM(I$c:K$c)");

                $c++;
            }
            $citatoriosWorkSheet->setCellValue('A'.$c, 'Total');
            //Entrega solicitante
            $citatoriosWorkSheet->setCellValue('B'.$c, "=SUM(B4:B$c)")
                ->getStyle('B'.$c)->getNumberFormat()
                ->setFormatCode('#,##0');
            $citatoriosWorkSheet->setCellValue('C'.$c, "=SUM(C4:C$c)")
                ->getStyle('C'.$c)->getNumberFormat()
                ->setFormatCode('#,##0');
            //Entrega notificador
            $citatoriosWorkSheet->setCellValue('D'.$c, "=SUM(D4:D$c)")
                ->getStyle('D'.$c)->getNumberFormat()
                ->setFormatCode('#,##0');
            $citatoriosWorkSheet->setCellValue('E'.$c, "=SUM(E4:E$c)")
                ->getStyle('E'.$c)->getNumberFormat()
                ->setFormatCode('#,##0');
            //Entrega notificador con cita
            $citatoriosWorkSheet->setCellValue('F'.$c, "=SUM(F4:F$c)")
                ->getStyle('F'.$c)->getNumberFormat()
                ->setFormatCode('#,##0');
            $citatoriosWorkSheet->setCellValue('G'.$c, "=SUM(G4:G$c)")
                ->getStyle('G'.$c)->getNumberFormat()
                ->setFormatCode('#,##0');

            //Tota
            $citatoriosWorkSheet->setCellValue('H'.$c, "=SUM(H4:H$c)")
                ->getStyle('H'.$c)->getNumberFormat()
                ->setFormatCode('#,##0');
            $citatoriosWorkSheet->setCellValue('I'.$c, "=SUM(I4:I$c)")
                ->getStyle('I'.$c)->getNumberFormat()
                ->setFormatCode('#,##0');

            $citatoriosWorkSheet->setCellValue('J'.$c, "=SUM(J4:J$c)")
                ->getStyle('J'.$c)->getNumberFormat()
                ->setFormatCode('#,##0');
            $citatoriosWorkSheet->setCellValue('K'.$c, "=SUM(K4:K$c)")
                ->getStyle('K'.$c)->getNumberFormat()
                ->setFormatCode('#,##0');
            $citatoriosWorkSheet->setCellValue('L'.$c, "=SUM(L4:L$c)")
                ->getStyle('L'.$c)->getNumberFormat()
                ->setFormatCode('#,##0');

            return;
        }

        // Para desagregados

        $citatoriosWorkSheet->setCellValue('A1', 'CITATORIOS EMITIDOS (DESAGREGADO)');

        // Los registros desagregados correspondientes a la consulta
        $encabezado = [
            'CENTRO',
            'FOLIO AUDIENCIA',
            'AÑO AUDIENCIA',
            'TIPO CITATORIO',
            '# AUDIENCIA',
            'FECHA CITATORIO',
            'EXPEDIENTE',
            'AUDIENCIA ID',
            'SOLICITUD ID',
            'CONCILIADOR ID',
            'CONCILIADOR',
            'PARTE ID',
            'AUDIENCIA PARTE ID',
        ];

        // Seteo de encabezados
        $tipos_notificaciones = ['1' => 'Entrega Solicitante', '2' => 'Entrega notificador', '3' => 'Cita con notificador'];
        foreach ($this->excelColumnasRango(count($encabezado) - 1, 'B') as $columna) {
            $citatoriosWorkSheet->getColumnDimension($columna)->setAutoSize(true);
        }
        $this->arrayToExcel([$encabezado], $citatoriosWorkSheet, 3);

        $s = $citatorios->reject(
            function ($valor, $llave) {
                // Rechazamos cualquier dato no previsto en la primera etapa
                return in_array($valor->abreviatura, $this->noImp);
            }
        )->map(
            function ($v, $k) use ($tipos_notificaciones) {
                // Extraemos los valores de los datos que vamos a poner en las columnas desagregadas únicamente
                return [
                    'abreviatura' => $v->abreviatura,
                    'folio_audiencia' => $v->folio,
                    'anio_audiencia' => $v->anio,
                    'tipo_citatorio' => isset($tipos_notificaciones[$v->tipo_notificacion_id]) ? $tipos_notificaciones[$v->tipo_notificacion_id] : null,
                    'num_audiencia' => $v->numero_audiencia,
                    'fecha_citatorio' => $v->fecha_citatorio,
                    'expediente' => $v->expediente_folio,
                    'audiencia_id' => $v->audiencia_id,
                    'solicitud_id' => $v->solicitud_id,
                    'conciliador_id' => $v->conciliador_id,
                    'conciliador' => trim($v->conciliador_nombre.' '.$v->conciliador_primer_apellido.' '.$v->conciliador_segundo_apellido),
                    'parte_id' => $v->parte_id,
                    'audiencia_parte_id' => $v->audiencia_parte_id,
                ];
            }
        );
        $this->arrayToExcel($s->toArray(), $citatoriosWorkSheet, 4);
    }

    /**
     * Las incompetencias declaradas
     */
    public function incompetencias($incompetenciasWorkSheet, $incompetencias, $request)
    {
        // INCOMPETENCIAS
        $incompetenciasWorkSheet->getStyle('A1')->applyFromArray($this->tituloH1());

        if ($request->get('tipo_reporte') == 'agregado') {
            $incompetenciasWorkSheet->getStyle('A3:D3')->applyFromArray($this->th1());
            $incompetenciasWorkSheet->getColumnDimension('B')->setAutoSize(true);
            $incompetenciasWorkSheet->getColumnDimension('C')->setAutoSize(true);
            $incompetenciasWorkSheet->getColumnDimension('D')->setAutoSize(true);

            $incompetenciasWorkSheet->setCellValue('A1', 'INCOMPETENCIAS');

            $incompetenciasWorkSheet->setCellValue('A3', 'CENTRO');
            $incompetenciasWorkSheet->setCellValue('B3', 'INCOMPETENCIA');
            $incompetenciasWorkSheet->setCellValue('C3', 'DETECTADA EN AUDIENCIA');
            $incompetenciasWorkSheet->setCellValue('D3', 'TOTAL');

            $c = 4;
            foreach ($this->imp as $centro) {
                $incompetenciasWorkSheet->setCellValue('A'.$c, $centro);
                $incompetenciasWorkSheet->setCellValue(
                    'B'.$c,
                    isset($incompetencias['en_ratificacion'][$centro]) ? $incompetencias['en_ratificacion'][$centro] : 0
                );
                $incompetenciasWorkSheet->setCellValue(
                    'C'.$c,
                    isset($incompetencias['en_audiencia'][$centro]) ? $incompetencias['en_audiencia'][$centro] : 0
                );
                $incompetenciasWorkSheet->setCellValue(
                    'D'.$c,
                    "=SUM(B$c:C$c)"
                );
                $c++;
            }

            $incompetenciasWorkSheet->setCellValue('A'.$c, 'Total');
            $incompetenciasWorkSheet->setCellValue('B'.$c, "=SUM(B3:B$c)")
                ->getStyle('B'.$c)->getNumberFormat()
                ->setFormatCode('#,##0');
            $incompetenciasWorkSheet->setCellValue('C'.$c, "=SUM(C3:C$c)")
                ->getStyle('C'.$c)->getNumberFormat()
                ->setFormatCode('#,##0');
            $incompetenciasWorkSheet->setCellValue('D'.$c, "=SUM(D3:D$c)")
                ->getStyle('D'.$c)->getNumberFormat()
                ->setFormatCode('#,##0');

            return;
        }

        // Reporte desagregado

        $encabezado = explode(',', 'CENTRO,SOLICITUD_ID,EXPEDIENTE,AUDIENCIA_ID,FECHA CONFIRMACIÓN,CONFIRMADA,DETECTADA EN');
        foreach ($this->excelColumnasRango(count($encabezado) - 1, 'B') as $columna) {
            $incompetenciasWorkSheet->getColumnDimension($columna)->setAutoSize(true);
        }
        $this->arrayToExcel([$encabezado], $incompetenciasWorkSheet, 3);

        $incompetenciasWorkSheet->getStyle('A3:G3')->applyFromArray($this->th1());

        $incompetenciasWorkSheet->setCellValue('A1', 'INCOMPETENCIAS (DESAGREGADO)');

        $rowsRatificacion = collect($incompetencias['en_ratificacion'])->map(function ($i) {
            return [
                'abreviatura' => $i['abreviatura'],
                'solicitud_id' => $i['solicitud_id'],
                'expediente' => $i['expediente'],
                'audiencia_id' => $i['audiencia_id'],
                'fecha_ratificacion' => $i['fecha_ratificacion'],
                'ratificada' => $i['ratificada'],
                'detectada_en' => 'CONFIRMACIÓN',
            ];
        });

        $rows = collect($incompetencias['en_audiencia'])->map(function ($i) {
            return [
                'abreviatura' => $i['abreviatura'],
                'solicitud_id' => $i['solicitud_id'],
                'expediente' => $i['expediente'],
                'audiencia_id' => $i['audiencia_id'],
                'fecha_ratificacion' => $i['fecha_ratificacion'],
                'ratificada' => $i['ratificada'],
                'detectada_en' => 'AUDIENCIA',
            ];
        });

        $rowsRatificacion->merge($rows->toArray())->toArray();

        $this->arrayToExcel($rowsRatificacion->merge($rows->toArray())->sortBy('abreviatura')->toArray(), $incompetenciasWorkSheet, 4);

    }

    /**
     * Archivados por falta de interés
     */
    public function archivoPorFaltaInteres(
        \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $archivadosWorkSheet,
        $archivados, $request
    ): void {
        $archivadosWorkSheet->getStyle('A1')->applyFromArray($this->tituloH1());
        if ($request->get('tipo_reporte') == 'agregado') {
            $archivadosWorkSheet->getStyle('A3:B3')->applyFromArray($this->th1());
            $archivadosWorkSheet->getColumnDimension('B')->setAutoSize(true);

            $archivadosWorkSheet->setCellValue('A1', 'ARCHIVO POR FALTA DE INTERÉS');
            $archivadosWorkSheet->setCellValue('A3', 'CENTRO');
            $archivadosWorkSheet->setCellValue('B3', 'SOLICITUDES');

            $c = 4;
            foreach ($this->imp as $centro) {
                $archivadosWorkSheet->setCellValue('A'.$c, $centro);
                $archivadosWorkSheet->setCellValue(
                    'B'.$c,
                    isset($archivados[$centro]) ? $archivados[$centro] : 0
                );
                $c++;
            }
            $archivadosWorkSheet->setCellValue('A'.$c, 'Total');
            $archivadosWorkSheet->setCellValue('B'.$c, "=SUM(B3:B$c)")
                ->getStyle('B'.$c)->getNumberFormat()
                ->setFormatCode('#,##0');

            return;
        }

        // Desagregado

        $encabezado = explode(',', 'CENTRO,FINALIZADA,SOLICITUD_ID,AUDIENCIA_ID,EXPEDIENTE,CONCILIADOR ID,CONCILIADOR,FECHA AUDIENCIA,NÚMERO AUDIENCIA,FECHA CONFIRMACIÓN,CONFIRMADA');
        foreach ($this->excelColumnasRango(count($encabezado) - 1, 'B') as $columna) {
            $archivadosWorkSheet->getColumnDimension($columna)->setAutoSize(true);
        }
        $this->arrayToExcel([$encabezado], $archivadosWorkSheet, 3);

        $archivadosWorkSheet->getStyle('A3:K3')->applyFromArray($this->th1());

        $archivadosWorkSheet->setCellValue('A1', 'ARCHIVADO POR FALTA DE INTERÉS (DESAGREGADO)');

        $res = $archivados->map(function ($item) {
            return [
                'abreviatura' => $item->abreviatura,
                'finalizada' => $item->finalizada,
                'solicitud_id' => $item->solicitud_id,
                'audiencia_id' => $item->audiencia_id,
                'expediente' => $item->expediente,
                'conciliador_id' => $item->conciliador_id,
                'conciliador' => trim($item->conciliador_nombre.' '.$item->conciliador_primer_apellido.' '.$item->conciliador_segundo_apellido),
                'fecha_audiencia' => $item->fecha_audiencia,
                'numero_audiencia' => $item->numero_audiencia,
                'fecha_ratificacion' => $item->fecha_ratificacion,
                'ratificada' => $item->ratificada,
            ];
        });

        $this->arrayToExcel($res, $archivadosWorkSheet, 4);

    }

    /**
     * Convenios de conciliación
     */
    public function convenios(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $conveniosWorkSheet, $convenios, $request)
    {
        $conveniosWorkSheet->getStyle('A1')->applyFromArray($this->tituloH1());
        if ($request->get('tipo_reporte') == 'agregado') {
            $conveniosWorkSheet->getStyle('A3:C3')->applyFromArray($this->th1());
            $conveniosWorkSheet->getColumnDimension('B')->setAutoSize(true);
            $conveniosWorkSheet->getColumnDimension('C')->setAutoSize(true);

            $conveniosWorkSheet->setCellValue('A1', 'CONVENIOS');
            $conveniosWorkSheet->setCellValue('A3', 'CENTRO');
            $conveniosWorkSheet->setCellValue('B3', 'SOLICITUDES');
            $conveniosWorkSheet->setCellValue('C3', 'IMPORTES');

            $c = 4;
            foreach ($this->imp as $centro) {

                $monto = $convenios->where('abreviatura', $centro)->sum('monto');
                $cantidad_solicitudes = $convenios->where('abreviatura', $centro)->unique('solicitud_id')->count();

                $conveniosWorkSheet->setCellValue('A'.$c, $centro);
                $conveniosWorkSheet->setCellValue('B'.$c, $cantidad_solicitudes);
                $conveniosWorkSheet->setCellValue('C'.$c, $monto)
                    ->getStyle('C'.$c)->getNumberFormat()->setFormatCode('#,##0.00');

                $c++;
            }
            $conveniosWorkSheet->setCellValue('A'.$c, 'Total');
            $conveniosWorkSheet->setCellValue('B'.$c, "=SUM(B3:B$c)")
                ->getStyle('B'.$c)->getNumberFormat()
                ->setFormatCode('#,##0');
            $conveniosWorkSheet->setCellValue('C'.$c, "=SUM(C3:C$c)")
                ->getStyle('C'.$c)->getNumberFormat()
                ->setFormatCode('#,##0.00');

            return;
        }

        // Desagregado

        $encabezado = explode(',', 'CENTRO,SOLICITUD_ID,AUDIENCIA_ID,EXPEDIENTE,CONCILIADOR_ID,CONCILIADOR,FECHA AUDIENCIA,NÚMERO AUDIENCIA,MONTO');
        foreach ($this->excelColumnasRango(count($encabezado) - 1, 'B') as $columna) {
            $conveniosWorkSheet->getColumnDimension($columna)->setAutoSize(true);
        }
        $this->arrayToExcel([$encabezado], $conveniosWorkSheet, 3);

        $conveniosWorkSheet->getStyle('A3:I3')->applyFromArray($this->th1());
        $conveniosWorkSheet->setCellValue('A1', 'CONVENIOS (DESAGREGADO)');

        $res = $convenios->map(function ($item) {
            return [
                'abreviatura' => $item->abreviatura,
                'solicitud_id' => $item->solicitud_id,
                'audiencia_id' => $item->audiencia_id,
                'expediente' => $item->expediente,
                'conciliador_id' => $item->conciliador_id,
                'conciliador' => trim($item->conciliador_nombre.' '.$item->conciliador_primer_apellido.' '.$item->conciliador_segundo_apellido),
                'fecha_audiencia' => $item->fecha_audiencia,
                'numero_audiencia' => $item->numero_audiencia,
                'monto' => $item->monto,
            ];
        });

        $conveniosWorkSheet->getStyle('I3:I'.($res->count() + 3))->getNumberFormat()->setFormatCode('#,##0.00');

        $this->arrayToExcel($res, $conveniosWorkSheet, 4);

    }

    /**
     * Convenios con ratificación (inmediatos)
     *
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     */
    public function conveniosRatificacion(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $conveniosWorkSheet, $convenios, $request)
    {
        $conveniosWorkSheet->getStyle('A1')->applyFromArray($this->tituloH1());

        if ($request->get('tipo_reporte') == 'agregado') {
            $conveniosWorkSheet->setCellValue('A1', 'CONFIRMACIÓN DE CONVENIOS');
            $conveniosWorkSheet->getStyle('A4:H4')->applyFromArray($this->th1());
            $conveniosWorkSheet->getStyle('C3:G3')->applyFromArray($this->boldcenter());
            $conveniosWorkSheet->getColumnDimension('B')->setAutoSize(true);
            $conveniosWorkSheet->getColumnDimension('C')->setAutoSize(true);
            $conveniosWorkSheet->getColumnDimension('D')->setAutoSize(true);
            $conveniosWorkSheet->getColumnDimension('E')->setAutoSize(true);
            $conveniosWorkSheet->getColumnDimension('F')->setAutoSize(true);
            $conveniosWorkSheet->getColumnDimension('G')->setAutoSize(true);
            $conveniosWorkSheet->getColumnDimension('H')->setAutoSize(true);

            $conveniosWorkSheet->mergeCells('C3:E3');
            $conveniosWorkSheet->setCellValue('C3', 'CONCLUIDAS');
            $conveniosWorkSheet->mergeCells('G3:H3');
            $conveniosWorkSheet->setCellValue('G3', 'SIN CONCLUIR');

            $conveniosWorkSheet->setCellValue('A4', 'Centro');
            $conveniosWorkSheet->setCellValue('B4', 'Solicitudes');
            $conveniosWorkSheet->setCellValue('C4', 'Hubo convenio');
            $conveniosWorkSheet->setCellValue('D4', 'Importe convenio');
            $conveniosWorkSheet->setCellValue('E4', 'Archivado');
            $conveniosWorkSheet->setCellValue('F4', 'Sin resolución');
            $conveniosWorkSheet->setCellValue('G4', 'No hubo convenio');
            $conveniosWorkSheet->setCellValue('H4', 'Sin resolución');

            //$solicitudes = $convenios->unique('solicitud_id')->groupBy('abreviatura');
            $solicitudes = $convenios->get()
                ->unique('solicitud_id')
                ->groupBy('abreviatura')
                ->map(
                    function ($item, $k) {
                        return $item->count();
                    }
                );

            $hubo_convenio = $convenios
                ->where('resolucion_id', ReportesService::RESOLUCIONES_HUBO_CONVENIO)
                ->get()
                ->unique('solicitud_id')->groupBy('abreviatura')->map(
                    function ($item, $k) {
                        return $item->count();
                    }
                );

            $monto_convenio = $convenios
                ->where('resolucion_id', ReportesService::RESOLUCIONES_HUBO_CONVENIO)
                ->get()
                ->groupBy('abreviatura')->map(
                    function ($item, $k) {
                        return $item->sum('monto');
                    }
                );

            $archivados = $convenios
                ->where('resolucion_id', ReportesService::RESOLUCIONES_ARCHIVADO)
                ->get()
                ->groupBy('abreviatura')->map(
                    function ($item, $k) {
                        return $item->unique('solicitud_id')->count();
                    }
                );

            //TODO ?
            $sin_resolucion = $convenios->where('resolucion_id', ReportesService::RESOLUCIONES_ARCHIVADO)
                ->get()->groupBy('abreviatura')->map(
                    function ($item, $k) {
                        return $item->count();
                    }
                );

            $no_hubo_convenio = $convenios->where('resolucion_id', ReportesService::RESOLUCIONES_NO_HUBO_CONVENIO)
                ->get()
                ->groupBy('abreviatura')->map(
                    function ($item, $k) {
                        return $item->unique('solicitud_id')->count();
                    }
                );

            $c = 5;
            foreach ($this->imp as $centro) {
                $conveniosWorkSheet->setCellValue('A'.$c, $centro);
                $conveniosWorkSheet->setCellValue('B'.$c, isset($solicitudes[$centro]) ? $solicitudes[$centro] : 0);
                $conveniosWorkSheet->setCellValue(
                    'C'.$c,
                    isset($hubo_convenio[$centro]) ? $hubo_convenio[$centro] : 0
                );
                $conveniosWorkSheet->setCellValue(
                    'D'.$c,
                    isset($monto_convenio[$centro]) ? $monto_convenio[$centro] : 0
                )->getStyle('D'.$c)->getNumberFormat()->setFormatCode('#,##0.00');

                $conveniosWorkSheet->setCellValue(
                    'E'.$c,
                    isset($archivados[$centro]) ? $archivados[$centro] : 0
                );

                //TODO de donde sale esto SIn resolución concluidas
                $conveniosWorkSheet->setCellValue(
                    'F'.$c,
                    0
                );

                $conveniosWorkSheet->setCellValue(
                    'G'.$c,
                    isset($no_hubo_convenio[$centro]) ? $no_hubo_convenio[$centro] : 0
                );

                //TODO de donde sale esto Sin resolución concluidas
                $conveniosWorkSheet->setCellValue(
                    'H'.$c,
                    0
                );

                $c++;
            }

            $conveniosWorkSheet->setCellValue('A'.$c, 'Total');
            $conveniosWorkSheet->setCellValue('B'.$c, "=SUM(B5:B$c)")
                ->getStyle('B'.$c)->getNumberFormat()
                ->setFormatCode('#,##0');
            $conveniosWorkSheet->setCellValue('C'.$c, "=SUM(C5:C$c)")
                ->getStyle('C'.$c)->getNumberFormat()
                ->setFormatCode('#,##0');
            $conveniosWorkSheet->setCellValue('D'.$c, "=SUM(D5:D$c)")
                ->getStyle('D'.$c)->getNumberFormat()
                ->setFormatCode('#,##0.00');
            $conveniosWorkSheet->setCellValue('E'.$c, "=SUM(E5:E$c)")
                ->getStyle('E'.$c)->getNumberFormat()
                ->setFormatCode('#,##0');
            $conveniosWorkSheet->setCellValue('F'.$c, "=SUM(F5:F$c)")
                ->getStyle('F'.$c)->getNumberFormat()
                ->setFormatCode('#,##0');
            $conveniosWorkSheet->setCellValue('G'.$c, "=SUM(G5:G$c)")
                ->getStyle('G'.$c)->getNumberFormat()
                ->setFormatCode('#,##0');
            $conveniosWorkSheet->setCellValue('H'.$c, "=SUM(H5:H$c)")
                ->getStyle('H'.$c)->getNumberFormat()
                ->setFormatCode('#,##0');

            return;
        }

        // Desagregado

        $encabezado = explode(',', 'CENTRO,SOLICITUD_ID,AUDIENCIA_ID,EXPEDIENTE,CONCILIADOR_ID,CONCILIADOR,FECHA AUDIENCIA,NÚMERO AUDIENCIA,RESOLUCIÓN,TIPO TERMINACIÓN,FINALIZADA,MONTO');
        foreach ($this->excelColumnasRango(count($encabezado) - 1, 'B') as $columna) {
            $conveniosWorkSheet->getColumnDimension($columna)->setAutoSize(true);
        }
        $this->arrayToExcel([$encabezado], $conveniosWorkSheet, 3);

        $conveniosWorkSheet->getStyle('A3:L3')->applyFromArray($this->th1());
        $conveniosWorkSheet->setCellValue('A1', 'CONFIRMACIÓN DE CONVENIOS (DESAGREGADO)');

        $res = $convenios->map(function ($item) {
            return [
                'abreviatura' => $item->abreviatura,
                'solicitud_id' => $item->solicitud_id,
                'audiencia_id' => $item->audiencia_id,
                'expediente' => $item->expediente,
                'conciliador_id' => $item->conciliador_id,
                'conciliador' => trim($item->conciliador_nombre.' '.$item->conciliador_primer_apellido.' '.$item->conciliador_segundo_apellido),
                'fecha_audiencia' => $item->fecha_audiencia,
                'numero_audiencia' => $item->numero_audiencia,
                'resolucion' => $item->resolucion,
                'tipo_terminacion' => $item->tipo_terminacion,
                'finalizada' => $item->audiencia_finalizada,
                'monto' => $item->monto,
            ];
        });

        $conveniosWorkSheet->getStyle('L3:L'.($res->count() + 3))->getNumberFormat()->setFormatCode('#,##0.00');

        $this->arrayToExcel($res, $conveniosWorkSheet, 4);

    }

    /**
     * No conciliación
     */
    public function noConciliacion(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $workSheet, $noconciliacion, $request)
    {
        $workSheet->getStyle('A1')->applyFromArray($this->tituloH1());
        if ($request->get('tipo_reporte') == 'agregado') {
            $workSheet->getStyle('A3:D3')->applyFromArray($this->th1());
            $workSheet->getColumnDimension('B')->setAutoSize(true);
            $workSheet->getColumnDimension('C')->setAutoSize(true);
            $workSheet->getColumnDimension('D')->setAutoSize(true);

            $workSheet->getStyle('B3:D3')->getAlignment()->setWrapText(true);

            $workSheet->setCellValue('A1', 'NO CONCILIACIÓN');
            $workSheet->setCellValue('A3', 'CENTRO');
            $workSheet->setCellValue('B3', "No conciliación\n(procedimiento normal)");
            $workSheet->setCellValue('C3', "No conciliación\n(confirmación de\nconvenios -\nsolicitudes concluidas)");
            $workSheet->setCellValue('D3', "Total de solicitudes\ndonde se emitió\nconstancia de no\nconciliación");

            $normal = $noconciliacion->where('inmediata', false)->where('audiencia_finalizada', true)->unique('solicitud_id')->groupBy('abreviatura')->map(
                function ($item, $k) {
                    return $item->count();
                }
            );
            $inmediata = $noconciliacion->where('inmediata', true)->unique('solicitud_id')->groupBy('abreviatura')->map(
                function ($item, $k) {
                    return $item->count();
                }
            );

            $c = 4;
            foreach ($this->imp as $centro) {
                $workSheet->setCellValue('A'.$c, $centro);
                $workSheet->setCellValue('B'.$c, isset($normal[$centro]) ? $normal[$centro] : 0);
                $workSheet->setCellValue('C'.$c, isset($inmediata[$centro]) ? $inmediata[$centro] : 0);
                $workSheet->setCellValue('D'.$c, "=SUM(B$c:C$c)");
                $c++;
            }
            $workSheet->setCellValue('A'.$c, 'Total');
            $workSheet->setCellValue('B'.$c, "=SUM(B3:B$c)")
                ->getStyle('B'.$c)->getNumberFormat()
                ->setFormatCode('#,##0');
            $workSheet->setCellValue('C'.$c, "=SUM(C3:C$c)")
                ->getStyle('C'.$c)->getNumberFormat()
                ->setFormatCode('#,##0');
            $workSheet->setCellValue('D'.$c, "=SUM(D3:D$c)")
                ->getStyle('D'.$c)->getNumberFormat()
                ->setFormatCode('#,##0');

            return;
        }
        // Desagregado

        $encabezado = explode(',', 'CENTRO,SOLICITUD_ID,AUDIENCIA_ID,EXPEDIENTE,CONCILIADOR_ID,CONCILIADOR,FECHA AUDIENCIA,NÚMERO AUDIENCIA,RESOLUCIÓN,PROCEDIMIENTO,FINALIZADA');
        foreach ($this->excelColumnasRango(count($encabezado) - 1, 'B') as $columna) {
            $workSheet->getColumnDimension($columna)->setAutoSize(true);
        }
        $this->arrayToExcel([$encabezado], $workSheet, 3);

        $workSheet->getStyle('A3:K3')->applyFromArray($this->th1());
        $workSheet->setCellValue('A1', 'NO CONCILIACIÓN (DESAGREGADO)');

        $res = $noconciliacion->map(function ($item) {
            return [
                'abreviatura' => $item->abreviatura,
                'solicitud_id' => $item->solicitud_id,
                'audiencia_id' => $item->audiencia_id,
                'expediente' => $item->expediente,
                'conciliador_id' => $item->conciliador_id,
                'conciliador' => trim($item->conciliador_nombre.' '.$item->conciliador_primer_apellido.' '.$item->conciliador_segundo_apellido),
                'fecha_audiencia' => $item->fecha_audiencia,
                'numero_audiencia' => $item->numero_audiencia,
                'resolucion' => $item->resolucion,
                'inmediata' => $item->inmediata ? 'CONFIRMACIÓN CONVENIO' : 'NORMAL',
                'finalizada' => $item->audiencia_finalizada,
            ];
        });

        $this->arrayToExcel($res, $workSheet, 4);

    }

    /**
     * Las audiencias
     */
    public function audiencias(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $workSheet, $audiencias, $request)
    {
        $workSheet->getStyle('A1')->applyFromArray($this->tituloH1());
        if ($request->get('tipo_reporte') == 'agregado') {
            $workSheet->getStyle('A3:B3')->applyFromArray($this->th1());
            $workSheet->getColumnDimension('B')->setAutoSize(true);

            $workSheet->getStyle('B3')->getAlignment()->setWrapText(true);

            $workSheet->setCellValue('A1', 'AUDIENCIAS');
            $workSheet->setCellValue('A3', 'CENTRO');
            $workSheet->setCellValue('B3', "Audiencias\nconcluidas");

            $resultados = $audiencias->where('audiencia_finalizada', true)->groupBy('abreviatura')->map(
                function ($item, $k) {
                    return $item->count();
                }
            );

            $c = 4;
            foreach ($this->imp as $centro) {
                $valor = isset($resultados[$centro]) ? $resultados[$centro] : 0;
                $workSheet->setCellValue('A'.$c, $centro);
                $workSheet->setCellValue('B'.$c, $valor);
                $c++;
            }
            $workSheet->setCellValue('A'.$c, 'Total');
            $workSheet->setCellValue('B'.$c, "=SUM(B3:B$c)")
                ->getStyle('B'.$c)->getNumberFormat()
                ->setFormatCode('#,##0');

            return;
        }

        // Desagregado
        $encabezado = explode(',', 'CENTRO,SOLICITUD_ID,AUDIENCIA_ID,EXPEDIENTE,CONCILIADOR ID,CONCILIADOR,FECHA AUDIENCIA,NÚMERO AUDIENCIA,INMEDIATA,FINALIZADA');
        foreach ($this->excelColumnasRango(count($encabezado) - 1, 'B') as $columna) {
            $workSheet->getColumnDimension($columna)->setAutoSize(true);
        }
        $this->arrayToExcel([$encabezado], $workSheet, 3);

        $workSheet->getStyle('A3:J3')->applyFromArray($this->th1());
        $workSheet->setCellValue('A1', 'AUDIENCIAS (DESAGREGADO)');

        $res = $audiencias->map(function ($item) {
            return [
                'abreviatura' => $item->abreviatura,
                'solicitud_id' => $item->solicitud_id,
                'audiencia_id' => $item->audiencia_id,
                'expediente' => $item->expediente,
                'conciliador_id' => $item->conciliador_id,
                'conciliador' => trim($item->conciliador_nombre.' '.$item->conciliador_primer_apellido.' '.$item->conciliador_segundo_apellido),
                'fecha_audiencia' => $item->fecha_audiencia,
                'numero_audiencia' => $item->numero_audiencia,
                'tipo_solicitud' => $item->inmediata,
                'finalizada' => $item->audiencia_finalizada,
            ];
        });

        $this->arrayToExcel($res, $workSheet, 4);

    }

    /**
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     */
    public function pagosDiferidos(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $workSheet, $pagosdiferidos, $request)
    {
        $workSheet->getStyle('A1')->applyFromArray($this->tituloH1());
        if ($request->get('tipo_reporte') == 'agregado') {
            $workSheet->getStyle('A4:D4')->applyFromArray($this->th1());
            $workSheet->getColumnDimension('B')->setAutoSize(true);
            $workSheet->getColumnDimension('C')->setAutoSize(true);
            $workSheet->getColumnDimension('D')->setAutoSize(true);
            $workSheet->getStyle('B3:D3')->applyFromArray($this->boldcenter());

            $workSheet->mergeCells('B3:D3');
            $workSheet->setCellValue('B3', 'Pagos diferidos');

            $workSheet->getStyle('B3:D3')->getAlignment()->setWrapText(true);

            $workSheet->setCellValue('A1', 'PAGOS DIFERIDOS');
            $workSheet->setCellValue('A4', 'CENTRO');
            $workSheet->setCellValue('B4', 'Vencidos');
            $workSheet->setCellValue('C4', 'Incumplimiento');
            $workSheet->setCellValue('D4', 'Pagado');

            $pagados = $pagosdiferidos->where('pagado', true)->groupBy('abreviatura')->map(
                function ($item, $k) {
                    return $item->count();
                }
            );
            $incumplimientos = $pagosdiferidos->where('pagado', false)->whereNotNull('pagado')->groupBy('abreviatura')->map(
                function ($item, $k) {
                    return $item->count();
                }
            );
            $vencidos = $pagosdiferidos->whereNull('pagado')->groupBy('abreviatura')->map(
                function ($item, $k) {
                    return $item->count();
                }
            );

            $c = 5;
            foreach ($this->imp as $centro) {
                $workSheet->setCellValue('A'.$c, $centro);
                $workSheet->setCellValue('B'.$c, isset($vencidos[$centro]) ? $vencidos[$centro] : 0);
                $workSheet->setCellValue('C'.$c, isset($incumplimientos[$centro]) ? $incumplimientos[$centro] : 0);
                $workSheet->setCellValue('D'.$c, isset($pagados[$centro]) ? $pagados[$centro] : 0);
                $c++;
            }
            $workSheet->setCellValue('A'.$c, 'Total');
            $workSheet->setCellValue('B'.$c, "=SUM(B3:B$c)")
                ->getStyle('B'.$c)->getNumberFormat()
                ->setFormatCode('#,##0');
            $workSheet->setCellValue('C'.$c, "=SUM(C3:C$c)")
                ->getStyle('C'.$c)->getNumberFormat()
                ->setFormatCode('#,##0');
            $workSheet->setCellValue('D'.$c, "=SUM(D3:D$c)")
                ->getStyle('D'.$c)->getNumberFormat()
                ->setFormatCode('#,##0');

            return;
        }

        // Desagregado

        $encabezado = explode(',', 'CENTRO,SOLICITUD_ID,AUDIENCIA_ID,EXPEDIENTE,CONCILIADOR ID,CONCILIADOR,FECHA AUDIENCIA,FECHA PAGO,PAGADO');
        foreach ($this->excelColumnasRango(count($encabezado) - 1, 'B') as $columna) {
            $workSheet->getColumnDimension($columna)->setAutoSize(true);
        }
        $this->arrayToExcel([$encabezado], $workSheet, 3);

        $workSheet->getStyle('A3:I3')->applyFromArray($this->th1());
        $workSheet->setCellValue('A1', 'PAGOS DIFERIDOS (DESAGREGADO)');

        $res = $pagosdiferidos->map(function ($item) {
            return [
                'abreviatura' => $item->abreviatura,
                'solicitud_id' => $item->solicitud_id,
                'audiencia_id' => $item->audiencia_id,
                'expediente' => $item->expediente,
                'conciliador_id' => $item->conciliador_id,
                'conciliador' => trim($item->conciliador_nombre.' '.$item->conciliador_primer_apellido.' '.$item->conciliador_segundo_apellido),
                'fecha_audiencia' => $item->fecha_audiencia,
                'numero_audiencia' => Carbon::createFromFormat('Y-m-d H:i:s', $item->fecha_pago)->format('Y-m-d'),
                'finalizada' => ($item->pagado === null) ? null : $item->pagado,
            ];
        });

        $this->arrayToExcel($res, $workSheet, 4);

    }

    //////////////////////////////////////////////////////////////////////////////////////////////////////////

    /**
     * Array a filas y columnas de excel
     *
     * @TODO: Mejorar el algoritmo, talvez peuda eficientarse la velocidad de creación
     *
     * @param  $rows  array Arreglo de los registros que se van a presentar en el excel
     * @param  $sheet  Worksheet Hoja en la que se va a vaciar el arreglo
     * @param  $idx  integer Fila desde la que se va a comenzar a vaciar el arreglo
     */
    private function arrayToExcel($rows, $sheet, $idx)
    {
        if (! $idx) {
            $idx = 1;
        }
        $id = 0;
        foreach ($rows as $row) {
            $c = 0;
            $vals = array_values($row);
            foreach ($this->excelColumnasRango(count($row)) as $i => $value) {
                $sheet->setCellValue($value.$idx, $vals[$i]);
            }
            $idx++;
            $id++;
        }
    }

    /**
     * Regresa un arreglo con las columnas numeradas dada una cantidad de elementos, un número inicial de columna y una letra mínima de columna
     *
     * @param  $columnas  integer Número de columnas que se debe pasar a letras
     * @param  $inicial  string Letra inicial con la que va a comenzar el conteo de columnas
     */
    public function excelColumnasRango($columnas, $inicial = null)
    {
        if (! $inicial) {
            $inicial = 'A';
        }
        $columna = 1;
        $bandera = true;
        while ($bandera) {
            if ($columna >= $columnas) {
                $bandera = false;
            }
            $columna++;
            yield $inicial;
            $inicial++;
        }
    }
}
