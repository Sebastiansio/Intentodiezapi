<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TipoVialidad extends Model
{
    use SoftDeletes;
    /**
     * El nombre de la tabla se declara manualmente debido a que el plural no es regular (vialidad y vialidades)
     * por lo que la convención queda idiomáticamente mal si sisguieramos la regla quedaría el nombre de la clase "TipoVialidade"
     */
    protected $table = 'tipo_vialidades';
    /**
     * No queremos que autoincremente el id, los cambios en este catálogo deberán ser tratados manualmente
     * y de forma idéntica a lo que tengan las fuentes de estos catálogos.
     */
    public $incrementing = false;
}
