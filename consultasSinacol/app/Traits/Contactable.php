<?php
namespace App\Traits;


use App\Contacto;

trait Contactable
{
    /**
     * Relaciona el Contacto con una entidad contactable de "uno a muchos". Una entidad puede tener más de un contacto.
     * @return mixed
     */
    public function contacto()
    {
        return $this->morphMany(Contacto::class, 'contactable');
    }
}
