<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FirmaDocumento extends Model
{
    use SoftDeletes;
    protected $table = 'firmas_documentos';
    protected $guarded = ['id','created_at','updated_at','deleted_at'];

    /**
     *  funcion que indica que es una relación polimorfica
     *  Documentable puede ser usada por toda tabla que requiera subir documentos
     */
    public function firmable()
    {
        return $this->morphTo();
    }
    public function documento(){
        return $this->belongsTo(Documento::class);
    }
}
