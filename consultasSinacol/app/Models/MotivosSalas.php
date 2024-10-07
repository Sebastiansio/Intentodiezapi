<?php
namespace App\Models;


use Illuminate\Database\Eloquent\Model;

class MotivosSalas extends Model
{
    //
    protected $table = 'motivos_salas';
    protected $guarded = ['id','created_at','updated_at','deleted_at'];

    /*
     * Relación con la tabla Centros
     * un centro puede tener muchas salas
     */
    public function centro(){
        return $this->belongsTo(Centro::class);
    }

    /**
     * Funcion para asociar con modelo User con belongsTo
     * * Utilizando belongsTo para relaciones 1 a 1
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user(){
        return $this->belongsTo('App\User');
    }

}
