<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use OwenIt\Auditing\Auditable;

class RolAtencion extends Model implements AuditableContract
{
    use Auditable;
    use \App\Traits\CambiarEventoAudit;
    protected $table = 'roles_atencion';
    protected $guarded = ['id','created_at','updated_at','deleted_at'];
    public function transformAudit($data):array
    {
        $data = $this->cambiarEvento($data);
        return $data;
    }
    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function RolesConciliador(){
      return $this->hasMany('App\RolConciliador');
    }
}
