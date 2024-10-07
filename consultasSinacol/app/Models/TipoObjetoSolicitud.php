<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TipoObjetoSolicitud extends Model
{
    protected $table = 'tipo_objeto_solicitudes';

    /**
     * Relación del tipo de solicitud con los objetos de la solicitud
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function objetoSolicitudes()
    {
        return $this->hasMany(ObjetoSolicitud::class, 'tipo_objeto_solicitudes_id');
    }
}
