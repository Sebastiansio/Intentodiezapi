<?php
namespace App\Traits;


use App\Domicilio;

trait Domiciliable
{
    /**
     * Relaciona el Domicilio con una entidad domiciliable de "uno a muchos". Una entidad puede tener más de un domicilio.
     * @return mixed
     */
    public function domicilio()
    {
        return $this->morphMany(Domicilio::class, 'domiciliable');
    }
}
