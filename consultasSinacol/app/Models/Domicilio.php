<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use OwenIt\Auditing\Auditable;

class Domicilio extends Model implements AuditableContract
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
    public function domiciliable()
    {
        return $this->morphTo();
    }
    public function estado()
    {
        return $this->belongsTo(Estado::class);
    }
    public function tipo_vialidad()
    {
        return $this->belongsTo(TipoVialidad::class);
    }

}
