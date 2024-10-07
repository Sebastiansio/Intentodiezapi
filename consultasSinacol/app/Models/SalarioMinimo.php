<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalarioMinimo extends Model
{
    use SoftDeletes;
    protected $table = 'salarios_minimos';
    public $incrementing = false;
    protected $guarded = ['id','created_at','updated_at'];
    
}
