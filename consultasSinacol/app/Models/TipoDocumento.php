<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TipoDocumento extends Model
{
  use SoftDeletes;
  // public $incrementing = false;
    protected $guarded = ['id','created_at','updated_at','deleted_at'];
}
