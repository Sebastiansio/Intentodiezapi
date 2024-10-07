<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ResolucionPagoDiferido extends Model
{
    use SoftDeletes;

    protected $table = 'resolucion_pagos_diferidos';
    protected $guarded = ['id','created_at','updated_at'];

     /*
     * Relación con la tabla partes para los solicitados
     * un centro puede tener muchas salas
     */
    public function resolucionParte(){
        return $this->belongsTo(ResolucionPartes::class);
    }
     /*
     * Relación con la tabla partes para los solicitados
     * un centro puede tener muchas salas
     */
    public function solicitante(){
        return $this->belongsTo(Parte::class);
    }

    /**
     * Relación con audiencias.
     * Un pago diferido pertenece a una audiencia.
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function audiencia()
    {
        return $this->belongsTo(Audiencia::class);
    }
}

