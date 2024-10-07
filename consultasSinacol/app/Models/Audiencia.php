<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\AppendPolicies;
use App\Traits\LazyAppends;
use App\Traits\LazyLoads;
use App\Traits\RequestsAppends;
use Illuminate\Support\Arr;
use OwenIt\Auditing\Contracts\Auditable;
use App\Traits\ValidTypes;

class Audiencia extends Model implements Auditable
{
    use SoftDeletes,
        LazyLoads,
        LazyAppends,
        RequestsAppends,
        AppendPolicies,
        \OwenIt\Auditing\Auditable,
        \App\Traits\CambiarEventoAudit,
        ValidTypes;

    /**
     * Nombre de la tabla
     * @var string
     */
    protected $table = 'audiencias';
    protected $guarded = ['id','created_at','updated_at','deleted_at'];
    protected $loadable = ['conciliador', 'sala','parte','resolucion'];

    public function transformAudit($data):array
    {
        if (Arr::has($data, 'new_values.finalizada')) {
            if($data["event"] != "created"){
                $data['old_values']['finalizada'] = $this->validBool($this->getOriginal('finalizada'));
            }
            $data['new_values']['finalizada'] = $this->validBool($this->getAttribute('finalizada'));
        }
        $data = $this->cambiarEvento($data);
        return $data;
    }

    /**
     * Relación con expediente
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function expediente(){
      return $this->belongsTo('App\Expediente');
    }

    /**
     * Relación con conciliador
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function conciliador(){
      return $this->belongsTo('App\Conciliador');
    }

    /**
     * Relación con parte
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function parte()
    {
        return $this->belongsTo(Parte::class, 'parte_responsable_id');
    }

    /**
     * Relación con resolución
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function resolucion(){
      return $this->belongsTo('App\Resolucion');
    }

    /**
     * Relación con comparecientes
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function comparecientes(){
      return $this->hasMany('App\Compareciente');
    }

    /**
     * Relación con salasAudiencias
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function salasAudiencias(){
      return $this->hasMany('App\SalaAudiencia');
    }
    /**
     * Relación con conciliadoresAudiencias
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function conciliadoresAudiencias(){
      return $this->hasMany('App\ConciliadorAudiencia');
    }
    /**
     * Relación con documentos
     * @return \Illuminate\Database\Eloquent\Relations\morphMany
     */
    public function documentos(){
        return $this->morphMany(Documento::class,'documentable');
    }
    /**
     * Relación con audienciaParte
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function audienciaParte(){
        return $this->hasMany(AudienciaParte::class);
    }
    /**
     * Relacion con resolucionParte
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function resolucionPartes(){
        return $this->hasMany(ResolucionPartes::class);
    }
    public function etapasResolucionAudiencia(){
        return $this->hasMany(EtapaResolucionAudiencia::class);
    }
    /**
     * Relacion con pago diferido
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function pagosDiferidos(){
        return $this->hasMany(ResolucionPagoDiferido::class)->orderBy('fecha_pago');
    }
    public function etapa_notificacion(){
        return $this->belongsTo(EtapaNotificacion::class)->withDefault(["etapa" => "N/A"]);
    }
    public function getEsUltimaAttribute(){
      $num_total = $this->expediente->audiencia->count();
      if($num_total == $this->numero_audiencia){
        return true;
      }
      return false;
  }

    /**
     * Relación con el catálogo de tipo de terminaciones de audiencias
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function tipoTerminacion(){
        return $this->belongsTo(TipoTerminacionAudiencia::class, 'tipo_terminacion_audiencia_id');
    }
}
