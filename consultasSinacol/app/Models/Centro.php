<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

class Centro extends Model implements Auditable
{

    use SoftDeletes,
    \App\Traits\CambiarEventoAudit,
    \OwenIt\Auditing\Auditable;
    protected $guarded = ['id','created_at','updated_at','deleted_at','url_instancia_notificacion'];
    public function transformAudit($data):array
    {
        $data = $this->cambiarEvento($data);
        return $data;
    }

    /**
     * Funcion para asociar con modelo Solicitud con hasMany
     * * Utilizando hasMany para relacion uno a muchos
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function solicitudes(){
      return $this->hasMany('App\Solicitud');
    }

    /**
     * Funcion para asociar con modelo Salas con hasMany
     * * Utilizando hasMany para relacion uno a muchos
     * * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function salas(){
      return $this->hasMany('App\Sala');
    }
    /**
     * Funcion para asociar con modelo User con hasMany
     * * Utilizando hasMany para relacion uno a muchos
     * * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function user(){
      return $this->hasMany('App\User');
    }

  /**
   * Funcion para asociar con modelo Solicitud con hasMany
   * * Utilizando hasMany para relacion uno a muchos
   * * @return \Illuminate\Database\Eloquent\Relations\HasMany
   */
    public function conciliadores(){
            return $this->hasMany('App\Conciliador');
    }
  /**
   * Funcion para asociar con modelo Solicitud con hasMany
   * * Utilizando hasMany para relacion uno a muchos
   * * @return \Illuminate\Database\Eloquent\Relations\HasMany
   */
    public function contadores(){
            return $this->hasMany(Contador::class);
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
     * @return \Illuminate\Database\Eloquent\Relations\MorphTo
     */
    
    public function domicilio()
    {
        return $this->morphOne(Domicilio::class, 'domiciliable');
    }
    public function contactos() {
        return $this->morphMany(Contacto::class, 'contactable');
    }
}
