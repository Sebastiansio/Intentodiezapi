<?php

namespace App\Services;

/**
 * Interfase que define el numero de días de una solicitud
 * Interface DiasVigenciaSolicitudService
 */
interface DiasVigenciaSolicitudService
{
    /**
     * define si una solicitud aun esta vigente dentro de los días definidos
     *
     * @param  int  $solicitud_id  Identificador de la solicitud a evaluar
     * @param  string  $fecha_solicitada  Fecha en la que se solicita realizar la acción
     * @return bool
     */
    public function getSolicitudVigente($solicitud_id, $fecha_solicitada);
}
