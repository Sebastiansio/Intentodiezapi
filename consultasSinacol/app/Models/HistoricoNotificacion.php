<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class HistoricoNotificacion extends Model
{
    use SoftDeletes;
    protected $table = "historico_notificaciones";
    protected $guarded = ['id','created_at','updated_at','deleted_at'];
    public function respuestas(){
        return $this->hasMany(HistoricoNotificacionRespuesta::class);
    }
    public function peticiones(){
        return $this->hasMany(HistoricoNotificacionPeticion::class);
    }
    public function peticion(){
        return $this->belongsTo(HistoricoNotificacionPeticion::class,'historico_notificacion_peticion_id');
    }
}
