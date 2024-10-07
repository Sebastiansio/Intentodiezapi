<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Estado extends Model
{
    use SoftDeletes;
    /**
     * No queremos que autoincremente el id, los cambios en este catálogo deberán ser tratados manualmente
     * y de forma idéntica a lo que tengan las fuentes de estos catálogos.
     */
    public $incrementing = false;

    /**
     * La llave primaria es tipo caracter
     * @var string
     */
    protected $keyType = 'string';
}
