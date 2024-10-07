<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CentroMunicipio extends Model
{
    use SoftDeletes;
    protected $guarded = ['id','created_at','updated_at','deleted_at'];
    
}
