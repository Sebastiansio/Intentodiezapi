<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class HorarioInhabil extends Model
{
    protected $table = 'horarios_inhabiles';
    protected $guarded = ['id','created_at','updated_at','deleted_at'];
    public function inhabilitable(){
        return $this->morphTo(__FUNCTION__, 'inhabilitable_type', 'inhabilitable_id');
    }
}
