<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TipoContador extends Model
{
    use SoftDeletes;
    public $incrementing = false;
    public $table='tipo_contadores';
    protected $guarded = ['id','created_at','updated_at','deleted_at'];
    public function contador(){
        return $this->hasMany(Contador::class);
    }
}
