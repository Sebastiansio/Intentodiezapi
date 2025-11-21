<?php

namespace App\Services;

use App\Exceptions\FechaInvalidaException;
use App\Parte;
use App\Solicitud;
use App\TipoParte;
use Carbon\Carbon;

/**
 * Operaciones para la consulta de expedientes por rango de fechas
 * Class ConsultaConciliacionesPorRangoFechas
 */
class ConsultaConciliacionesPorRfc
{
    //TODO: Regresar los datos como en la consulta por rango de fechas, sólo resumen.
    public function consulta($rfc, $tipo_resolucion, $limit = 15, $page = 1)
    {
        $tipo_resolucion_id = 3;
        switch ($tipo_resolucion) {
            case 'conciliacion':
                $tipo_resolucion_id = 1;
                break;
            case 'no-conciliacion':
                $tipo_resolucion_id = 3;
                break;
        }
        $partes = Parte::where('rfc', 'ilike', $rfc)->get();
        // obtenemos la solicitud y el expediente
        $resultado = [];
        foreach ($partes as $parte) {
            // Validamos el tipo parte del CURP buscado
            $parteCat = $parte->tipoParte;
            // Buscamos la solicitud de este registro
            $exp = Solicitud::find($parte->solicitud_id);
            if ($exp->expediente != null) {
                foreach ($exp->expediente->audiencia as $audiencia) {
                    if ($audiencia->resolucion_id == $tipo_resolucion_id) {
                        $audiencias = $exp->expediente->audiencia()->paginate();
                        if (strtoupper($parteCat->nombre) == 'SOLICITANTE') {
                            $parte_actora = $this->partesTransformer($parte, 'solicitante', false);
                            $parte_demandada = $this->partesTransformer($exp->partes, 'citado', true);
                        } elseif (strtoupper($parteCat->nombre) == 'CITADO') {
                            $parte_actora = $this->partesTransformer($exp->partes, 'solicitante', true);
                            $parte_demandada = $this->partesTransformer($parte, 'citado', false);
                        }

                        $resultado[] = [
                            'numero_expediente_oij' => $exp->expediente->folio,
                            'fecha_audiencia' => '/Date('.strtotime($audiencia->fecha_audiencia).')/',
                            'organo_impartidor_de_justicia' => $audiencia->expediente->solicitud->centro->id,
                            'organo_impartidor_de_justicia_nombre' => $audiencia->expediente->solicitud->centro->nombre,
                            'parte_actora' => $parte_actora,
                            'parte_demandada' => $parte_demandada,
                        ];
                    }
                }
            }
        }
        if (! count($resultado)) {
            return [
                'data' => [],
                'total' => 0,
                'per_page' => 15,
                'current_page' => 1,
                'last_page' => 1,
                'has_more_pages' => false,
                'previous_page_url' => null,
                'next_page_url' => null,
                'url' => '',
            ];
        } else {
            return [
                'data' => $resultado,
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
    }

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

    public function partesTransformer($datos, $parte, $busqueda, $domicilio = false)
    {
        if ($busqueda) {
            $parteCat = TipoParte::where('nombre', 'ilike', $parte)->first();
            $persona = $datos->where('tipo_parte_id', $parteCat->id)->first();
        } else {
            $persona = $datos;
        }

        $resultado = [];
        if ($persona->tipoPersona->abreviatura == 'F') {
            $resultado = [
                'nombre' => $persona->nombre,
                'primer_apellido' => $persona->primer_apellido,
                'segundo_apellido' => $persona->segundo_apellido,
                'rfc' => $persona->rfc,
                'curp' => $persona->curp,
                'caracter_persona' => $persona->tipoPersona->nombre,
                'caracter_persona_id' => $persona->tipo_persona_id,
                'domicilios' => $this->domiciliosTransformer($persona->domicilios),
            ];
        }
        if ($persona->tipoPersona->abreviatura == 'M') {
            $resultado = [
                'denominacion' => $persona->nombre_comercial,
                'rfc' => $persona->rfc,
                'caracter_persona' => $persona->tipoPersona->nombre,
                'caracter_persona_id' => $persona->tipo_persona_id,
                'domicilios' => $this->domiciliosTransformer($persona->domicilios),
            ];
        }
        if (! $domicilio) {
            unset($resultado['domicilios']);
        }

        return $resultado;
    }

    public function domiciliosTransformer($datos)
    {
        $domicilios = [];
        foreach ($datos as $domicilio) {
            $domicilios[] = [
                'tipo_vialidad' => $domicilio->tipo_vialidad,
                'vialidad' => $domicilio->vialidad,
                'num_ext' => $domicilio->num_ext,
                'num_int' => $domicilio->num_int,
                'tipo_asentamiento' => $domicilio->tipo_asentamiento,
                'asentamiento' => $domicilio->asentamiento,
                'municipio' => $domicilio->municipio,
                'estado' => $domicilio->estado,
                'cp' => $domicilio->cp,
                'latitud' => $domicilio->latitud,
                'longitud' => $domicilio->longitud,
                'entre_calle1' => $domicilio->entre_calle1,
                'entre_calle2' => $domicilio->entre_calle2,
                'referencias' => $domicilio->referencias,
            ];
        }

        return $domicilios;
    }
}
