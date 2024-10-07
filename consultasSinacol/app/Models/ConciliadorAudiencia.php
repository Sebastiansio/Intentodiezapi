<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use OwenIt\Auditing\Auditable;
use Illuminate\Support\Arr;

class ConciliadorAudiencia extends Model implements AuditableContract
{
    use SoftDeletes;
    use Auditable,
    \App\Traits\CambiarEventoAudit;
    protected $table = 'conciliadores_audiencias';
    protected $guarded = ['id','created_at','updated_at','deleted_at'];
    protected $loadable = ['conciliadores','audiencias'];
    public function transformAudit($data):array
    {
        if (Arr::has($data, 'new_values.conciliador_id')) {
            if($data["event"] != "created"){
                $conciliador = Conciliador::find($this->getOriginal('conciliador_id'))->persona;
                $data['old_values']['conciliador'] = $conciliador->nombre." ".$conciliador->primer_apellido." ".$conciliador->segundo_apellido;
                unset($data['old_values']['conciliador_id']);
            }
            $conciliadorNew = Conciliador::find($this->getAttribute('conciliador_id'))->persona;
            $data['new_values']['conciliador'] = $conciliadorNew->nombre." ".$conciliadorNew->primer_apellido." ".$conciliadorNew->segundo_apellido;
            unset($data['new_values']['conciliador_id']);
        }
        $data = $this->cambiarEvento($data);
        return $data;
    }
    /*
     * Relación con la tabla Salas
     * una sala_audiencia puede tener muchas salas
     */
    public function conciliador(){
        return $this->belongsTo(Conciliador::class);
    }
    /*
     * Relacion con la tabla audiencias
     * una sala_audiencia debe tener muchas audiencias
     */
    public function audiencia(){
        return $this->belongsTo(Audiencia::class);
    }
}
