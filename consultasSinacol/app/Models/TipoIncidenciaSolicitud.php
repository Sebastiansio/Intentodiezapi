<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TipoIncidenciaSolicitud extends Model
{
    protected $table = 'tipo_incidencia_solicitudes';
    protected $guarded = ['id','created_at','updated_at','deleted_at'];
}
