<?php

namespace App\Traits;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
trait Menu
{
    /**
     * Función para almacenar catalogos (nombre,id) en cache
     *
     * @param [string] $nombre
     * @param [Model] $modelo
     */
    private function construirMenu($rol_id){
        //Obtenemos Los menus principales
        $role= Role::find($rol_id);
        $todosPermisos = $role->permissions;
        $menu=$todosPermisos->where("padre_id",null);
        $arreglo = array();
        foreach($menu as $formulario){
            $formulario->hijos = $this->encontrarHijos($formulario->id,$todosPermisos);
        }
        return $menu;
    }
    private function encontrarHijos($padre_id,$permisos,$menu = array()){
        $hijos = $permisos->where("padre_id",$padre_id);
        if($hijos != null){
            foreach($hijos as $men){
                $men["hijos"] = $this->encontrarHijos($men->id,$permisos);
                $menu[] = $men;
            }
        }
        return $menu;
    }
}
