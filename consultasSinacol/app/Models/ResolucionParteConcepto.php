<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ResolucionParteConcepto extends Model
{
    use SoftDeletes;
    protected $table = 'resolucion_parte_conceptos';
    protected $guarded = ['id','created_at','updated_at'];
    
    /*
     * Relación con la tabla conceptoPagoResolucion
     * una resolucion tener muchas conceptos
     */
    public function ConceptoPagoResolucion(){
        return $this->belongsTo(ConceptoPagoResolucion::class,"concepto_pago_resoluciones_id");
    }
    /*
     * Relación con la tabla resolucionPartes
     * un centro puede tener muchas salas
     */
    public function ResolucionPartes(){
        return $this->belongsTo(ResolucionPartes::class);
    }
}
