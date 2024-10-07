<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use OwenIt\Auditing\Auditable;

class ObjetoSolicitud extends Model implements AuditableContract
{
    use SoftDeletes;
    use Auditable;
    use \App\Traits\CambiarEventoAudit;
    // public $incrementing = false;
    protected $table = 'objeto_solicitudes';
    protected $guarded = ['id','created_at','updated_at','deleted_at'];
    public function transformAudit($data):array
    {
        $data = $this->cambiarEvento($data);
        return $data;
    }

    /**
     * Relación del objeto de la solicitud con tipo de objeto de solicitud
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function tipoObjetoSolicitud() {
        return $this->belongsTo(TipoObjetoSolicitud::class, 'tipo_objeto_solicitudes_id');
    }
}
