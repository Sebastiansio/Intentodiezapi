<?php

namespace App\Services;

use App\ClasificacionArchivo;
use App\Exceptions\ParametroNoValidoException;
use App\Expediente;
use App\TipoParte;
use App\Traits\Transformer;
use Illuminate\Support\Facades\Storage;

/**
 * Operaciones para la consulta de expedientes por rango de fechas
 * Class ConsultaConciliacionesPorRangoFechas
 */
class ConsultaConciliacionesPorExpediente
{
    use Transformer;

    public function consulta($parametro, $tipo_resolucion, $limit = 15, $page = 1)
    {
        $resolucion_id = 3;
        switch ($tipo_resolucion) {
            case 'conciliacion':
                $resolucion_id = 1;
                break;
            case 'no-conciliacion':
                $resolucion_id = 3;
                break;
        }
        $expediente = Expediente::where('folio', 'ilike', $parametro)->first();
        if (! $expediente) {
            return ['data' => []];
        }
        $audiencia = $expediente->audiencia()->where('resolucion_id', $resolucion_id)->first();
        if (! $audiencia) {
            return ['data' => []];
        }

        $partes = $expediente->solicitud->partes;
        $parte_demandada = $this->partesTransformer($partes, 'citado', true);
        $parte_actora = $this->partesTransformer($partes, 'solicitante', true);
        //        dd($parte_actora);

        $res[] = [
            'numero_expediente_oij' => $audiencia->expediente->folio,
            'fecha_audiencia' => '/Date('.strtotime($audiencia->fecha_audiencia).')/',
            'fecha_conflicto' => '/Date('.strtotime($audiencia->expediente->solicitud->fecha_conflicto).')/',
            'fecha_ratificacion' => '/Date('.strtotime($audiencia->expediente->solicitud->fecha_ratificacion).')/',
            'organo_impartidor_de_justicia' => $audiencia->expediente->solicitud->centro->id,
            'organo_impartidor_de_justicia_nombre' => $audiencia->expediente->solicitud->centro->nombre,
            'actores' => [$parte_actora],
            'demandados' => [$parte_demandada],
        ];

        //TODO: Firma de documentos (PEndiente)
        //TODO: Implementar el catálogo de clasificación de archivo (Pendiente).
        if ($resolucion_id == 3) {
            $clasificacion_archivo = 'Constancia de no conciliación con firma autógrafa';
        } else {
            $clasificacion_archivo = 'Convenio con firma autógrafa';
        }
        $clasificacion = ClasificacionArchivo::where('nombre', $clasificacion_archivo)->first();
        $documento = $audiencia->documentos()->where('clasificacion_archivo_id', $clasificacion->id)->first();
        if ($documento != null) {
            if (Storage::disk('local')->exists($documento->ruta)) {
                $contents = base64_encode(Storage::get($documento->ruta));
                $info = pathinfo($documento->ruta);
                $size = Storage::size($documento->ruta);

                return [
                    'data' => $res,
                    'documento' => [
                        'documento_id' => $documento->id,
                        'nombre' => $info['basename'],
                        'extension' => $info['extension'],
                        'filebase64' => $contents,
                        'longitud' => $size,
                        'firmado' => 0,
                        'pkcs7base64' => '',
                        'fecha_firmado' => '',
                        'clasificacion_archivo' => 1,
                    ],
                ];
            } else {
                $contents = base64_encode(Storage::get('Prueba.pdf'));
                $info = pathinfo('Prueba.pdf');
                $size = Storage::size('Prueba.pdf');

                return [
                    'data' => $res,
                    'documento' => [
                        'documento_id' => 1553,
                        'nombre' => $info['basename'],
                        'extension' => $info['extension'],
                        'filebase64' => $contents,
                        'longitud' => $size,
                        'firmado' => 0,
                        'pkcs7base64' => '',
                        'fecha_firmado' => '',
                        'clasificacion_archivo' => 1,
                    ],
                ];
            }
        } else {
            if ($resolucion_id == 3) {
                $clasificacion_archivo = 'Constancia de no conciliación';
            } else {
                $clasificacion_archivo = 'Convenio';
            }
            $clasificacion = ClasificacionArchivo::where('nombre', $clasificacion_archivo)->first();
            $documento = $audiencia->documentos()->where('clasificacion_archivo_id', $clasificacion->id)->first();
            if ($documento != null) {
                if (Storage::disk('local')->exists($documento->ruta)) {
                    $contents = base64_encode(Storage::get($documento->ruta));
                    $info = pathinfo($documento->ruta);
                    $size = Storage::size($documento->ruta);

                    return [
                        'data' => $res,
                        'documento' => [
                            'documento_id' => $documento->id,
                            'nombre' => $info['basename'],
                            'extension' => $info['extension'],
                            'filebase64' => $contents,
                            'longitud' => $size,
                            'firmado' => 0,
                            'pkcs7base64' => '',
                            'fecha_firmado' => '',
                            'clasificacion_archivo' => 1,
                        ],
                    ];
                } else {
                    $contents = base64_encode(Storage::get('Prueba.pdf'));
                    $info = pathinfo('Prueba.pdf');
                    $size = Storage::size('Prueba.pdf');

                    return [
                        'data' => $res,
                        'documento' => [
                            'documento_id' => 1553,
                            'nombre' => $info['basename'],
                            'extension' => $info['extension'],
                            'filebase64' => $contents,
                            'longitud' => $size,
                            'firmado' => 0,
                            'pkcs7base64' => '',
                            'fecha_firmado' => '',
                            'clasificacion_archivo' => 1,
                        ],
                    ];
                }
            } else {
                $contents = base64_encode(Storage::get('Prueba.pdf'));
                $info = pathinfo('Prueba.pdf');
                $size = Storage::size('Prueba.pdf');

                return [
                    'data' => $res,
                    'documento' => [
                        'documento_id' => 1553,
                        'nombre' => $info['basename'],
                        'extension' => $info['extension'],
                        'filebase64' => $contents,
                        'longitud' => $size,
                        'firmado' => 0,
                        'pkcs7base64' => '',
                        'fecha_firmado' => '',
                        'clasificacion_archivo' => 1,
                    ],
                ];
            }
        }
    }

