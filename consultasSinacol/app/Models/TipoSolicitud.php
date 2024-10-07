<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TipoSolicitud extends Model
{
    protected $table = 'tipo_solicitudes';
    protected $guarded = ['id','created_at','updated_at','deleted_at'];
    public function solicitudes(){
        return $this->hasMany('App\Solicitud');
    }
}
