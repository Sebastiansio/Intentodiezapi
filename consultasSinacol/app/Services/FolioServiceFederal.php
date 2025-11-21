<?php

namespace App\Services;

use App\Centro;
use App\Exceptions\CentroNoValidoException;
use App\Exceptions\TipoSolicitudNoValidaException;

class FolioServiceFederal implements FolioService
{
    /**
     * Instancia del servicio contador
     *
     * @var ContadorService
     */
    protected $contadorService;

    /**
     * CI: Conciliación Individual
     * CC: Conciliación colectiva
     *
     * @var array
     */
    protected $tipoSolicitudes = [
        1 => 'CI',
        2 => 'CI',
        3 => 'CI',
        4 => 'CC',
    ];

    /**
     * FolioServiceFederal constructor.
     */
    public function __construct(ContadorService $contadorService)
    {
        $this->contadorService = $contadorService;
    }

    /**
     * {@inheritDoc}
     *
     * Esta implementación formatea [INSTANCIA]/[TIPO SOLICITUD]/[AÑO]/[CONSECUTIVO]
     *
     * @throws CentroNoValidoException
     * @throws TipoSolicitudNoValidaException
     */
    public function getFolio($parametros)
    {
        // Se definen las reglas de construcción del folio

        // Si no viene año definido en los parámetros entonces se setea el año actual
        $anio = isset($parametros->anio) ? $parametros->anio : date('Y');
        $centro_id = $parametros->centro_id;
        $tipo_contador_id = $parametros->tipo_contador_id;
        $tipo_solicitud_id = $parametros->tipo_solicitud_id;

        // Se obtienen las siglas del centro
        $siglas = $this->siglasTipoSolicitud($tipo_solicitud_id);

        // Se obtiene la abreviatura del centro
        $abreviatura = $this->abreviaturaCentro($centro_id);

        // Se obtiene el consecutivo del centro
        $consecutivo = $this->contadorService->getContador($anio, $tipo_contador_id, $centro_id);

        return [$consecutivo, sprintf('%s/%s/%d/%06d', $abreviatura, $siglas, $anio, $consecutivo)];
    }

    /**
     * Abreviatura del centro
     *
     *
     * @throws CentroNoValidoException
     */
    protected function abreviaturaCentro($centro_id)
    {
        // Abreviatura del centro
        $centro = Centro::find($centro_id);
        if (! $centro) {
            throw new CentroNoValidoException;
        }

        return $centro->abreviatura;
    }

    /**
     * Siglas del tipo de solicitud: CI o CC
     *
     *
     * @throws TipoSolicitudNoValidaException
     */
    protected function siglasTipoSolicitud($tipo_solicitud_id)
    {
        // Siglas de los tipos de solicitudes
        if (! isset($this->tipoSolicitudes[$tipo_solicitud_id])) {
            throw new TipoSolicitudNoValidaException;
        }
        $siglas = $this->tipoSolicitudes[$tipo_solicitud_id];

        return $siglas;
    }
}
