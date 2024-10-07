<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use OwenIt\Auditing\Auditable;

class Disponibilidad extends Model implements AuditableContract
{
    use SoftDeletes,
        Auditable,
        \App\Traits\CambiarEventoAudit;
    protected $table = 'disponibilidades';
    protected $guarded = ['id','created_at','updated_at','deleted_at'];
    public function transformAudit($data):array
    {
        $data = $this->cambiarEvento($data);
        return $data;
    }
    /*
     *  funcion que indica que es una relación polimorfica
     *  Disponibilidad puede ser usado por Conciliadores, Salas y centros
     */
    public function disponible()
    {
        return $this->morphTo();
    }
}
