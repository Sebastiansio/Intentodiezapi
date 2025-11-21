<?php

namespace App\Services;

use App\Audiencia;
use App\Exceptions\FechaInvalidaException;
use App\Exceptions\ParametroNoValidoException;
use App\Traits\Transformer;
use Carbon\Carbon;

/**
 * Operaciones para la consulta de expedientes por rango de fechas
 * Class ConsultaConciliacionesPorRangoFechas
 */
class ConsultaConciliacionesPorRangoFechas
{
    use Transformer;

    public function consulta($fecha_inicio, $fecha_fin, $tipo_resolucion, $limit = 15, $page = 1)
    {

        $query = Audiencia::whereBetween('fecha_audiencia', [$fecha_inicio, $fecha_fin]);
        //ToDo: Sacar los id del catalogo de resoluciones dado el nombre y no un "número mágico".
        switch ($tipo_resolucion) {
            case 'conciliacion':
                $query->where('resolucion_id', 1);
                break;
            case 'no-conciliacion':
                $query->where('resolucion_id', 3);
                break;
        }

        $audiencias = $query->paginate();
        $res = [];
        foreach ($audiencias as $audiencia) {
            $parte_actora = $this->partesTransformer($audiencia->expediente->solicitud->partes, 'solicitante');
            $parte_demandada = $this->partesTransformer($audiencia->expediente->solicitud->partes, 'citado');

            $res[] = [
                'numero_expediente_oij' => $audiencia->expediente->folio,
                'resolucion_id' => $audiencia->resolucion_id,
                'fecha_audiencia' => '/Date('.strtotime($audiencia->fecha_audiencia).')/',
                'organo_impartidor_de_justicia' => $audiencia->expediente->solicitud->centro->id,
                'organo_impartidor_de_justicia_nombre' => $audiencia->expediente->solicitud->centro->nombre,
                'parte_actora' => $parte_actora,
                'parte_demandada' => $parte_demandada,
            ];
        }

        return [
            'data' => $res,
            'total' => $audiencias->total(),
            'per_page' => $audiencias->perPage(),
            'current_page' => $audiencias->currentPage(),
            'last_page' => $audiencias->lastPage(),
            'has_more_pages' => $audiencias->hasMorePages(),
            'previous_page_url' => $audiencias->previousPageUrl(),
            'next_page_url' => $audiencias->nextPageUrl(),
            'url' => $audiencias->url($audiencias->currentPage()),
        ];
    }

    /**
     * Valida fechas
     *
     *
     * @throws FechaInvalidaException
     */
    public function validaFechas($fecha)
    {
        //Se espera que la fecha venga en epoch milisegundos con timezone
        $match = [];
        if (preg_match("/(\d+)(\D{1})(\d+)/", $fecha, $match)) {

            try {
                if (strlen($match[1]) == 13) {
                    return Carbon::createFromTimestampMs($match[1], $match[2].$match[3]);
                } elseif (strlen($match[1]) == 10) {
                    return Carbon::createFromTimestamp($match[1], $match[2].$match[3]);
                } else {
                    throw new FechaInvalidaException("La fecha $fecha no es válida");
                }
            } catch (\Exception $e) {
                throw new FechaInvalidaException("La fecha $fecha no es válida");
            }

        } else {
            throw new FechaInvalidaException("La fecha $fecha no es válida");
        }
    }

    /**
     * Valida la estructura de los parametros enviados
     *
     * @return mixed|null
     *
     * @throws ParametroNoValidoException
     */
    public function validaEstructuraParametros($params)
    {
        $paramsJSON = json_decode($params);
        if ($paramsJSON === null) {
            throw new ParametroNoValidoException('Los datos enviados no pueden interpretarse como una estructura JSON válida, favor de revisar.', 1000);

            return null;
        }
        if (! isset($paramsJSON->fechaInicio)) {
            throw new ParametroNoValidoException('La fecha de inicio a consultar es requierida.', 1010);

            return null;
        }
        if (! isset($paramsJSON->fechaFin)) {
            throw new ParametroNoValidoException('La fecha final a consultar es requierida.', 1011);

            return null;
        }

        return $paramsJSON;
    }
}
