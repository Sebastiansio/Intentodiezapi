<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TipoParte extends Model
{
    use SoftDeletes;
    public $incrementing = false;
    protected $guarded = ['id','updated_at','created_at'];
}
