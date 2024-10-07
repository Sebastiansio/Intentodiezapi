<?php
namespace App\Traits;


use App\FirmaDocumento;

trait Firmable
{
    /**
     * Relaciona el Domicilio con una entidad domiciliable de "uno a muchos". Una entidad puede tener más de un domicilio.
     * @return mixed
     */
    public function firma()
    {
        return $this->morphMany(FirmaDocumento::class, 'firmable');
    }
}