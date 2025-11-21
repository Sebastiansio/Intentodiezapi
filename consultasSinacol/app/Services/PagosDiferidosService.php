<?php

namespace App\Services;

use App\Audiencia;
use App\Municipio;
use App\AudienciaParte;
use App\Parte;
use App\Models\Bitacora;
use App\ResolucionPagoDiferido;
use App\DatoLaboral;
use App\SalarioMinimo;

use Carbon\Carbon;

class PagosDiferidosService
{
    /**
     * Verifica si la deuda ha sido completada
     *
     * @param array $pago
     * @param array $montoConvenio
     */
    public function isPagosConvenioCompletados($pago, $montoConvenio)
    {
        $montoPagado = $pago['monto_pago_realizado'];
        $montoRestante = $montoConvenio['monto_pendiente'] - $montoPagado;

        if ($montoRestante <= 0)
            return true;

        return false;
    }

    /**
     * Calcula la penalización por pago retrazado
     * En caso que los citados no se presenten a la audiencia
     *
     * @param int $audiencia_id
     * @param string $fecha_pago
     * @param string $fecha_cumplimiento
     * @return float
     */
    public function calcularPenalizacion($pagoDiferido, $fecha_cumplimiento)
    {
        $audiencia_id = $pagoDiferido->audiencia_id;
        $fecha_pago = $pagoDiferido->fecha_pago;

        $fechaDesde = Carbon::createFromFormat('Y-m-d', $this->formatDate($fecha_pago)->format('Y-m-d'))->startOfDay();
        $fechaHasta = Carbon::createFromFormat('Y-m-d', $this->formatDate($fecha_cumplimiento)->format('Y-m-d'))->startOfDay();

        # si fecha cumplimiento es menor a la fecha de pago no se aplica penalización
        if ($fechaHasta < $fechaDesde){
            return [
                'dias' => 0,
                'salario_minimo' => 0,
                'monto' => 0
            ];
        }

        $anioFechaSalida = null;

        $parteSolicitante = AudienciaParte::with('parte')
            ->where('audiencias_partes.audiencia_id', $audiencia_id)
            ->where('audiencias_partes.parte_id', $pagoDiferido->solicitante_id)
            ->first();

        $parteDatoLaboral = $parteSolicitante->parte->dato_laboral->first();

        if($parteDatoLaboral->fecha_salida){
            $anioFechaSalida = date('Y', strtotime($parteDatoLaboral->fecha_salida));
        }else{
            $anioFechaSalida = date('Y');
        } 

        # obtener el salario minimo
        $salarioMinimo = SalarioMinimo::where('slug', "penalizacion_salario_minimo_" . $anioFechaSalida)
                                    ->where('anio', $anioFechaSalida)
                                    ->orderBy('created_at', 'desc')
                                    ->first();

        if (!$salarioMinimo)
            throw new \Exception('No se encontró el salario mínimo para el año ' . $anioFechaSalida);

        # obtener datos del citado para obtener el domicilio
        $parteCitado = AudienciaParte::with('parte')
            ->join('partes', 'audiencias_partes.parte_id', '=', 'partes.id')
            ->where('audiencias_partes.audiencia_id', $audiencia_id)
            ->where('partes.tipo_parte_id', 2) # 2 citado
            ->first();

        if (!$parteCitado)
            return [
                'dias' => 0,
                'salario_minimo' => 0,
                'monto' => 0
            ];

        $estado_id = $parteCitado->parte->domicilios[0]->estado_id;
        $municipio = Municipio::where('estado_id', $estado_id)
                            ->where('municipio', 'like', '%'.$parteCitado->parte->domicilios[0]->municipio.'%')
                            ->first();

        if ($this->findMunicipio($estado_id, $municipio->id)) {
            $salario_minimo = $salarioMinimo->salario_minimo_zona_libre;
        } else {
            $salario_minimo = $salarioMinimo->salario_minimo;
        }

        

        $dias = $fechaDesde->diffInDays($fechaHasta);
        return [
            'dias' => $dias,
            'salario_minimo' => $salario_minimo,
            'monto' => round($dias * $salario_minimo, 2)
        ];
    }

