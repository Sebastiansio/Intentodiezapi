<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TipoNotificacion extends Model
{
    //
    protected $table = 'tipo_notificaciones';
    protected $guarded = ['id','created_at','updated_at','deleted_at'];
    public function audienciaParte(){
        return $this->hasMany(AudienciaParte::class);
    }
}