    /**
     * Transforma los datos de las partes
     */
    public function partesTransformer($datos, $parte, bool $domicilio = false)
    {
        $array = [];
        $parteCat = TipoParte::where('nombre', 'ilike', $parte)->first();
        $personas = $datos->where('tipo_parte_id', $parteCat->id);
        $resultado = [];
        foreach ($personas as $persona) {
            if ($persona->tipoPersona->abreviatura == 'F') {
                $resultado = [
                    'nombre' => $persona->nombre,
                    'primer_apellido' => $persona->primer_apellido,
                    'segundo_apellido' => $persona->segundo_apellido,
                    'rfc' => $persona->rfc,
                    'curp' => $persona->curp,
                    'caracter_persona' => $persona->tipoPersona->nombre,
                    'caracter_persona_id' => $persona->tipo_persona_id,
                    'solicita_traductor' => $persona->solicita_traductor,
                    'lengua_indigena' => $persona->lenguaIndigena->nombre,
                    'lengua_indigena_id' => $persona->tipo_persona_id,
                    'padece_discapacidad' => $persona->padece_discapacidad,
                    'discapacidad' => $persona->tipoDiscapacidad->nombre,
                    'discapacidad_id' => $persona->tipo_discapacidad_id,
                    'publicacion_datos' => $persona->publicacion_datos,
                    'domicilios' => $this->domiciliosTransformer($persona->domicilios),
                    'contactos' => $this->contactoTransformer($persona->contactos),
                    'datos_laborales' => $this->laboralTransformer($persona->dato_laboral),
                ];
            }
            if ($persona->tipoPersona->abreviatura == 'M') {
                $resultado = [
                    'denominacion' => $persona->nombre_comercial,
                    'rfc' => $persona->rfc,
                    'caracter_persona' => $persona->tipoPersona->nombre,
                    'caracter_persona_id' => $persona->tipo_persona_id,
                    'solicita_traductor' => $persona->solicita_traductor,
                    'lengua_indigena' => $persona->lenguaIndigena->nombre,
                    'lengua_indigena_id' => $persona->tipo_persona_id,
                    'padece_discapacidad' => false,
                    'discapacidad' => 'N/A',
                    'discapacidad_id' => null,
                    'publicacion_datos' => $persona->publicacion_datos,
                    'domicilios' => $this->domiciliosTransformer($persona->domicilios),
                    'contactos' => $this->contactoTransformer($persona->contactos),
                    'datos_laborales' => $this->laboralTransformer($persona->dato_laboral),
                ];
            }
            if (! $domicilio) {
                unset($resultado['domicilios']);
            }

        }

        return $resultado;
    }

    /**
     * Valida la estructura de los parametros eviados en el post
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
        if (! isset($paramsJSON->expediente) || ! $paramsJSON->expediente) {
            throw new ParametroNoValidoException('El número de expediente es requerido.', 1040);

            return null;
        }

        //TODO: Validar la estructura del expediente que sea conformante y emitir excepción de lo contrario
        return $paramsJSON;
    }

    public function contactoTransformer($datos)
    {
        $contacto = [];
        foreach ($datos as $contact) {
            $contacto[] = [
                'tipo_contacto' => $contact->tipo_contacto->nombre,
                'tipo_contacto_id' => $contact->tipo_contacto_id,
                'contacto' => $contact->contacto,
            ];
        }

        return $contacto;
    }

    public function laboralTransformer($datos)
    {
        if (count($datos) > 0) {
            $datos = $datos[0];
            $laboral = [
                'ocupacion_id' => $datos->ocupacion_id,
                'ocupacion_nombre' => $datos->ocupacion->nombre,
                'fecha_ingreso' => '/Date('.strtotime($datos->fecha_ingreso).')/',
                'fecha_salida' => '/Date('.strtotime($datos->fecha_salida).')/',
                'nss' => $datos->nss,
            ];

            return $laboral;
        } else {
            return [];
        }
    }
}
