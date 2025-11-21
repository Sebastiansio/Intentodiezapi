<?php

namespace App\Services;

use App\Exceptions\FechaInvalidaException;
use App\Exceptions\ParametroNoValidoException;
use App\Parte;
use App\Solicitud;
use App\TipoParte;
use Carbon\Carbon;

/**
 * Operaciones para la consulta de expedientes por rango de fechas
 * Class ConsultaConciliacionesPorRangoFechas
 */
class ConsultaConciliacionesPorNombre
{
    //TODO: Regresar los datos como en la consulta por rango de fechas, sólo resumen.
    public function consulta($nombre_denominacion, $primer_apellido, $segundo_apellido, $tipo_persona, $tipo_parte, $tipo_resolucion, $limit = 15, $page = 1)
    {
        $partes = [];
        $tipo_resolucion_id = 3;
        switch ($tipo_resolucion) {
            case 'conciliacion':
                $tipo_resolucion_id = 1;
                break;
            case 'no-conciliacion':
                $tipo_resolucion_id = 3;
                break;
        }
        if ($tipo_persona == 1) {
            $partes = Parte::where('nombre', 'ilike', $nombre_denominacion)
                ->where('primer_apellido', 'ilike', $primer_apellido)
                ->where('segundo_apellido', 'ilike', $segundo_apellido)
                ->where('tipo_parte_id', $tipo_parte)->get();
        }
        if ($tipo_persona == 2) {
            $partes = Parte::where('nombre_comercial', 'ilike', mb_strtoupper($nombre_denominacion))
                ->where('tipo_parte_id', $tipo_parte)->get();
        }
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
        if (! isset($paramsJSON->caracter_persona) || ! $paramsJSON->caracter_persona) {
            throw new ParametroNoValidoException('El caracter de la persona es requerido.', 1020);

            return null;
        } elseif (isset($paramsJSON->caracter_persona) && ! in_array(mb_strtolower($paramsJSON->caracter_persona), ['física', 'fisica', 'moral', 'f', 'm'])) {
            throw new ParametroNoValidoException('El caracter de la persona no es una opción reconocida.', 1020);

            return null;
        }

        //Valida para persona física
        if (isset($paramsJSON->caracter_persona) && in_array(mb_strtolower($paramsJSON->caracter_persona), ['física', 'fisica', 'f'])) {
            $paramsJSON->caracter_persona = 'FISICA';
            if (! isset($paramsJSON->nombre) || ! trim($paramsJSON->nombre)) {
                throw new ParametroNoValidoException('El nombre de la parte es requerido.', 1020);

                return null;
            }
            if (! isset($paramsJSON->primer_apellido) || ! trim($paramsJSON->primer_apellido)) {
                throw new ParametroNoValidoException('El primer apellido de la parte es requerido.', 1021);

                return null;
            }
            if (! isset($paramsJSON->segundo_apellido)) {
                throw new ParametroNoValidoException('El parámetro del segundo apellido es requerido aunque el dato sea vacío .', 1022);

                return null;
            }
        }

        //Valida para persona moral
        if (isset($paramsJSON->caracter_persona) && in_array(mb_strtolower($paramsJSON->caracter_persona), ['moral', 'm'])) {
            $paramsJSON->caracter_persona = 'MORAL';
            $paramsJSON->primer_apellido = '';
            $paramsJSON->segundo_apellido = '';
            $paramsJSON->nombre = $paramsJSON->denominacion;
            if (! isset($paramsJSON->denominacion) || ! trim($paramsJSON->nombre)) {
                throw new ParametroNoValidoException('La denominación o razón social de la persona moral es requerido.', 1020);

                return null;
            }
        }

        return $paramsJSON;
    }
}
