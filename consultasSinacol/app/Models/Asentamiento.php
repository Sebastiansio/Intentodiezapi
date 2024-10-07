<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Asentamiento extends Model
{
    public $incrementing = false;
    protected $guarded = ['updated_at','created_at']; 
}
