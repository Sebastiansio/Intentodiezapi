<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Nacionalidad extends Model
{
    //
    use SoftDeletes;
    protected $table = 'nacionalidades';
    protected $softDelete = true;
    public $incrementing = false;
}
