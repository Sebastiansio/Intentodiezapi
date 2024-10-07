<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use OwenIt\Auditing\Auditable;

class AudienciaParte extends Model implements AuditableContract
{
    use SoftDeletes,Auditable,
        \App\Traits\CambiarEventoAudit;
    protected $table = 'audiencias_partes';
    protected $guarded = ['id','created_at','updated_at','deleted_at'];
    public function transformAudit($data):array
    {
        $data = $this->cambiarEvento($data);
        return $data;
    }
    /**
     * Relacion con audiencia
     * @return \Illuminate\Database\Eloquent\Relations\belongsTo
     */
    public function audiencia(){
      return $this->belongsTo(Audiencia::class);
    }
    /**
     * Relacion con parte
     * @return \Illuminate\Database\Eloquent\Relations\belongsTo
     */
    public function parte(){
      return $this->belongsTo(Parte::class);
    }
    public function tipo_notificacion(){
        return $this->belongsTo(TipoNotificacion::class)->withDefault(["nombre"=>"-"]);
    }
    public function documentos(){
        return $this->morphMany(Documento::class,'documentable');
    }
    /**
     * Relacion con concepto pagos
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function parteConceptos(){
        return $this->hasMany(ResolucionParteConcepto::class);
    }
    public function historialNotificacion(){
        return $this->hasMany(HistoricoNotificacion::class);
    }
}
