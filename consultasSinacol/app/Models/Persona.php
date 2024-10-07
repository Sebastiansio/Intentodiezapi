<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use OwenIt\Auditing\Auditable;

/**
 * Class Persona
 * @package App
 */
class Persona extends Model implements AuditableContract
{
    use SoftDeletes,
    Auditable,
    \App\Traits\CambiarEventoAudit;
    protected $table='personas';
    protected $guarded = ['id','created_at','updated_at','deleted_at'];
    public function transformAudit($data):array
    {
        $data = $this->cambiarEvento($data);
        return $data;
    }

    /**
     * Una persona puede tener una cuenta de usuario del sistema.
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function user()
    {
        return $this->hasOne(User::class);
    }

    /**
     * Relacion con la tabla tipo personas
     * una persona debe tener un tipo persona
     */
    public function tipoPersona(){
    	return $this->belongsTo(TipoPersona::class);
    }

    /**
     * Relación inversa con la tabla conciliadores
     * una persona puede ser un conciliador
     */
    public function conciliador(){
    	return $this->hasOne(Conciliador::class);
    }

    /**
     * Get the persona's full name.
     *
     * @return string
     */    
    public function getFullNameAttribute(){
        return "{$this->nombre} {$this->primer_apellido} {$this->segundo_apellido}";
    }
    
    public function firmas(){
        return $this->morphMany(FirmaDocumento::class,'firmable');
    }
}
