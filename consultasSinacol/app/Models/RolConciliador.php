<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use OwenIt\Auditing\Auditable;

class RolConciliador extends Model implements AuditableContract
{
    use SoftDeletes,
        Auditable,
        \App\Traits\CambiarEventoAudit;
    protected $table = 'roles_conciliador';
    protected $guarded = ['id','created_at','updated_at','deleted_at'];

    /**
     * Relación con conciliador
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function conciliador(){
      return $this->belongsTo('App\Conciliador');
    }
    
    /**
     * Relación con roles_atencion
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function rolAtencion(){
      return $this->belongsTo('App\RolAtencion');
    }
}
