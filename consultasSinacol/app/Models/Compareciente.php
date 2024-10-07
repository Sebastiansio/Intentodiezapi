<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use OwenIt\Auditing\Auditable;

class Compareciente extends Model implements AuditableContract
{
    use SoftDeletes;
    use Auditable,
        \App\Traits\CambiarEventoAudit;
    protected $table = 'comparecientes';
    protected $guarded = ['id','created_at','updated_at','deleted_at'];
    public function transformAudit($data):array
    {
        $data = $this->cambiarEvento($data);
        return $data;
    }
    /*
     * Funcion de la relación con la tabla de audiencias
     * una audiencia tiene varios comparecientes
     */
    public function audiencia(){
        return $this->belongsTo('App\Audiencia');
    }
    public function parte(){
        return $this->belongsTo('App\Parte');
    }
}
