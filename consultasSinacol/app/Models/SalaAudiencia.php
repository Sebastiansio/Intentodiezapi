<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use OwenIt\Auditing\Auditable;
use Illuminate\Support\Arr;

class SalaAudiencia extends Model implements AuditableContract
{
    use SoftDeletes,
        Auditable,
        \App\Traits\CambiarEventoAudit; 
    protected $table = 'salas_audiencias';
    protected $guarded = ['id','created_at','updated_at','deleted_at'];
    protected $loadable = ['salas','audiencias'];
    public function transformAudit($data):array
    {
        if (Arr::has($data, 'new_values.sala_id')) {
            if($data["event"] != "created"){
                $data['old_values']['sala'] = Sala::find($this->getOriginal('sala_id'))->sala;
                unset($data['old_values']['sala_id']);
            }
            $data['new_values']['sala'] = Sala::find($this->getAttribute('sala_id'))->sala;
            unset($data['new_values']['sala_id']);
        }
        $data = $this->cambiarEvento($data);
        return $data;
    }
    /*
     * Relación con la tabla Salas
     * una sala_audiencia puede tener muchas salas
     */
    public function sala(){
        return $this->belongsTo(Sala::class);
    }
    /*
     * Relacion con la tabla audiencias
     * una sala_audiencia debe tener muchas audiencias
     */
    public function audiencia(){
        return $this->belongsTo(Audiencia::class);
    }
}

