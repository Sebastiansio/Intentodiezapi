<?php
namespace App\Traits;


use App\Incidencia;

trait Incidenciable
{
    /**
     * Relaciona la incidencia con un la salas, centros y conciliadores de "uno a muchos". 
     * @return mixed
     */
    public function incidencia()
    {
        return $this->morphMany(Incidencia::class, 'incidenciable');
    }
}
 