    /**
     * Verifica si el municipio pertenece a un estado fronterizo
     *
     * @param int $estado_id
     * @param int $municipio_id
     * @return bool
     */
    public function findMunicipio($estado_id, $municipio_id)
    {
        $tabla = $this->tablaSalarioMinimoEstadoMunicipio();

        if (array_key_exists($estado_id, $tabla)) {
            if (in_array($municipio_id, $tabla[$estado_id])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Formatea la fecha
     * 
     * @param string $date
     */
    public function formatDate($date)
    {
        $formato = strpos($date, '/') !== false ? 'd/m/Y' : 'Y-m-d H:i:s';
        $date = \DateTime::createFromFormat($formato, $date);

        if (!$date)
            throw new \Exception('Formato de fecha inválido');

        return $date;
    }

    /**
     * Tabla de municipios por estado
     *
     * @return array
     */
    public function tablaSalarioMinimoEstadoMunicipio()
    {
        return [
            '02' => [ #BAJA CALIFORNIA
                582, #ENSENADA
                1223, #PLAYAS DE ROSARITO
                2142, #TIJUANA
                1012, #MEXICALI
                2010, #TECATE
            ],
            '26' => [ #SONORA
                1540, #SAN LUIS RÍO COLORADO
                1242, #PUERTO PEÑASCO
                626, #GENERAL PLUTARCO ELÍAS CALLES
                246, #CABORCA
                80, #ALTAR
                1915, #SARIC
                2243, #TUBUTAMA
                1152, #OQUITOA
                159, #ATIL
                1107, #NOGALES
                1735, #SANTA CRUZ
                272, #CANANEA
                1076, #NACO
                42, #AGUA PRIETA
                611, #FRONTERAS
            ],
            '08' => [ #CHIHUAHUA
                811, #JANOS
                142, #ASCENSIÓN
                855, #JUÁREZ
                1230, #PRAXEDIS G. GUERRERO
                643, #GUADALUPE
                54, #AHUMADA
                471, #COYAME DEL SOTOL
                1140, #OJINAGA
                961, #MANUEL BENAVIDES
            ],
            '05' => [ #COAHUILA
                38, #ACUÑA
                2430, #ZARAGOZA
                1213, #PIEDRAS NEGRAS
                825, #JIMÉNEZ
                1092, #NAVA
                660, #GUERRERO
                674, #HIDALGO
            ],
            '19' => [ #NUEVO LEÓN
                104 #ANÁHUAC
            ],
            '28' => [ #TAMAULIPAS
                1117, #NUEVO LAREDO
                661, #GUERRERO
                1022, #MIER
                1024, #MIGUEL ALEMÁN
                265, #CAMARGO
                664, #GUSTAVO DÍAZ ORDAZ
                1270, #REYNOSA
                1273, #RÍO BRAVO
                2296, #VALLE HERMOSO
                981, #MATAMOROS
            ],
        ];
    }

    /**
     * Obtiene los pagos de un convenio de pago
     * 
     * @param Audiencia $audiencia
     * @param Parte $parte
     * 
     * @return array
     */
    public function getConvenioPago(Audiencia $audiencia, Parte $parte)
    {
        $monto = 0.0;
        $montoPagado = 0.0;
        $montoTotalPagado = 0.0;
        $montoPendiente = 0.0;
        $montoCubiertoAFecha = 0.0;
        $montoPagadoAFecha = 0.0;
        $montoPenalizaciones = 0.0;

        $pagos = ResolucionPagoDiferido::where('audiencia_id', $audiencia->id)
            ->where('code_estatus', '!=', ResolucionPagoDiferido::CANCELADO)
            ->where('solicitante_id', $parte->id)
            ->orderBy('fecha_pago', 'asc')
            ->get();

        foreach ($pagos as $pago) {

            $monto += (float) $pago->monto;
            
            if($pago->code_estatus === ResolucionPagoDiferido::PAGADO){

                $pagoRealizado = 0;
                $monto_pagado_realizado = (float) $pago->monto_pago_realizado;
                $monto_penalizacion = (float) $pago->penalizacion;

                if($monto_pagado_realizado > 0)
                    $pagoRealizado = $monto_pagado_realizado;

                // Sumar penalización si existe
                if ($monto_penalizacion > 0)
                    $montoPenalizaciones += (float) $pago->penalizacion;

                $montoPagado += $pagoRealizado;
                $montoPagadoAFecha += (float) $pago->monto_pago_realizado;

            }else{
                $montoPendiente += (float) $pago->monto;
            }

             // Calcular el monto que debería estar cubierto a la fecha actual
            if (strtotime($pago->fecha_pago) <= strtotime(now())) {
                $montoCubiertoAFecha += (float) $pago->monto;
            }
        }

        $montoTotalPagado += $montoPagadoAFecha + $montoPenalizaciones;

        return [
            'totales'=> [
                'monto_total' => $monto,
                'monto_total_pagado' => $montoTotalPagado,
                'monto_pagado' => $montoPagado,
                'monto_pendiente' =>  $montoPendiente,
                'monto_cubierto_a_fecha' => $montoCubiertoAFecha,
                'monto_pagado_a_fecha' => $montoPagadoAFecha,
                'monto_penalizaciones' => $montoPenalizaciones,
            ],
            'pagos' => $pagos
        ];
    }

    /**
     * Obtiene el estatus de un pago diferido
     * 
     * @param int $status
     * 
     * @return string
     */
    public static function getStatusTranslate($status)
    {
        switch ($status) {
            case ResolucionPagoDiferido::PENDIENTE:
                return 'Pendiente';
            case ResolucionPagoDiferido::PAGADO:
                return 'Pagado';
            case ResolucionPagoDiferido::NO_PAGADO:
                return 'No pagado';
            case ResolucionPagoDiferido::CANCELADO:
                return 'Cancelado';
            default:
                return '';
        }
    }

    /**
     * Obtiene los pagos diferidos de un retroceso
     * 
     * @param int $retroceso_pago_id
     */
    public function listaPagosDiferidosRetrocesos(ResolucionPagoDiferido $pagoDiferidoOrigen)
    {
        $listaPagos = [];

        $pagoRetroceso = ResolucionPagoDiferido::find($pagoDiferidoOrigen->retroceso_pago_id);

        if (!$pagoRetroceso)
            return $listaPagos;

        $pagoRetroceso->bitacora = Bitacora::where('componente', 'Pagos')
                                ->where('tipo_evento', 'Retroceso')
                                ->where('referencia_id', $pagoDiferidoOrigen->id)
                                ->first();

        if ($pagoRetroceso->retroceso_pago_id) {
            $listaPagos[] = $pagoRetroceso;
            $listaPagos = array_merge($listaPagos, $this->listaPagosDiferidosRetrocesos($pagoRetroceso));
        } else {
            $listaPagos[] = $pagoRetroceso;
        }

        return $listaPagos;
    }
}
