<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class EtapaResolucion extends Model
{
    protected $table = 'etapa_resoluciones';
    protected $guarded = ['id','created_at','updated_at','deleted_at'];
}
