<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\AppendPolicies;
use App\Traits\LazyAppends;
use App\Traits\LazyLoads;
use App\Traits\RequestsAppends;

class TipoDiscapacidad extends Model
{
    use SoftDeletes,
        LazyLoads,
        LazyAppends,
        RequestsAppends,
        AppendPolicies; 
    protected $table = 'tipo_discapacidades';
    protected $guarded = ['id','created_at','updated_at','deleted_at'];
    
    /**
     * Funcion para asociar con modelo Parte con hasMany
     * * Utilizando hasMany para relacion uno a muchos
     * * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function partes(){
        return $this->hasMany(Parte::class);
    }
}
