<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class EntidadEmisora extends Model
{
    use SoftDeletes;
    protected $table = 'entidades_emisoras';
    public $incrementing = false;
    protected $guarded = ['id','created_at','updated_at','deleted_at'];
}
