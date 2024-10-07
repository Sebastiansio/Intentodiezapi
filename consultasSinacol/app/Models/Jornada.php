<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use OwenIt\Auditing\Auditable;

class Jornada extends Model implements AuditableContract
{
    use SoftDeletes;
    use Auditable;
    use \App\Traits\CambiarEventoAudit;
    public $incrementing = false;
    protected $guarded = ['updated_at','created_at']; 
    public function transformAudit($data):array
    {
        $data = $this->cambiarEvento($data);
        return $data;
    }
}
