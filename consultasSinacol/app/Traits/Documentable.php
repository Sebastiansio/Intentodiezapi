<?php
namespace App\Traits;


use App\Documento;

trait Documentable
{
    /**
     * Relaciona el Domicilio con una entidad domiciliable de "uno a muchos". Una entidad puede tener más de un domicilio.
     * @return mixed
     */
    public function documento()
    {
        return $this->morphMany(Documento::class, 'documentable');
    }
}