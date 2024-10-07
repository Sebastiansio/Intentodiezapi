<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MotivoArchivado extends Model
{
    use SoftDeletes;
    protected $guarded = ['id','created_at','updated_at','deleted_at'];
    protected $table = "motivo_archivados";
    /**
     * Relación con resolucionPartes
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function resolucionParte(){
      return $this->hasMany(ResolucionPartes::class);
    }
}
