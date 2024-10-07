<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use OwenIt\Auditing\Auditable;

class DatoLaboral extends Model implements AuditableContract
{
    use SoftDeletes,
      Auditable,
      \App\Traits\CambiarEventoAudit;
    protected $table = 'datos_laborales';
    public function transformAudit($data):array
    {
        $data = $this->cambiarEvento($data);
        return $data;
    }  

  /**
   * The attributes that are mass assignable.
   *
   * @var array
   */
  protected $guarded = ['id', 'created_at', 'updated_at'];

/**
 * asocia datos_laborales con la tabla de jornada
 * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
 */
  public function jornada(){
    return $this->belongsTo('App\Jornada');

  }

/**
 * Funcion para asociar con modelo Estado
 * Utilizando belongsTo para relaciones 1 a 1
 * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
 */
public function periodicidad(){
  return $this->belongsTo('App\Periodicidad');
}

  /**
 * asocia datos_laborales con la tabla de parte
 * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
 */
public function parte(){
  return $this->belongsTo('App\Parte');

}
  /**
 * asocia oficios con la tabla de parte
 * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
 */
public function ocupacion(){
  return $this->belongsTo(Ocupacion::class)->withDefault(["nombre" => "N/A"]);

}
/**
 * Relacion con la tabla domicilio
 * @return type
 */
public function domicilios(){
  return $this->morphMany(Domicilio::class,'domiciliable');
}

}
