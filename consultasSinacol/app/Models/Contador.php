<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\AppendPolicies;
use App\Traits\LazyAppends;
use App\Traits\LazyLoads;
use App\Traits\RequestsAppends;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use OwenIt\Auditing\Auditable;

class Contador extends Model implements AuditableContract
{
    use SoftDeletes,
        LazyLoads,
        LazyAppends,
        RequestsAppends,
        AppendPolicies,
        Auditable,
        \App\Traits\CambiarEventoAudit;
    protected $table='contadores';
    protected $guarded = ['id','created_at','updated_at','deleted_at'];
    public function transformAudit($data):array
    {
        $data = $this->cambiarEvento($data);
        return $data;
    }
    /**
     * Relacion con la tabla tipo_contadores
     * un contador debe tener un tipo contador
     */
    public function tipoContador(){
    	return $this->belongsTo(TipoContador::class);
    }
    /**
     * Relacion con la tabla centros
     * @return type
     */
    public function centro(){
    	return $this->belongsTo(Centro::class);
    }
    
}
