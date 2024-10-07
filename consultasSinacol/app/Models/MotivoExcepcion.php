<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class MotivoExcepcion extends Model
{
    protected $table = 'motivo_excepciones';
    protected $guarded = ['id', 'created_at', 'updated_at',];
    public function transformAudit($data):array
    {
        $data = $this->cambiarEvento($data);
        return $data;
    }
}
