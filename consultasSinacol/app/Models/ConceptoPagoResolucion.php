<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ConceptoPagoResolucion extends Model
{
    protected $table = 'concepto_pago_resoluciones';
    protected $guarded = ['id','created_at','updated_at'];
}
