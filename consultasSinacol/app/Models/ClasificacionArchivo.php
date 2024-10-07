<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClasificacionArchivo extends Model
{
    use SoftDeletes;
    protected $guarded = ['id', 'created_at', 'updated_at', ];

    public function documentos()
    {
        return $this->hasMany(Documento::class);
    }
    public function entidad_emisora(){
        return $this->belongsTo(EntidadEmisora::class)->withDefault(['nombre'=>'No asignado']);
    }
    public function tipo_archivo(){
        return $this->belongsTo(TipoArchivo::class)->withDefault(['nombre'=>'No asignado']);
    }
}
