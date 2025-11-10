<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ambito extends Model
{
    protected $guarded = ['id','created_at','updated_at','deleted_at'];

    use SoftDeletes;
    /**
     * Funcion para asociar con modelo GiroComercial con hasMany
     * * Utilizando hasMany para relacion uno a muchos
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function GirosComerciales(){
      return $this->hasMany('App\GiroComercial');
    }
}
