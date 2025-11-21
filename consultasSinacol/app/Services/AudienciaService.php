<?php

namespace App\Services;

use App\Exceptions\FolioExpedienteExistenteException;
use App\Traits\FechaNotificacion;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * Clase para la confirmación de audiencia
 * Class AudienciaService
 */
class AudienciaService
{
    use FechaNotificacion;

    /**
     * Tipos de contador solicitud
     */
    const TIPO_CONTADOR_SOLICITUD = 1;

    /**
     * Tipo de contador expediente
     */
    const TIPO_CONTADOR_EXPEDIENTE = 2;

    /**
     * Tipos de contador audiencia
     */
    const TIPO_CONTADOR_AUDIENCIA = 3;

    /**
     * Centro default para contador en solicitud
     */
    const CENTRO_DEFAULT_CONTADOR_ID = 1;

    /**
     * Función para obtener los folios de audiencia y expediente
     */
    public static function obtenerFolios($solicitud, $contadorService, $folioService)
    {
        try {
            $anio = date('Y');
            //Obtenemos el consecutivo para audiencia
            $consecutivo_audiencia = $contadorService->getContador($anio, self::TIPO_CONTADOR_AUDIENCIA, auth()->user()->centro_id);

            //Obtenemos el folio y su consecutivo del expediente
            $parametrosFolioExpediente = $solicitud->toArray();
            $parametrosFolioExpediente['anio'] = date('Y');
            $parametrosFolioExpediente['tipo_contador_id'] = self::TIPO_CONTADOR_SOLICITUD;
            [$consecutivo, $folio] = $folioService->getFolio((object) $parametrosFolioExpediente);

            return ['folios' => true, 'expediente' => $folio, 'audiencia' => $consecutivo_audiencia, 'consecutivo_expediente' => $consecutivo];
        } catch (FolioExpedienteExistenteException $e) {
            // Si hay folio de expediente duplicado entonces aumentamos en 1 el contador
            $contexto = $e->getContext();
            Log::error($e->getMessage().' '.$contexto->folio);
            $contadorService->getContador($contexto->anio, self::TIPO_CONTADOR_SOLICITUD, $contexto->solicitud->centro_id);

            return ['folios' => false];
        } catch (Exception $e) {
            Log::error('Al obtener los folios');
            Log::error('En script:'.$e->getFile().' En línea: '.$e->getLine().
                    ' Se emitió el siguiente mensale: '.$e->getMessage().
                    ' Con código: '.$e->getCode().' La traza es: '.$e->getTraceAsString());

            return ['folios' => false];
        }
    }
}
