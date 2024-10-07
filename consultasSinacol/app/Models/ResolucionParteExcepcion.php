<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ResolucionParteExcepcion extends Model
{
    protected $table = 'resolucion_parte_excepciones';
    protected $guarded = ['id', 'created_at', 'updated_at',];
    public function transformAudit($data):array
    {
        $data = $this->cambiarEvento($data);
        return $data;
    }
}
