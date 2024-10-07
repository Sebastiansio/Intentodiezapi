<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class EtapaNotificacion extends Model
{
    protected $table = 'etapas_notificaciones';
    protected $guarded = ['id','created_at','updated_at','deleted_at'];
}
