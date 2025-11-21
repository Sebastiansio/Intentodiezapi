<?php 

namespace App\Services;

use Illuminate\Support\Facades\DB;

use App\Parte;

class ParteService
{
    /**
     * Get a part by name
     * 
     * @param string $nombreParte
     * @param int $tipoPersonaId (default 1)
     * 
     * @return object
     */
    public function getParteByName($nombreParte, $tipoPersonaId = 1)
    {
        $nombreClear = str_replace(' ', '', trim($nombreParte));

        return Parte::query()
                    ->select(['id'])
                    ->where('tipo_persona_id', $tipoPersonaId)
                    ->whereRaw("replace(concat(trim(nombre), trim(primer_apellido), trim(segundo_apellido)), ' ', '') like ?", [ '%' .  strtoupper($nombreClear) . '%' ])
                    ->get();
    }
}