<?php

namespace App\Models;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\AppendPolicies;
use App\Traits\LazyAppends;
use App\Traits\LazyLoads;
use App\Traits\RequestsAppends;
use Illuminate\Support\Arr;
use OwenIt\Auditing\Contracts\Auditable;
use App\Traits\ValidTypes;
use Illuminate\Database\Eloquent\Builder;

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

    // Definimos el scope para las condiciones del query  
    public function scopeWithDetails(Builder $query, $fechaAudiencia)
    {
        return $query
            ->select('audiencias.*', 'contactos.contacto', 'contactos.tipo_contacto_id', 'expedientes.folio', 
                     \DB::raw("CONCAT(personas.nombre, ' ', personas.primer_apellido, ' ', personas.segundo_apellido) as nombre_conciliador"))
            ->join('audiencias_partes', 'audiencias.id', '=', 'audiencias_partes.audiencia_id')
            ->join('partes', 'partes.id', '=', 'audiencias_partes.parte_id')
            ->join('contactos', function ($join) {
                $join->on('contactos.contactable_id', '=', 'partes.id')
                     ->where('contactos.contactable_type', 'App\\Parte');
            })
            ->join('expedientes', 'audiencias.expediente_id', '=', 'expedientes.id')
            ->join('conciliadores', 'audiencias.conciliador_id', '=', 'conciliadores.id')
            ->join('personas', 'conciliadores.persona_id', '=', 'personas.id')
            ->join('solicitudes', function ($join) {
                $join->on('partes.solicitud_id', '=', 'solicitudes.id')
                     ->where('solicitudes.inmediata', false)
                     ->where('solicitudes.centro_id', '38');
            })
            ->where('audiencias.fecha_audiencia', $fechaAudiencia);
    }

    public function scopeConciliacionAudiencias($query, $fechaInicio, $fechaFin, $centroId = 38)
    {
        return $query
            ->select([
                DB::raw("CONCAT(s.folio, '/', TO_CHAR(s.created_at, 'YYYY')) AS folio_solicitud"),
                'e.folio AS expediente',
                DB::raw("CONCAT(audiencias.folio, '/', TO_CHAR(audiencias.created_at, 'YYYY')) AS audiencia"),
                'audiencias.fecha_audiencia AS fecha_evento',
                DB::raw("audiencias.hora_inicio::text AS hora_inicio"),
                DB::raw("audiencias.hora_fin::text AS hora_termino"),
                DB::raw("STRING_AGG(DISTINCT TRIM(UPPER(CONCAT(p.nombre, ' ', p.primer_apellido, ' ', p.segundo_apellido))), ' | ') AS conciliador"),
                DB::raw("STRING_AGG(DISTINCT sl.sala, ' | ') AS sala"),
                DB::raw("CASE WHEN audiencias.finalizada = true THEN 'Finalizada' ELSE 'Por celebrar' END AS estatus"),
                // Agregamos el paréntesis de cierre faltante en cada función ARRAY_AGG
                DB::raw("ARRAY_AGG(DISTINCT TRIM(UPPER(CONCAT(ps.nombre, ' ', ps.primer_apellido, ' ', ps.segundo_apellido, ' ', COALESCE(ps.nombre_comercial, ''))))) AS solicitantes"),
                DB::raw("ARRAY_AGG(DISTINCT TRIM(UPPER(CONCAT(pc.nombre, ' ', pc.primer_apellido, ' ', pc.segundo_apellido, ' ', COALESCE(pc.nombre_comercial, ''))))) AS citados"),
                DB::raw("'Audiencia' AS tipo_evento"),
            ])
            ->leftJoin('conciliadores_audiencias AS ca', 'ca.audiencia_id', '=', 'audiencias.id')
            ->leftJoin('conciliadores AS c', 'c.id', '=', 'ca.conciliador_id')
            ->leftJoin('personas AS p', 'p.id', '=', 'c.persona_id')
            ->leftJoin('salas_audiencias AS sa', 'sa.audiencia_id', '=', 'audiencias.id')
            ->leftJoin('salas AS sl', 'sl.id', '=', 'sa.sala_id')
            ->leftJoin('expedientes AS e', 'e.id', '=', 'audiencias.expediente_id')
            ->leftJoin('solicitudes AS s', 's.id', '=', 'e.solicitud_id')
            ->leftJoin('partes AS ps', function ($join) {
                $join->on('ps.solicitud_id', '=', 's.id')
                     ->where('ps.tipo_parte_id', '=', 1);
            })
            ->leftJoin('partes AS pc', function ($join) {
                $join->on('pc.solicitud_id', '=', 's.id')
                     ->where('pc.tipo_parte_id', '=', 2);
            })
            ->whereBetween('audiencias.fecha_audiencia', [$fechaInicio, $fechaFin])
            ->where('s.centro_id', $centroId)
            ->where('s.inmediata', false)
            ->groupBy([
                's.folio',
                's.created_at',
                'e.folio',
                'audiencias.folio',
                'audiencias.created_at',
                'audiencias.fecha_audiencia',
                'audiencias.hora_inicio',
                'audiencias.hora_fin',
                'audiencias.finalizada',
            ])
            ->orderBy('fecha_evento')
            ->orderBy('hora_inicio');
    }

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
