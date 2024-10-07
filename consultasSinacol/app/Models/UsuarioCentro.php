<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class UsuarioCentro extends Model
{
    protected $table = "usuarios_centros";
    protected $guarded = ['id','created_at','updated_at','deleted_at'];
    public function centro(){
        return $this->belongsTo(Centro::class);
    }
}
