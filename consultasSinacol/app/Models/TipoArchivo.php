<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TipoArchivo extends Model
{
    use SoftDeletes;
    public $incrementing = false;
    protected $guarded = ['id','created_at','updated_at','deleted_at'];

}
