<?php
namespace App\Traits;


use App\Disponibilidad;

trait Disponible
{
    /**
     * Relaciona la disponibilidad con un la salas, centros y conciliadores de "uno a muchos". 
     * @return mixed
     */
    public function disponibilidad()
    {
        return $this->morphMany(Disponibilidad::class, 'disponible');
    }
}
