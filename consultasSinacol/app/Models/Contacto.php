<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use OwenIt\Auditing\Auditable;

class Contacto extends Model implements AuditableContract
{
    use Auditable,
        \App\Traits\CambiarEventoAudit;
    protected $guarded = ['id', 'created_at', 'updated_at', ];
    public function transformAudit($data):array
    {
        $data = $this->cambiarEvento($data);
        return $data;
    }
    /**
     * Declara la entidad como polimorfica
     * @return MorphTo
     */
    public function contactable()
    {
        return $this->morphTo();
    }
    public function tipo_contacto(){
        return $this->belongsTo(TipoContacto::class);
    }
}
