<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ResolucionPartes extends Model
{
    use SoftDeletes;
    //
    protected $table = "resolucion_partes";
    protected $guarded = ['id','created_at','updated_at'];
    /*
     * Relación con la tabla audiencias
     * un centro puede tener muchas salas
     */
    public function audiencia(){
        return $this->belongsTo(Audiencia::class);
    }
    /*
     * Relación con la tabla partes para los solicitantes
     * un centro puede tener muchas salas
     */
    public function parteSolicitante(){
        return $this->belongsTo(Parte::class,"parte_solicitante_id");
    }
    /*
     * Relación con la tabla partes para los solicitados
     * un centro puede tener muchas salas
     */
    public function parteSolicitada(){
        return $this->belongsTo(Parte::class,"parte_solicitada_id");
    }
    /*
     * Relación con la tabla partes para los solicitados
     * un centro puede tener muchas salas
     */
    public function motivoArchivado(){
        return $this->belongsTo(MotivoArchivado::class)->withDefault();
    }
    /*
     * Relación con la tabla partes para los solicitados
     * un centro puede tener muchas salas
     */
    public function terminacion_bilateral(){
        return $this->belongsTo(TerminacionBilateral::class);
    }

    /**
     * Relacion con Conceptos pagos
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function parteConceptos(){
        return $this->hasMany(ResolucionParteConcepto::class);
    }
}
