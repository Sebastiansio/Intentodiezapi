<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use OwenIt\Auditing\Auditable;

class EstatusSolicitud extends Model implements AuditableContract
{
    use SoftDeletes;
    use Auditable;
    use \App\Traits\CambiarEventoAudit;
    // public $incrementing = false;
    protected $table = 'estatus_solicitudes';
    protected $guarded = ['id','created_at','updated_at','deleted_at'];
    public function transformAudit($data):array
    {
        $data = $this->cambiarEvento($data);
        return $data;
    }
  /**
   * Funcion para asociar con modelo Solicitud con hasMany
   * * Utilizando hasMany para relacion uno a muchos
   * @return \Illuminate\Database\Eloquent\Relations\HasMany
   */
  public function solicitudes(){
    return $this->hasMany('App\Solicitud');
  }
}
