<?php

namespace App\Services;

interface FolioService
{
    /**
     * Construye el folio del expediente único implementando las reglas que determine cada estado o entidad
     * Pej un aimplementación regresaría folios del tipo: MEX/C/2021/000000001
     *
     * @param  object  $folioDTO  Objeto que lleva los parámetros que sean necesarios para la implementación del folio.
     * @return array En nivel 0 el consecutivo, en nivel 1 el folio
     */
    public function getFolio($folioDTO);
}
