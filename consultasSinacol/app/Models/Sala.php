<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\AppendPolicies;
use App\Traits\LazyAppends;
use App\Traits\LazyLoads;
use App\Traits\RequestsAppends;
use Illuminate\Support\Arr;
use OwenIt\Auditing\Contracts\Auditable as AuditableContrat;

class Sala extends Model implements AuditableContrat
{
    use SoftDeletes,
        LazyLoads,
        LazyAppends,
        RequestsAppends,
        AppendPolicies,
        \OwenIt\Auditing\Auditable,
        \App\Traits\CambiarEventoAudit; 
    protected $table = 'salas';
    protected $guarded = ['id','created_at','updated_at','deleted_at'];
    protected $loadable = ['centro'];
    public function transformAudit($data):array
    {
        if (Arr::has($data, 'new_values.centro_id')) {
            if($data["event"] != "created"){
                $data['old_values']['Centro'] = Centro::find($this->getOriginal('centro_id'))->nombre;
                unset($data['old_values']["centro_id"]);
            }
            $data['new_values']['Centro'] = Centro::find($this->getAttribute('centro_id'))->nombre;
            unset($data['new_values']["centro_id"]);
        }
        $data = $this->cambiarEvento($data);
        return $data;
    }
    /*
     * Relación con la tabla Centros
     * un centro puede tener muchas salas
     */
    public function centro(){
        return $this->belongsTo(Centro::class);
    }
    /*
     * Relacion con la tabla audiencias
     * una audiencia debe tener una sala
     */
    public function audiencias(){
        return $this->hasMany('App\Audiencia');
    }
    /**
     * Relacion con la tabla disponibilidad
     * @return type
     */
    public function disponibilidades(){
        return $this->morphMany(Disponibilidad::class,'disponibilidad');
    }
    /**
     * Relacion con la tabla incidencias
     * @return type
     */
    public function incidencias(){
        return $this->morphMany(Incidencia::class,'incidenciable');
    }
    /**
     * Relacion con la tabla Sala_audiencia
     * @return type
     */
    public function salaAudiencia(){
        return $this->hasMany(SalaAudiencia::class);
    }

    public function tieneConciliador () {
        return $this->hasOne(ConciliadorHasSala::class, 'sala_id', 'id');
    }
}
