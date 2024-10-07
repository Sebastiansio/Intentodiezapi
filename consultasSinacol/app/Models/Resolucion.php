<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use OwenIt\Auditing\Auditable;

class Resolucion extends Model implements AuditableContract
{
    use SoftDeletes;
    use Auditable;
    use \App\Traits\CambiarEventoAudit;
    
    protected $table = 'resoluciones';
    protected $guarded = ['created_at','updated_at','deleted_at'];
    public function transformAudit($data):array
    {
        $data = $this->cambiarEvento($data);
        return $data;
    }
    /*
     * Relacion con la tabla audiencias
     * una audiencia debe tener resolución
     */
    public function audiencias(){
        return $this->hasMany(Audiencia::class);
    }
}
