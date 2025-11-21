<?php

namespace App\Services;

use App\Audiencia;
use App\Compareciente;
use App\Documento;
use App\Domicilio;
use App\Parte;
use App\Repositories\AudienciaRepository;
use App\Solicitud;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ConsultaService
{
    const TIPO_RESOLUCION_ID_NO_CONCILIACION = 3;

    const TIPO_RESOLUCION_ID_CONVENIO = 1;

    const TIPO_PARTE_ID_SOLICITANTE = 1;

    const TIPO_PARTE_ID_CITADO = 2;

    const TIPO_PERSONA_ID_FISICA = 1;

    const CLASIFICACION_ARCHIVO_ID_CONSTANCIA_NO_CONCILIACION = 17;

    const CLASIFICACION_ARCHIVO_ID_RAZON_DE_NOTIFICACION_CITATORIO = 22;

    const CLASIFICACION_ARCHIVO_ID_RAZON_DE_NOTIFICACION_MULTA = 23;

    const CLASIFICACION_ARCHIVO_ID_NOTIFICACION_POR_COMPARECENCIA = 56;

    const CLASIFICACION_ARCHIVO_ID_CONVENIO = 16;

    const CLASIFICACION_ARCHIVO_ID_CONVENIO_REINSTALACION = 43;

    const CLASIFICACION_ARCHIVO_ID_CONVENIO_PATRONAL = 52;

    const CLASIFICACION_ARCHIVO_ID_CONVENIO_REINSTALACION_PATRONAL = 54;

    const CLASIFICACION_ARCHIVO_ID_ACTA_CUMPLIMIENTO_CONVENIO = 45;

    const CLASIFICACION_ARCHIVO_ID_ACTA_NO_COMPARECENCIA_PAGO = 19;

    const CLASIFICACION_ARCHIVO_ID_CONSTANCIA_PAGO_PARCIAL = 49;

    const CLASIFICACION_ARCHIVO_ID_COMPROBANTE_PAGO = 12;

    const CONVENIOS = [
        self::CLASIFICACION_ARCHIVO_ID_CONVENIO,
        self::CLASIFICACION_ARCHIVO_ID_CONVENIO_REINSTALACION,
        self::CLASIFICACION_ARCHIVO_ID_CONVENIO_PATRONAL,
        self::CLASIFICACION_ARCHIVO_ID_CONVENIO_REINSTALACION_PATRONAL,
    ];

    const RAZONES_NOTIFICACION = [
        self::CLASIFICACION_ARCHIVO_ID_RAZON_DE_NOTIFICACION_CITATORIO,
        self::CLASIFICACION_ARCHIVO_ID_RAZON_DE_NOTIFICACION_MULTA,
        self::CLASIFICACION_ARCHIVO_ID_NOTIFICACION_POR_COMPARECENCIA,
    ];

    const CUMPLIMIENTOS_INCUMPLIMIENTOS = [
        self::CLASIFICACION_ARCHIVO_ID_ACTA_CUMPLIMIENTO_CONVENIO,
        self::CLASIFICACION_ARCHIVO_ID_ACTA_NO_COMPARECENCIA_PAGO,
        self::CLASIFICACION_ARCHIVO_ID_CONSTANCIA_PAGO_PARCIAL,
        self::CLASIFICACION_ARCHIVO_ID_COMPROBANTE_PAGO,
    ];

    /**
     * @var AudienciaRepository
     */
    protected $audienciaRepository;

    /**
     * ConstanciaNoConciliacionController constructor.
     */
    public function __construct(AudienciaRepository $audienciaRepository)
    {
        $this->audienciaRepository = $audienciaRepository;
    }

    /**
     * Busca y devuelve los datos de una audiencia en la que no se llegó a conciliación, dado su NIU, CURP y/o RFC del
     * solicitante.
     *
     * @param  string  $expediente  El Número de Identificación Único (NIU) del expediente.
     * @param  string|null  $curp  La Clave Única de Registro de Población (CURP) de la persona solicitante. Puede ser null.
     * @param  string|null  $rfc  El Registro Federal de Contribuyentes (RFC) de la persona solicitante. Puede ser null.
     * @return mixed Los datos de la audiencia en la que no se llegó a conciliación, si existe.
     */
    public function noConciliacion(string $expediente, ?string $curp, ?string $rfc)
    {
        // Consultar datos de la audiencia donde se emitió la constancia de no conciliación (CNC) para el expediente y
        // CURP del solicitante dados
        return $this->audienciaPorExpediente($expediente, $curp, $rfc, self::TIPO_RESOLUCION_ID_NO_CONCILIACION);
    }

    public function convenio($expediente, $curp, $rfc)
    {
        return $this->audienciaPorExpediente($expediente, $curp, $rfc, self::TIPO_RESOLUCION_ID_CONVENIO);
    }

    public function razonNotificacion($expediente, $curp, $rfc)
    {
        return $this->audienciaPorExpediente($expediente, $curp, $rfc, null);
    }

    public function expediente($expediente, $curp, $rfc)
    {
        return $this->audienciaPorExpediente($expediente, $curp, $rfc, null);
    }

    /**
     * Transforma los datos del modelo Audiencia en un arreglo
     *
     * @return array Devuelve un arreglo con la siguiente información
     *               - expediente: El número de folio del expediente de la audiencia (NIU).
     *               - fecha_conflicto: La fecha en que se produjo el conflicto que dio lugar a la solicitud de conciliación prejudicial.
     *               - fecha_registro_solicitud: La fecha en que se registró la solicitud de conciliación prejudicial.
     *               - fecha_ratificacion: La fecha en que se ratificó la solicitud de conciliación prejudicial.
     *               - fecha_audiencia: La fecha en que se llevó a cabo la audiencia.
     *               - conciliador_responsable: El nombre completo del conciliador responsable de la audiencia.
     *               - solicitante: Un arreglo con la información del solicitante de la conciliación prejudicial.
     *               - citados: Un arreglo con la información de los citados.
     *               - documentos: Un arreglo con la información de las constancias de no conciliación.
     */
    public function audienciaTransformer(Audiencia $audiencia, $curp, $rfc, $clasificacion_documentos)
    {

        $solicitante = $audiencia->expediente->solicitud->partes()
            ->when($curp, function ($q) use ($curp) {
                $q->whereCurp($curp);
            })
            ->when($rfc, function ($q) use ($rfc) {
                $q->whereRfc($rfc);
            })
            ->whereNull('deleted_at')
            ->first();

        $citados = [];
        $audiencia->audienciaParte()->where('audiencia_id', $audiencia->id)
            ->whereHas('parte', function ($q) {
                $q->where('tipo_parte_id', self::TIPO_PARTE_ID_CITADO)->whereNull('deleted_at');
            })->get()->each(function ($audiencia_parte) use (&$citados) {

                $citado = $audiencia_parte->parte;
                $citado->notificacion = $audiencia_parte->finalizado;
                $citados[] = $this->citadoTansformer($citado, $audiencia_parte->audiencia_id);
            });
        $estatus_solicitud = mb_strtoupper(data_get($audiencia, 'expediente.solicitud.estatusSolicitud.nombre', ''));
        if ($estatus_solicitud === 'RATIFICADA') {
            $estatus_solicitud = 'EN PROCESO';
        }
        $estatus_audiencia = 'PENDIENTE';
        if ($audiencia->finalizada) {
            $estatus_audiencia = 'FINALIZADA';
        }

        return [
            'expediente' => data_get($audiencia, 'expediente.folio', ''),
            'fecha_conflicto' => data_get($audiencia, 'expediente.solicitud.fecha_conflicto', ''),
            'fecha_registro_solicitud' => data_get($audiencia, 'expediente.solicitud.fecha_recepcion', ''),
            'fecha_ratificacion' => data_get($audiencia, 'expediente.solicitud.fecha_ratificacion', ''),
            'fecha_audiencia' => data_get($audiencia, 'fecha_audiencia', ''),
            'estatus_solicitud' => $estatus_solicitud,
            'estatus_audiencia' => $estatus_audiencia,
            'conciliador_responsable' => mb_strtoupper(data_get($audiencia, 'conciliador.persona.fullName', '')),
            'solicitante' => mb_strtoupper($this->nombreParteTransformer($solicitante)),
            'citados' => $citados,
            'documentos' => $this->documentoTansformer($audiencia, $clasificacion_documentos),
        ];
    }

    /**
     * Transforma los datos de la Parte citada a un arreglo con los siguientes campos:
     * - nombre: el nombre de la parte citada, ya sea persona física o moral.
     * - domicilio: un arreglo con los datos del domicilio de la parte citada, en el siguiente formato:
     *          'calle' => string,
     *          'numero_exterior' => string,
     *          'numero_interior' => string|null,
     *          'colonia' => string,
     *          'municipio' => string,
     *          'estado' => string,
     *          'pais' => string,
     *          'codigo_postal' => string,
     * - notificacion: una cadena de texto con el resultado de la notificacion
     * - comparecio: una cadena de texto indicando si la parte citada compareció o no a la audiencia donde se determina la NC
     *        con los siguientes valores:
     *      - 'SI' si compareció.
     *      - 'NO' si no compareció.
     *
     * @param  Parte  $parte  El modelo de la Parte citada.
     * @param  int  $audiencia_id  El ID de la audiencia
     * @return array Un arreglo con los datos de la Parte citada transformados.
     */
    public function citadoTansformer(Parte $parte, int $audiencia_id)
    {

        $nombre = $this->nombreParteTransformer($parte);

        $domicilio = $this->domiciliosTransformer($parte->domicilios()->first());

        $comparece = $this->comparecio($audiencia_id, $parte->id);

        return [
            'nombre' => $nombre,
            'domicilio' => $domicilio,
            'notificacion' => $parte->notificacion,
            'comparecio' => $comparece ? 'SI' : 'NO',
        ];

    }

    /**
     * Obtiene el nombre completo de un solicitante o citado según su tipo de persona, física o moral.
     *
     * @param  Parte  $parte  El objeto Parte que contiene los datos del nombre
     * @return string El nombre completo de la parte parte
     */
    private function nombreParteTransformer(Parte $parte)
    {
        return ($parte->tipo_persona_id == self::TIPO_PERSONA_ID_FISICA)
            ? trim($parte->nombre.' '.$parte->primer_apellido.' '.($parte->segundo_apellido ?? ''))
            : trim($parte->nombre_comercial);
    }

    /**
     * Transforma los datos del modelo Audiencia a un arreglo de metadatos de las constancias de no conciliación emitidas
     * en dicha audiencia por cada citado.
     *
     * @param  Audiencia  $audiencia  El modelo de Audiencia a transformar
     * @param  string  $clasificacion  La clasificación del archivo
     * @return array Un arreglo de datos de cada constancia de no conciliación
     *               - documento_id: El UUID del documento
     *               - nombre: El nombre del archivo
     *               - extension: La extensión del archivo (si está disponible)
     *               - filebase64: La Constancia de no conciliación codificada en Base64
     *               - longitud: La longitud del archivo en KBytes
     *               - firmado: Booleano que indica si el archivo ha sido firmado
     *               - pkcs7base64: El archivo de firma PKCS7 codificado en Base64 (si está disponible)
     *               - fecha_firmado: La fecha de firma del documento (si está disponible)
     *               - clasificacion_archivo: La clasificación del archivo, en este caso CONSTANCIA DE NO CONCILIACIÓN
     *               - tipo_documento: El tipo de documento
     */
    public function documentoTansformer(Audiencia $audiencia, string $clasificacion) {
        $clasificacion_archivo_id = [];
        $documentos = [];

        switch ($clasificacion) {
            case 'convenio':
                $clasificacion_archivo_id = self::CONVENIOS;
                break;
            case 'razonNotificacion':
                $clasificacion_archivo_id = self::RAZONES_NOTIFICACION;
                break;
            case 'noConciliacion':
                $clasificacion_archivo_id = [self::CLASIFICACION_ARCHIVO_ID_CONSTANCIA_NO_CONCILIACION];
                break;
            default:
                $clasificacion_archivo_id = array_merge(
                    self::CONVENIOS,
                    self::RAZONES_NOTIFICACION,
                    self::CUMPLIMIENTOS_INCUMPLIMIENTOS,
                    [self::CLASIFICACION_ARCHIVO_ID_CONSTANCIA_NO_CONCILIACION]
                );
                break;
        }

        $expediente = $audiencia->expediente;
        $documentosCollection = collect();

        foreach ($expediente->audiencia as $audiencia) {
            // Documentos de la Audiencia
            $documentosCollection = $documentosCollection->merge($audiencia->documentos);
            foreach ($audiencia->audienciaParte as $audienciaParte) {
                // Documentos de la AudienciaParte
                $documentosCollection = $documentosCollection->merge($audienciaParte->documentos);
            }
        }

        $documentosCollection = $documentosCollection->filter(function ($documento) use ($clasificacion_archivo_id){
            return in_array($documento->clasificacion_archivo_id, $clasificacion_archivo_id) && !$documento->deleted_at;
        })->unique();

        foreach ($documentosCollection as $documento) {

            $extension = pathinfo($documento->nombre);
            $documentoBase64 = null;
            $pkcs7Base64 = null;

            if ($clasificacion !== 'expediente') {

                try {
                    $documentoBase64 = $this->codificarArchivoB64($documento->uri);
                } catch (\Exception $e) {
                    Log::error("[WS:] Error al obtener archivo correspondiente a la audiencia: {$documento->documentable_id}: " . $e->getMessage());
                }
                if ($documento->firmado) {
                    try {
                        $pkcs7Base64 = $this->codificarArchivoB64($documento->pkcs7base64);
                    } catch (\Exception $e) {
                        Log::error("[WS:] Error al obtener archivo pcks7base64 {$documento->pkcs7base64} correspondiente a la audiencia: {$documento->documentable_id}: " . $e->getMessage());
                    }
                }
            }

            $documentos[] = [
                'documento_id'          => $documento->uuid,
                'nombre'                => $documento->nombre,
                'extension'             => $extension['extension'] ?? null,
                'filebase64'            => $documentoBase64,
                'longitud'              => $documento->longitud,
                'firmado'               => $documento->firmado,
                'pkcs7base64'           => $pkcs7Base64,
                'fecha_firmado'         => $documento->fecha_firmado,
                'clasificacion_archivo' => $documento->clasificacion_archivo_id ?? null,
                'tipo_documento'        => data_get($documento,'clasificacionArchivo.nombre'),
                'fecha_documento'       => $documento->created_at ?? null,
                'uri'                   => $documento->uri ?? null,
            ];
        }

        return $documentos;
    }

    /**
     * Transforma los datos del modelo Domicilio a una cadena con la dirección completa del domicilio.
     *
     * @param  Domicilio  $domicilio  El objeto Domicilio que se va a transformar.
     * @return string La dirección completa en el formato "[tipo vialidad] [vialidad] [num ext], [num int], [colonia], CP [cp], [MUNICIPIO|ALCALDÍA] [municipio], [ESTADO] [estado]".
     */
    public function domiciliosTransformer(Domicilio $domicilio)
    {

        $dom = [];
        $segmento_calle = [];
        if ($domicilio->tipo_vialidad) {
            $segmento_calle[] = $domicilio->tipo_vialidad;
        }
        $segmento_calle[] = $domicilio->tipo_vialidad.' '.$domicilio->vialidad;
        $segmento_calle[] = $domicilio->num_ext;

        $dom[] = preg_replace('/\b(\w+)\b\s+\b\1\b/', '$1', implode(' ', $segmento_calle));

        if ($domicilio->num_int) {
            $dom[] = $domicilio->num_int;
        }

        if ($domicilio->asentamiento) {
            $dom[] = $domicilio->asentamiento;
        }
        if ($domicilio->cp && $domicilio->cp != '00000') {
            $dom[] = 'CP '.$domicilio->cp;
        }
        if ($domicilio->estado_id == '09') {
            if ($domicilio->municipio) {
                $dom[] = 'ALCALDÍA '.$domicilio->municipio;
            }
        } else {
            if ($domicilio->municipio) {
                $dom[] = 'MUNICIPIO '.$domicilio->municipio;
            }
        }

        // Cuando se trata de ciudad de méxico no se agrega la palabra "ESTADO"
        $cdmx = [
            'CIUDAD DE MÉXICO', 'CDMX', 'CIUDAD DE MEXICO', 'CD MEXICO', 'CD MÉXICO',
        ];

        if ($domicilio->estado && ($domicilio->estado_id != '09' || ! in_array(mb_strtoupper($domicilio->estado), $cdmx))) {
            $dom[] = 'ESTADO '.$domicilio->estado;
        } else {
            $dom[] = $domicilio->estado;
        }

        return mb_strtoupper(implode(', ', $dom));
    }

    /**
     * Codifica un archivo en base64 si existe en la ruta especificada.
     *
     * @param  string  $ruta_archivo  La ruta del archivo a codificar en base64.
     * @return string El archivo codificado en base64.
     *
     * @throws \Exception Si el archivo no existe en la ruta especificada.
     */
    public function codificarArchivoB64(string $ruta_archivo)
    {

        // Verificamos que el archivo exista
        if (! Storage::exists($ruta_archivo)) {
            throw new \Exception("El archivo {$ruta_archivo} no existe en la ruta especificada.");
        }

        // Leemos y convertimos el archivo a base64
        $fileContents = Storage::get($ruta_archivo);

        return base64_encode($fileContents);
    }

    /**
     * Indica si compareció la parte a la audiencia dada
     */
    public function comparecio($audiencia_id, $parte_id)
    {

        // Para saber si compareció una persona moral, necesitamos saber si su representante legal compareció.

        $parte = Parte::find($parte_id);

        if (! $parte) {
            return false;
        }

        // Si se encuentra la parte en la tabla de comparecencias regresamos true
        if ((bool) Compareciente::where('audiencia_id', $audiencia_id)->where('parte_id', $parte_id)->exists()) {
            return true;
        }

        // Si no se encontró la parte en la tabla comparecencias, entonces buscamos si la parte comparece mediante representante
        return $this->compareceRepresentado($audiencia_id, $parte);
    }

    /**
     * Si la parte comparece mediante representante, regresa true, false de no encontrar parte representante
     */
    public function compareceRepresentado($audiencia_id, $parte)
    {

        return (bool) Compareciente::where('audiencia_id', $audiencia_id)
            ->whereHas('parte', function ($q) use ($parte, $audiencia_id) {
                $q->where('parte_representada_id', $parte->id)
                    ->whereHas('audienciaParte', function ($w) use ($audiencia_id) {
                        $w->where('audiencia_id', $audiencia_id);
                    });
            })
            ->where('presentado', true)
            ->exists();
    }

    /**
     * Bandera de comparecencia tomada de Parte
     *
     * ESTE CAMPO INDICA COMPARECENCIA DE LA PARTE EN GENERAL. NO CAMBIA CON RESPECTO A UNA AUDIENCIA ESPECÍFICA
     * COMO ES EL CASO QUE SE REQUIERE SABER, SI EN LA AUDIENCIA DONDE SE LLEGA A UNA NO CONCILIACIÓN, LA PARTE ESTUVO
     * PRESENTE.
     *
     * ESTE CAMPO SIEMPRE VA A SER TRUE SI COMPARECIÓ POR LO MENOS A UNA AUDIENCIA.
     */
    public function comparecioParte($parte_id): ?bool
    {
        return Parte::find($parte_id)->comparece ?? null;
    }

    /**
     * @return mixed
     */
    public function audienciaPorExpediente(string $expediente, ?string $curp, ?string $rfc, ?int $tipo_resolucion)
    {
        $query = $this->audienciaRepository->audiencias()->query();

        if ($tipo_resolucion) {
            $query->where('resolucion_id', $tipo_resolucion);
        }

        return $query
            ->select('audiencias.*')
            ->with('expediente.solicitud.partes', 'audienciaParte')
            ->whereHas('expediente', function ($query) use ($expediente) {
                $query->where('folio', $expediente)
                    ->whereNull('deleted_at');
            })
            ->whereHas('expediente.solicitud.partes', function ($query) use ($curp, $rfc) {
                $query->where('tipo_parte_id', self::TIPO_PARTE_ID_SOLICITANTE)
                    ->when($curp, function ($query) use ($curp) {
                        $query->where('curp', $curp);
                    })
                    ->when($rfc, function ($query) use ($rfc) {
                        $query->where('rfc', $rfc);
                    })
                    ->whereNull('deleted_at');
            })
            ->orderBy('audiencias.id', 'desc')
            ->whereNull('audiencias.deleted_at')
            ->first();
    }

    public function documento($uuid)
    {
        $documento = Documento::where('uuid', $uuid)->first();
        if (! $documento) {
            return false;
        }
        $extension = pathinfo($documento->nombre);

        $documentoBase64 = null;
        try {
            $documentoBase64 = $this->codificarArchivoB64($documento->uri);
        } catch (\Exception $e) {
            Log::error("[WS:] Error al obtener archivo correspondiente a la audiencia: {$documento->documentable_id}: ".$e->getMessage());
        }
        $pkcs7Base64 = null;
        if ($documento->firmado) {
            try {
                $pkcs7Base64 = $this->codificarArchivoB64($documento->pkcs7base64);
            } catch (\Exception $e) {
                Log::error("[WS:] Error al obtener archivo pcks7base64 {$documento->pkcs7base64} correspondiente a la audiencia: {$documento->documentable_id}: ".$e->getMessage());
            }
        }

        return [
            'documento_id' => $documento->uuid,
            'nombre' => $documento->nombre,
            'extension' => $extension['extension'] ?? null,
            'filebase64' => $documentoBase64,
            'longitud' => $documento->longitud,
            'firmado' => $documento->firmado,
            'pkcs7base64' => $pkcs7Base64,
            'fecha_firmado' => $documento->fecha_firmado,
            'clasificacion_archivo' => $documento->clasificacion_archivo_id ?? null,
            'tipo_documento' => data_get($documento, 'clasificacionArchivo.nombre'),
            'fecha_documento' => $documento->created_at ?? null,
            'uri' => $documento->uri ?? null,
        ];

    }

    /**
     * Devuelve un documento específico dado su UUID en el formato solicitado
     *
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\Response|string
     */
    public function getDocumentoPorUuid($uuid, $formato = null)
    {
        $documento = Documento::where('uuid', $uuid)->firstOrFail();

        if ($documento && strtolower($formato) === 'base64') {
            $documentoBase64 = null;
            try {
                $documentoBase64 = $this->codificarArchivoB64($documento->uri);
            } catch (\Exception $e) {
                Log::error("[WS:] Error al obtener archivo correspondiente a la audiencia: {$documento->documentable_id}: ".$e->getMessage());

                return response()->json(['error' => 'Documento no encontrado'], 404);
            }

            return $documentoBase64;
        }

        // Verificar si el archivo existe
        if (Storage::exists($documento->uri)) {
            try {

                // Leemos el documento
                $contenido = Storage::get($documento->uri);

                // Enviamos las cabeceras para el archivo PDF
                return response($contenido, 200)
                    ->header('Content-Type', 'application/pdf')
                    ->header('Content-Disposition', 'inline; filename="'.basename($documento->uri).'"')
                    ->header('Content-Length', strlen($contenido));
            } catch (\Exception $e) {
                Log::error("[WS:] Error al leer el archivo correspondiente a la audiencia: {$documento->documentable_id}: ".$e->getMessage());

                return response()->json(['error' => 'Error al leer el archivo. No se encuentra en el sistema de archivos pero sí se encuentra en registros de expediente.'], 500);
            }
        }

        return response()->json(['error' => 'No se encontró el documento solicitado'], 404);
    }
}
