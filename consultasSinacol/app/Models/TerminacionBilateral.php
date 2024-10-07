<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TerminacionBilateral extends Model
{
    protected $table = "terminacion_bilaterales";
    protected $guarded = ['id','created_at','updated_at'];
}
