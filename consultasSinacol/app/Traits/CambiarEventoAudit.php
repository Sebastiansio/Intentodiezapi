<?php

namespace App\Traits;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
trait CambiarEventoAudit
{
    /**
     * Función para almacenar catalogos (nombre,id) en cache
     *
     * @param [string] $nombre
     * @param [Model] $modelo
     * @return void
     */
    private function cambiarEvento($data){
        if($data["event"] == "created"){
            $data["event"] = "Inserción";
        }else if($data["event"] == "updated"){
            $data["event"] = "Modificación";
        }else if($data["event"] == "deleted"){
            $data["event"] = "Eliminación";
        }
//        $modelo = $data["auditable_type"];
//        if($data["auditable_type"] == "Spatie\Permission\Models\Permission"){
//            $data["auditable_type"] = "Permiso";
//        }else if($data["auditable_type"] == "Spatie\Permission\Models\Role"){
//            $data["auditable_type"] = "Rol";
//        }else{
//            $data["auditable_type"] = substr($modelo, 4);
//        }
        return $data;
    }
}
