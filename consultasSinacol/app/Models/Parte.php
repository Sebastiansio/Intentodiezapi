<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\LazyLoads;
use App\Traits\RequestsAppends;
use App\Traits\LazyAppends;
use App\Traits\AppendPolicies;
use Illuminate\Support\Arr;
use OwenIt\Auditing\Contracts\Auditable;
use App\Traits\ValidTypes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Parte extends Model implements Auditable
{
    use SoftDeletes,
        LazyLoads,
        LazyAppends,
        RequestsAppends,
        AppendPolicies,
        \OwenIt\Auditing\Auditable,
        ValidTypes,
        \App\Traits\CambiarEventoAudit;
        /**
     * Las relaciones que son cargables.
     *
     * @var array
     */
    protected $loadable = ['solicitud', 'tipoParte','genero','tipoPersona','nacionalidad','entidadNacimiento','grupoPrioritario','lenguaIndigena'];
    
    protected $guarded = ['id','updated_at','created_at'];
    
    public function transformAudit($data):array
    {
//        Validamos el tipo_persona
        if (Arr::has($data, 'new_values.tipo_persona_id')) {
            if($data["event"] != "created"){
                $data['old_values']['tipo persona'] = TipoPersona::find($this->getOriginal('tipo_persona_id'))->name;
                unset($data['old_values']["tipo_persona_id"]);
            }
            $data['new_values']['tipo persona'] = TipoPersona::find($this->getAttribute('tipo_persona_id'))->name;
            unset($data['new_values']["tipo_persona_id"]);
        }
//        Validamos el genero_id
        // if (Arr::has($data, 'new_values.genero_id')) {
        //     if($data["event"] != "created"){
        //         $data['old_values']['Genero'] = Genero::find($this->getOriginal('genero_id'))->name;
        //         unset($data['old_values']["genero_id"]);
        //     }
        //     $data['new_values']['Genero'] = Genero::find($this->getAttribute('genero_id'))->name;
        //     unset($data['new_values']["genero_id"]);
        // } 

//        Validamos la nacionalidad
        // if (Arr::has($data, 'new_values.nacionalidad_id')) {
        //     if($data["event"] != "created"){
        //         $data['old_values']['Nacionalidad'] = Nacionalidad::find($this->getOriginal('nacionalidad_id'))->name;
        //         unset($data['old_values']["nacionalidad_id"]);
        //     }
        //     $data['new_values']['Nacionalidad'] = Nacionalidad::find($this->getAttribute('nacionalidad_id'))->name;
        //     unset($data['new_values']["nacionalidad_id"]);
        // }
//        Validamos la entidad de nacimiento
        if (Arr::has($data, 'new_values.entidad_nacimiento_id')) {
            if($this->getOriginal('entidad_nacimiento_id') != null){
                if (Arr::has($data, 'new_values.entidad_nacimiento_id')) {
                    if($data["event"] != "created"){
                        $data['old_values']['Entidad de nacimiento'] = Nacionalidad::find($this->getOriginal('entidad_nacimiento_id'))->name;
                        unset($data['old_values']["entidad_nacimiento_id"]);
                    }
                    $data['new_values']['Entidad de nacimiento'] = Nacionalidad::find($this->getAttribute('entidad_nacimiento_id'))->name;
                    unset($data['new_values']["entidad_nacimiento_id"]);
                }
            }
        }
//        Validamos el primer apellido
        if (Arr::has($data, 'new_values.primer_apellido')) {
            if($data["event"] != "created"){
                $data['old_values']['Primer apellido'] = $this->getOriginal('primer_apellido');
                unset($data['old_values']["primer_apellido"]);
            }
            $data['new_values']['Primer apellido'] = $this->getAttribute('primer_apellido');
            unset($data['new_values']["primer_apellido"]);
        }
//        Validamos el segundo apellido
        if (Arr::has($data, 'new_values.segundo_apellido')) {
            if($data["event"] != "created"){
                $data['old_values']['Segundo apellido'] = $this->getOriginal('segundo_apellido');
                unset($data['old_values']["segundo_apellido"]);
            }
            $data['new_values']['Segundo apellido'] = $this->getAttribute('segundo_apellido');
            unset($data['new_values']["segundo_apellido"]);
        }
//        Validamos el nombre_comercial
        if (Arr::has($data, 'new_values.nombre_comercial')) {
            if($data["event"] != "created"){
                $data['old_values']['Nombre comercial'] = $this->getOriginal('nombre_comercial');
                unset($data['old_values']["nombre_comercial"]);
            }
            $data['new_values']['Nombre comercial'] = $this->getAttribute('nombre_comercial');
            unset($data['new_values']["nombre_comercial"]);
        }
//        Validamos la fecha de nacimiento
        if (Arr::has($data, 'new_values.fecha_nacimiento')) {
            if($data["event"] != "created"){
                $data['old_values']['Fecha de nacimiento'] = $this->getOriginal('fecha_nacimiento');
                unset($data['old_values']["fecha_nacimiento"]);
            }
            $data['new_values']['Fecha de nacimiento'] = $this->getAttribute('fecha_nacimiento');
            unset($data['new_values']["Fecha de nacimiento"]);
        }
//        Validamos el numero_notaria
        if (Arr::has($data, 'new_values.numero_notaria')) {
            if($data["event"] != "created"){
                $data['old_values']['Número de notaría'] = $this->getOriginal('numero_notaria');
                unset($data['old_values']["numero_notaria"]);
            }
            $data['new_values']['Número de notaría'] = $this->getAttribute('numero_notaria');
            unset($data['new_values']["numero_notaria"]);
        }
//        Validamos la localidad_notaria
        if (Arr::has($data, 'new_values.localidad_notaria')) {
            if($data["event"] != "created"){
                $data['old_values']['Localidad de notaría'] = $this->getOriginal('localidad_notaria');
                unset($data['old_values']["localidad_notaria"]);
            }
            $data['new_values']['Localidad de notaría'] = $this->getAttribute('localidad_notaria');
            unset($data['new_values']["localidad_notaria"]);
        }
//        Validamos el nombre_notario
        if (Arr::has($data, 'new_values.nombre_notario')) {
            if($data["event"] != "created"){
                $data['old_values']['Nombre del notario'] = $this->getOriginal('nombre_notario');
                unset($data['old_values']["nombre_notario"]);
            }
            $data['new_values']['Nombre del notario'] = $this->getAttribute('nombre_notario');
            unset($data['new_values']["nombre_notario"]);
        }
//        Validamos la parte_representada
        if (Arr::has($data, 'new_values.parte_representada_id')) {
            if($data["event"] != "created"){
                $parteOld = Parte::find($this->getOriginal('parte_representada_id'));
                if($parteOld->tipo_parte == 1){
                    $nombreOld = $parteOld->nombre." ".$parteOld->primer_apellido." ".$parteOld->segundo_apellido;
                }else{
                    $nombreOld = $parteOld->nombre_comercial;
                }
                $data['old_values']['Parte representada'] = $nombreOld;
                unset($data['old_values']["parte_representada_id"]);
            }
            $parteNew = Parte::find($this->getAttribute('parte_representada_id'));
            if($parteNew->tipo_parte == 1){
                $nombreNew = $parteNew->nombre." ".$parteNew->primer_apellido." ".$parteNew->segundo_apellido;
            }else{
                $nombreNew = $parteNew->nombre_comercial;
            }
            $data['new_values']['Parte representada'] = $nombreNew;
            unset($data['new_values']["parte_representada_id"]);
        }
//        Validamos el grupo_prioritario
        if (Arr::has($data, 'new_values.grupo_prioritario_id')) {
            if($this->getAttribute('grupo_prioritario_id') != null){
                if($data["event"] != "created"){
                    if($this->getOriginal('grupo_prioritario_id') != null){
                        $data['old_values']['Grupo prioritario'] = GrupoPrioritario::find($this->getOriginal('grupo_prioritario_id'))->name;
                    }else{
                        $data['old_values']['Grupo prioritario'] = GrupoPrioritario::find($this->getAttribute('grupo_prioritario_id'))->name;
                    }
                    unset($data['old_values']["grupo_prioritario_id"]);
                }
                $data['new_values']['Grupo prioritario'] = GrupoPrioritario::find($this->getAttribute('grupo_prioritario_id'))->name;
                unset($data['new_values']["grupo_prioritario_id"]);
            }
        }
//        Validamos el solicita_traductor
        if (Arr::has($data, 'new_values.solicita_traductor')) {
            if($data["event"] != "created"){
                if($this->getOriginal('solicita_traductor') != ""){
                    $data['old_values']['Solicita traductor'] = $this->validBool($this->getOriginal('solicita_traductor'));
                }
                unset($data['old_values']["solicita_traductor"]);
            }
            $data['new_values']['Solicita traductor'] = $this->validBool($this->getAttribute('solicita_traductor'));
            unset($data['new_values']["solicita_traductor"]);
        }
//        Validamos la lengua_indigena
        if (Arr::has($data, 'new_values.lengua_indigena_id')) {
            if($this->getOriginal('lengua_indigena_id') != null){
                if($data["event"] != "created"){
                    $data['old_values']['Lengua indigena'] = LenguaIndigena::find($this->getOriginal('lengua_indigena_id'))->name;
                    unset($data['old_values']["lengua_indigena_id"]);
                }
                
                $data['new_values']['Lengua indigena'] = LenguaIndigena::find($this->getAttribute('lengua_indigena_id'))->name;
                unset($data['new_values']["lengua_indigena_id"]);
            }
        }
        $data = $this->cambiarEvento($data);
        return $data;
    }
    
     public function generateTags(): array
    {
        return [
            $this->lenguaIndigena->name,
        ];
    }
    
    
    
    /**
     * Funcion para asociar con modelo Genero
     * Utilizando belongsTo para relaciones 1 a 1
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function Genero(){
      return $this->belongsTo('App\Genero');
    }

    /**
     * Funcion para asociar con modelo Solicitud
     * Utilizando belongsTo para relaciones 1 a 1
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function solicitud(){
      return $this->belongsTo('App\Solicitud');
    }
    /**
     * Funcion para asociar con modelo TipoParte
     * Utilizando belongsTo para relaciones 1 a 1
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function tipoParte(){
      return $this->belongsTo('App\TipoParte');
    }
    /**
     * Funcion para asociar con modelo TipoPersona
     * Utilizando belongsTo para relaciones 1 a 1
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function tipoPersona(){
      return $this->belongsTo('App\TipoPersona');
    }
    /**
     * Funcion para asociar con modelo Nacionalidad
     * Utilizando belongsTo para relaciones 1 a 1
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function nacionalidad(){
      return $this->belongsTo('App\Nacionalidad');
    }
    /**
     * Funcion para asociar con modelo Estado
     * Utilizando belongsTo para relaciones 1 a 1
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function entidadNacimiento(){
      return $this->belongsTo('App\Estado');
    }
    /**
     * Funcion para asociar con modelo Estado
     * Utilizando belongsTo para relaciones 1 a 1
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function grupoPrioritario(){
      return $this->belongsTo('App\GrupoPrioritario');
    }
    /**
     * Funcion para asociar con modelo Audiencia con hasMany
     * * Utilizando hasMany para relacion uno a muchos
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function audiencias(){
      return $this->hasMany('App\Audiencia');
    }
     /**
     * Dato laboral tiene relacion 
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function dato_laboral()
    {
        return $this->hasMany(DatoLaboral::class);
    }
    /**
     * Relacion con la tabla domicilio
     * @return type
     */
    public function domicilios(){
      return $this->morphMany(Domicilio::class,'domiciliable');
    }
    /**
     * Relacion con la tabla domicilio
     * @return type
     */
    public function contactos(){
      return $this->morphMany(Contacto::class,'contactable');
    }
    /**
     * Funcion para asociar con modelo Estado
     * Utilizando belongsTo para relaciones 1 a 1
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function lenguaIndigena(){
      return $this->belongsTo('App\LenguaIndigena')->withDefault();
    }
    /**
     * Funcion para asociar con modelo tipoDiscapacidad
     * Utilizando belongsTo para relaciones 1 a 1
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function tipoDiscapacidad(){
      return $this->belongsTo(TipoDiscapacidad::class)->withDefault();
    }
    /**
     * Funcion para asociar con modelo tipoDiscapacidad
     * Utilizando belongsTo para relaciones 1 a 1
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function compareciente(){
      return $this->hasMany(Compareciente::class);
    }
    /**
     * Relación con audienciaParte
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function audienciaParte(){
        return $this->hasMany(AudienciaParte::class);
    }
    
    public function documentos(){
        return $this->morphMany(Documento::class,'documentable');
    }
    
    public function firmas(){
        return $this->morphMany(FirmaDocumento::class,'firmable');
    }
    /**
     * Get all of the bitacora_buzones for the Parte
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function bitacoras_buzon(): HasMany
    {
        return $this->hasMany(BitacoraBuzon::class, 'parte_id', 'id');
    }

    public function getDocumentosFirmarAttribute()
    {
        return Documento::whereHas('firma_documentos',function($q){ $q->where('firmable_type','App\Parte')->where('firmable_id',$this->id)->where('firma_electronicamente',true); })->get();    
    }

}
