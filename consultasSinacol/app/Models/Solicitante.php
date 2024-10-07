<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Solicitante extends Model
{
    //
    use SoftDeletes;
    protected $softDelete = true;
    /**
    * Funcion para asociar con modelo Genero con hasMany
    * * Utilizando hasMany para relacion uno a muchos
    * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function genero(){
      return $this->belongsTo('App\Genero');
    }
}

/*menor de edad
adulto mayor
mujeres enbarazadas*/
