<?php
namespace App\Models;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TipoAsentamiento extends Model
{
    use SoftDeletes;
    /**
     * No queremos que autoincremente el id, los cambios en este catálogo deberán ser tratados manualmente
     * y de forma idéntica a lo que tengan las fuentes de estos catálogos.
     */
    public $incrementing = false;
}
