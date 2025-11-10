<?php
namespace App;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\AppendPolicies;
use App\Traits\LazyAppends;
use App\Traits\LazyLoads;
use App\Traits\RequestsAppends;
use OwenIt\Auditing\Contracts\Auditable;
use Illuminate\Support\Arr;

class Conciliador extends Model implements Auditable
{
    use SoftDeletes,
        LazyLoads,
        LazyAppends,
        RequestsAppends,
        AppendPolicies,
        \OwenIt\Auditing\Auditable,
        \App\Traits\CambiarEventoAudit;
    protected $table = 'conciliadores';
    protected $guarded = ['id', 'created_at', 'updated_at', 'deleted_at'];
    protected $loadable = ['persona'];
    public function transformAudit($data): array
    {
        if (Arr::has($data, 'new_values.centro_id')) {
            if ($data["event"] != "created") {
                $data['old_values']['Centro'] = Centro::find($this->getOriginal('centro_id'))->nombre;
                unset($data['old_values']["centro_id"]);
            }
            $data['new_values']['Centro'] = Centro::find($this->getAttribute('centro_id'))->nombre;
            unset($data['new_values']["centro_id"]);
        }
        if (Arr::has($data, 'new_values.persona_id')) {
            if ($data["event"] != "created") {
                $persona = Persona::find($this->getOriginal('persona_id'));
                $data['old_values']['Persona'] = $persona->nombre . " " . $persona->primer_apellido . " " . $persona->segundo_apellido;
                unset($data['old_values']["centro_id"]);
            }
            $personaNew = Persona::find($this->getAttribute('persona_id'));
            $data['new_values']['Persona'] = $personaNew->nombre . " " . $personaNew->primer_apellido . " " . $personaNew->segundo_apellido;
            unset($data['new_values']["centro_id"]);
        }
        $data = $this->cambiarEvento($data);
        return $data;
    }
    /*
     * Relacion con ta tabla de personas
     * una conciliador debe tener una persona
     */
    public function persona()
    {
        return $this->belongsTo(Persona::class);
    }
    /*
     * Relacion con la tabla de centros
     * una conciliador debe tener un centro
     */
    public function centro()
    {
        return $this->belongsTo(Centro::class);
    }

    public function tieneSala()
    {
        return $this->hasOne(ConciliadorHasSala::class, 'conciliador_id', 'id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function rolesConciliador()
    {
        return $this->hasMany('App\RolConciliador');
    }
    /*
     * Relacion con la tabla de audiencias
     * una conciliador puede tener muchas audiencias
     */
    public function audiencias()
    {
        return $this->hasMany('App\Audiencia');
    }
    /**
     * Relacion con la tabla disponibilidad
     * @return type
     */
    public function disponibilidades()
    {
        return $this->morphMany(Disponibilidad::class, 'disponibilidad');
    }
    /**
     * Relacion con la tabla incidencias
     * @return type
     */
    public function incidencias()
    {
        return $this->morphMany(Incidencia::class, 'incidenciable');
    }
    /**
     * Relacion con la tabla conciliadoresAudiencia
     * @return type
     */
    public function conciliadorAudiencia()
    {
        return $this->hasMany(ConciliadorAudiencia::class);
    }
    public function firmas()
    {
        return $this->morphMany(FirmaDocumento::class, 'firmable');
    }
    public function horario_comida()
    {
        return $this->morphOne(HorarioInhabil::class, 'inhabilitable');
    }
}
