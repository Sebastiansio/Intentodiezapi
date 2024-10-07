<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PlantillaDocumento extends Model
{
  use SoftDeletes;
  protected $table = 'plantilla_documentos';
  protected $guarded = ['id','created_at','updated_at','deleted_at'];

  /*
   *  funcion que indica que es una relación polimorfica
   *  plantilla puede ser usada por toda tabla que requiera plantillas
   */
  public function plantilla()
  {
      return $this->morphTo();
  }

}
