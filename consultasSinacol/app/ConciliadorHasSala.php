<?php

namespace App;


use Illuminate\Database\Eloquent\Model;

class ConciliadorHasSala extends Model
{
protected $table = 'conciliador_has_salas';
protected $guarded = ['id','modified_date', 'modified_by', 'created_at', 'updated_at', 'deleted_at'];

    /*
     * Relacion con ta tabla de personas
     * una conciliador debe tener una persona
     */
    public function conciliador(){
    	return $this->belongsTo(Conciliador::class);
    }
    /*
     * Relacion con la tabla de centros
     * una conciliador debe tener un centro
     */
    public function centro(){
    	return $this->belongsTo(Centro::class);
    }
    /*
     * Relacion con la tabla salas
     * una conciliador debe tener una sala
     */
    public function sala(){
    	return $this->belongsTo(Sala::class);
    }
}
