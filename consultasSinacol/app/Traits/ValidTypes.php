<?php

namespace App\Traits;
trait ValidTypes
{
    /**
     * Función para almacenar catalogos (nombre,id) en cache
     *
     * @param [string] $nombre
     * @param [Model] $modelo
     * @return void
     */
    private function validBool(bool $valor){
        if($valor){
            return "Si";
        }
        return "No";
    }
}
