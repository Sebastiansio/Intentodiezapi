<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Industria extends Model
{
    protected $guarded = ['id', 'created_at', 'updated_at', ];

    public function girosComerciales()
    {
        return $this->hasMany(GiroComercial::class);
    }

}
