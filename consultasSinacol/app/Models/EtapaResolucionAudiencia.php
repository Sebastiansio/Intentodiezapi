<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class EtapaResolucionAudiencia extends Model
{
    protected $guarded = ['id','created_at','updated_at','deleted_at'];
    /**
     * Relacion con audiencia
     * @return belongsTo
     */
    public function audiencia(){
        return $this->belongsTo(Audiencia::class);
    }
    public function etapaResolucion(){
        return $this->belongsTo(EtapaResolucion::class);
    }
}
