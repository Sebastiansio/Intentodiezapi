<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TipoTerminacionAudiencia extends Model
{
    use SoftDeletes;
    protected $guarded = ['id','created_at','updated_at','deleted_at'];

    /**
     * Colección de audiencias pertenecientes a un tipo de terminación
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function audiencias()
    {
        return $this->hasMany(Audiencia::class);
    }
}
