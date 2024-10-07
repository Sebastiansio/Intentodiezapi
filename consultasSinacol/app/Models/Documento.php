<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use OwenIt\Auditing\Auditable;

class Documento extends Model implements AuditableContract
{
    use SoftDeletes,
        Auditable,
        \App\Traits\CambiarEventoAudit;
    protected $table = 'documentos';
    protected $guarded = ['id','created_at','updated_at','deleted_at'];
    public function transformAudit($data):array
    {
        $data = $this->cambiarEvento($data);
        return $data;
    }
    /**
     *  funcion que indica que es una relación polimorfica
     *  Documentable puede ser usada por toda tabla que requiera subir documentos
     */
    public function documentable()
    {
        return $this->morphTo();
    }

    /**
     * Relación con la clasificación del archivo
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function clasificacionArchivo()
    {
        return $this->belongsTo(ClasificacionArchivo::class);
    }
    /**
     * Relación con la clasificación del archivo
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function firma_documentos()
    {
        return $this->hasMany(FirmaDocumento::class);
    }
}
