<?php

namespace App\Listeners;

use App\Audiencia;
use App\EtapaNotificacion;
use App\Events\RatificacionRealizada;
use App\HistoricoNotificacion;
use App\HistoricoNotificacionPeticion;
use App\Incidencia;
use App\Traits\FechaNotificacion;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class SendNotificacion
{
    use FechaNotificacion;

    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(RatificacionRealizada $event): void
    {
        $start_response = microtime(true);
        try {
            DB::beginTransaction();
            $arreglo = [];
            // Consultamos la audiencia
            $audiencia = Audiencia::find($event->audiencia_id);
            Log::debug('Se recibio esta información:'.json_encode($event));
            if (isset($audiencia->id)) {
                $tipo_notificacion = $event->tipo_notificacion;
                $tipo_notificacion_fecha = self::ObtenerTipoNotificacion($audiencia);
                // Agregamos al arreglo las generalidades de la audiencia
                $arreglo['folio'] = $audiencia->folio.'/'.$audiencia->anio;
                $arreglo['expediente'] = $audiencia->expediente->folio;
                $arreglo['exhorto_num'] = '';
                //Validamos el tipo de notificación
                $fechaIngreso = new \Carbon\Carbon($audiencia->expediente->solicitud->created_at);
                if ($tipo_notificacion == 'citatorio') {
                    $fechaRecepcion = new \Carbon\Carbon($tipo_notificacion_fecha['fecha_recepcion']);
                    $fechaAudiencia = new \Carbon\Carbon($audiencia->fecha_audiencia);
                    $fechaCita = null;
                    if ($audiencia->fecha_cita != null && $audiencia->fecha_cita != '') {
                        $fechaCita = new \Carbon\Carbon($audiencia->fecha_cita);
                    }
                    $fechaLimite = new \Carbon\Carbon($audiencia->fecha_limite_audiencia);
                    $arreglo['fecha_recepcion'] = $fechaRecepcion->format('d/m/Y');
                    $arreglo['fecha_audiencia'] = $fechaAudiencia->format('d/m/Y');
                    $arreglo['fecha_ar'] = $fechaRecepcion->format('d/m/Y');
                    if ($fechaCita != null) {
                        $arreglo['fecha_cita'] = $fechaCita->format('d/m/Y');
                    } else {
                        $arreglo['fecha_cita'] = null;
                    }
                    $arreglo['fecha_limite'] = $fechaLimite->format('d/m/Y');
                } else {
                    $fechaRecepcion = new \Carbon\Carbon($tipo_notificacion_fecha['fecha_recepcion']);
                    $fechaAudiencia = self::ObtenerFechaAudienciaMulta($audiencia->fecha_audiencia, 15);
                    $fechaCita = null;
                    $fechaLimite = new \Carbon\Carbon(self::ObtenerFechaLimiteNotificacionMulta($fechaAudiencia, $audiencia->expediente->solicitud, $audiencia->id));
                    $arreglo['fecha_recepcion'] = $fechaRecepcion->format('d/m/Y');
                    $arreglo['fecha_audiencia'] = $fechaAudiencia->format('d/m/Y');
                    $arreglo['fecha_ar'] = $fechaRecepcion->format('d/m/Y');
                    $arreglo['fecha_limite'] = $fechaLimite->format('d/m/Y');
                    $arreglo['fecha_cita'] = null;
                }
                $arreglo['fecha_ingreso'] = $fechaIngreso->format('d/m/Y');
                $arreglo['nombre_junta'] = $audiencia->expediente->solicitud->centro->nombre;
                $arreglo['junta_id'] = $audiencia->expediente->solicitud->centro_id;
                $arreglo['tipo_notificacion'] = $tipo_notificacion;
                //Buscamos a los actores
                Log::debug('Información de solicitud:'.json_encode($arreglo));
                $actores = self::getSolicitantes($audiencia);
                foreach ($actores as $partes) {
                    $parte = $partes->parte;
                    if ($parte->tipo_persona_id == 1) {
                        $nombre = $parte->nombre.' '.$parte->primer_apellido.' '.$parte->segundo_apellido;
                        if ($parte->genero_id == 1) {
                            $sexo = 'Hombre';
                        } else {
                            $sexo = 'Mujer';
                        }
                    } else {
                        $nombre = $parte->nombre_comercial;
                        $sexo = '';
                    }
                    $domicilio = $parte->domicilios()->orderBy('id', 'desc')->first();
                    $arreglo['Actores'][] = [
                        'actor_id' => $parte->id,
                        'nombre' => $nombre,
                        'sexo' => $sexo,
                        'tipo_persona' => $parte->tipoPersona->nombre,
                        'Direccion' => [
                            'estado' => $domicilio->estado,
                            'estado_id' => $domicilio->estado_id,
                            'delegacion' => $domicilio->municipio,
                            'colonia' => $domicilio->asentamiento,
                            'cp' => $domicilio->cp,
                            'tipo_vialidad' => $domicilio->tipo_vialidad,
                            'calle' => $domicilio->vialidad,
                            'num_ext' => $domicilio->num_ext,
                            'num_int' => $domicilio->num_int,
                            'en_catalogo' => true,
                            'latitud' => $domicilio->latitud == null ? '0' : $domicilio->latitud,
                            'longitud' => $domicilio->longitud == null ? '0' : $domicilio->longitud,
                        ],
                    ];
                }
                //Log::debug('Información de actores:'.json_encode($arreglo["Actores"]));
                //Buscamos a los demandados
                $demandados = self::getSolicitados($audiencia, $tipo_notificacion, $event->parte_id);
                $etapa_notificacion_null = EtapaNotificacion::whereEtapa('No comparecio el citado')->first();
                foreach ($demandados as $partes) {
                    $parte = $partes->parte;
                    if ($parte->tipo_persona_id == 1) {
                        $nombre = $parte->nombre.' '.$parte->primer_apellido.' '.$parte->segundo_apellido;
                    } else {
                        $nombre = $parte->nombre_comercial;
                    }
                    $domicilio = $parte->domicilios()->orderBy('id', 'desc')->first();
                    $arreglo['Demandados'][] = [
                        'demandado_id' => $parte->id,
                        'actuario_id' => 999999,
                        'nombre' => $nombre,
                        'sexo' => '',
                        'tipo_persona' => $parte->tipoPersona->nombre,
                        'Direccion' => [
                            'estado' => $domicilio->estado,
                            'estado_id' => $domicilio->estado_id,
                            'delegacion' => $domicilio->municipio,
                            'colonia' => $domicilio->asentamiento,
                            'cp' => $domicilio->cp,
                            'tipo_vialidad' => $domicilio->tipo_vialidad,
                            'calle' => $domicilio->vialidad,
                            'num_ext' => $domicilio->num_ext,
                            'num_int' => $domicilio->num_int,
                            'en_catalogo' => true,
                            'latitud' => $domicilio->latitud == null ? '0' : $domicilio->latitud,
                            'longitud' => $domicilio->longitud == null ? '0' : $domicilio->longitud,
                        ],
                    ];
                    //                Buscamos si existe un historico de ese tipo de notificación
                    $historico = HistoricoNotificacion::where('audiencia_parte_id', $partes->id)->where('tipo_notificacion', $tipo_notificacion)->first();
                    if ($historico == null) {
                        $historico = HistoricoNotificacion::create([
                            'audiencia_parte_id' => $partes->id,
                            'tipo_notificacion' => $tipo_notificacion,
                        ]);
                    }
                    if ($audiencia->etapa_notificacion_id == null) {
                        $etapa = $etapa_notificacion_null->id;
                    } else {
                        $etapa = $audiencia->etapa_notificacion_id;
                    }
                    $historico_peticion = HistoricoNotificacionPeticion::create([
                        'historico_notificacion_id' => $historico->id,
                        'etapa_notificacion_id' => $etapa,
                        'fecha_peticion_notificacion' => now(),
                    ]);
                    $historico->update(['historico_notificacion_peticion_id' => $historico_peticion->id]);
                }
                $client = new Client;
                Log::info(json_encode($arreglo));
                if (config('app.env') != null) {
                    if (env('NOTIFICACION_DRY_RUN')) {
                        $baseURL = env('APP_URL_NOTIFICACIONES');
                    } else {
                        $baseURL = $audiencia->expediente->solicitud->centro->url_instancia_notificacion;
                    }
                    Log::info('Base URL: '.$baseURL);
                    if ($baseURL != null) {
                        Log::info('Envia a SIGNO');
                        $response = $client->request('POST', $baseURL, [
                            'headers' => ['foo' => 'bar'],
                            'verify' => false,
                            'body' => json_encode($arreglo),
                        ]);
                        Log::info($response->getBody());
                        $end_response = microtime(true);
                        $total_response = $end_response - $start_response;
                        Log::alert('Tiempo de respuesta (SIGNO): '.$total_response.' segundos. ');
                    }
                    //        Cambiamos el estatus de notificación
                    $solicitud = $audiencia->expediente->solicitud;
                    $solicitud->update(['fecha_peticion_notificacion' => now()]);
                } else {
                    throw new Exception('No se encuentra el ambiente');
                }
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $end_response = microtime(true);
            $total_response = $end_response - $start_response;
            Log::alert('Tiempo de respuesta (SIGNO Throwable): '.$total_response.' segundos. ');
            Log::error('En scriptt:'.$e->getFile().' En línea: '.$e->getLine().
                       ' Se emitió el siguiente mensale: '.$e->getMessage().
                       ' Con código: '.$e->getCode().' La traza es: '.$e->getTraceAsString());
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $response = $e->getResponse();
            $respuesta = $response->getBody()->getContents();
            $end_response = microtime(true);
            $total_response = $end_response - $start_response;
            Log::alert('Tiempo de respuesta (SIGNO ClientException): '.$total_response.' segundos. ');
            if ($respuesta == '{"error":"La notificaci\u00f3n ya existe en el sistema ","detalle":"Se ha actualizado la informaci\u00f3n del expediente."}') {
                $solicitud = $audiencia->expediente->solicitud;
                $solicitud->update(['fecha_peticion_notificacion' => now()]);
                DB::commit();
                Log::warning('En scripts:'.$e->getFile().' En línea: '.$e->getLine().
                           ' Se emitió el siguiente mensale: '.$respuesta.
                           ' Con código: '.$e->getCode().' La traza es: '.$e->getTraceAsString());
            } else {
                DB::rollBack();
                Log::error('respuesta En scripts:'.$e->getFile().' En línea: '.$e->getLine().
                           ' Se emitió el siguiente mensale: '.$respuesta.
                           ' Con código: '.$e->getCode().' La traza es: '.$e->getTraceAsString());
            }
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            DB::rollBack();
            $end_response = microtime(true);
            $total_response = $end_response - $start_response;
            Log::alert('Tiempo de respuesta (SIGNO RequestException): '.$total_response.' segundos. ');
            //$response = $e->getResponse();
            //$respuesta = $response->getBody()->getContents();
            //Log::error("Error en respuesta al enviar datos a signo: Respuesta SIGNO status: ". $response->getStatusCode()." => ".$respuesta);
        }

    }

    /**
     * Funcion para obtener las partes involucradas en una audiencia de tipo solicitante
     *
     * @return AudienciaParte $solicitante
     */
    public function getSolicitantes(Audiencia $audiencia)
    {
        $solicitantes = [];
        foreach ($audiencia->audienciaParte as $parte) {
            if ($parte->parte->tipo_parte_id == 1) {
                $solicitantes[] = $parte;
            }
        }

        return $solicitantes;
    }

    /**
     * Funcion para obtener las partes involucradas en una audiencia de tipo solicitado
     *
     * @return AudienciaParte $solicitado
     */
    public function getSolicitados(Audiencia $audiencia, $tipo_notificacion, $audiencia_parte_id = null)
    {
        $solicitados = [];
        $i = 0;
        foreach ($audiencia->audienciaParte as $parte) {
            if ($parte->parte->tipo_parte_id == 2) {
                if ($audiencia_parte_id == null) {
                    if ($tipo_notificacion == 'multa') {
                        $solicitados[] = $parte;
                    } else {
                        if ($parte->finalizado != 'FINALIZADO EXITOSAMENTE' && $parte->finalizado != 'EXITOSO POR INSTRUCTIVO') {
                            $solicitados[] = $parte;
                        }
                    }
                } else {
                    if ($parte->id == $audiencia_parte_id) {
                        $solicitados[] = $parte;
                    }
                }
            }
        }

        return $solicitados;
    }

    /**
     * Funcion para obtener la fecha de audiencia de multa
     *
     * @return Carbon $fecha_audiencia
     */
    public function ObtenerFechaAudienciaMulta(string $fecha_audiencia, $dias)
    {
        $d = new \Carbon\Carbon($fecha_audiencia);
        $diasRecorridos = 1;
        while ($diasRecorridos < $dias) {
            $sig = $d->addDay()->format('Y-m-d');
            if (! Incidencia::hayIncidencia($sig, auth()->user()->centro_id, 'App\Centro')) {
                $d = new \Carbon\Carbon($sig);
                $diasRecorridos++;
            }
        }

        return $d;
    }

    public function ObtenerFechaLimiteNotificacionMulta($fecha_audiencia, $solicitud, $audiencia_id)
    {
        //      obtenemos el domicilio del centro
        $domicilio_centro = auth()->user()->centro->domicilio;
        //      obtenemos el domicilio del citado
        $partes = $solicitud->partes;
        $domicilio_citado = null;
        foreach ($partes as $parte) {
            if ($parte->tipo_parte_id == 2) {
                $domicilio_citado = $parte->domicilios->last();
                break;
            }
        }
        if ($domicilio_citado->latitud == '' || $domicilio_citado->longitud == '') {
            return date('Y-m-d');
        } else {
            return self::obtenerFechaLimiteNotificacion($domicilio_centro, $domicilio_citado, $fecha_audiencia);
        }
    }

    public function ObtenerTipoNotificacion($audiencia)
    {
        $clasificacion_multa = \App\ClasificacionArchivo::where('nombre', 'Acta de multa')->first();
        $clasificacion_citatorio = \App\ClasificacionArchivo::where('nombre', 'Citatorio')->first();
        $docCitatorio = $audiencia->documentos()->where('clasificacion_archivo_id', $clasificacion_citatorio->id)->first();
        if (isset($audiencia->documentos)) {
            $doc = $audiencia->documentos()->where('clasificacion_archivo_id', $clasificacion_multa->id)->first();
            if ($doc == null) {
                if ($docCitatorio != null) {
                    return [
                        'tipo_notificacion' => 'citatorio',
                        'fecha_recepcion' => $docCitatorio->created_at,
                    ];
                }
            } else {
                return [
                    'tipo_notificacion' => 'multa',
                    'fecha_recepcion' => $doc->created_at,
                ];
            }
        }

        return [
            'tipo_notificacion' => 'citatorio',
            'fecha_recepcion' => $audiencia->expediente->solicitud->fecha_ratificacion,
        ];
    }
}
