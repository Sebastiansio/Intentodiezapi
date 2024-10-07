<?php
namespace App\Models;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use OwenIt\Auditing\Contracts\Auditable as AuditableContract;
use OwenIt\Auditing\Auditable;
use Carbon\Carbon;

class Incidencia extends Model implements AuditableContract
{
    use SoftDeletes,
        Auditable,
        \App\Traits\CambiarEventoAudit;
    protected $table = 'incidencias';
    protected $guarded = ['id','created_at','updated_at','deleted_at'];
    public function transformAudit($data):array
    {
        $data = $this->cambiarEvento($data);
        return $data;
    }
    /*
     *  funcion que indica que es una relación polimorfica
     *  incidenciable puede ser usado por Conciliadores, Salas y centros
     */
    public function incidenciable()
    {
        return $this->morphTo();
    }
    
    
    
    
    /**
     * Regresa si es hay incidencia o no
     * @param $fecha timestamp
     * @return bool
     * @throws \Exception
     */
    public static function hayIncidencia($fecha,$id,$incidencia_type)
    {
        $d = new Carbon($fecha);
        if($d->isWeekend()){
            return true;
        }
        $fechaInicioEv = $fecha." 00:00:00";
        $incidencia = self::whereDate("fecha_inicio","<=",$fechaInicioEv)->whereDate("fecha_fin",">=",$fechaInicioEv)->where("incidenciable_type",$incidencia_type)->where("incidenciable_id",$id)->get();
        if(count($incidencia) > 0){
            return true;
        }else{
            $numero_dia = $d->weekday();
            $pasa = true;
            switch ($incidencia_type){
                case "App\Centro":
                    $disponibilidades = Centro::find($id)->disponibilidades()->get();
                break;
                case "App\Sala":
                    $disponibilidades = Sala::find($id)->disponibilidades()->get();
                break;
            }
            foreach($disponibilidades as $disponibilidad){
                if($disponibilidad->dia == $numero_dia){
                    $pasa = false;
                }
            }
            return $pasa;
        }
    }

    /**
     * Regresa el ultimo día hábil desde la fecha dada como referencia.
     * @param $fecha
     * @return mixed
     * @throws \Exception
     */
    public static function ultimoDiaHabilDesde($fecha){

        $d = new Carbon($fecha);
        $ayer = $d->subDay()->format("Y-m-d H:i:s");

        if(self::hayIncidencia($ayer)){
            $d = new Carbon($fecha);
            $ayer = $d->subDay()->format("Y-m-d H:i:s");
            return self::ultimoDiaHabilDesde($ayer);
        }
        else{
            return $ayer;
        }
    }

    /**
     * @param $fecha
     * @return mixed|string|void
     * @throws \Exception
     */
    public static function siguienteDiaHabil($fecha,$id,$incidencia_type,$max)
    {
        $d = new Carbon($fecha);
        $fecha = $d->addDay()->format("Y-m-d");
        if($max >= 0){
            if(self::hayIncidencia($fecha,$id,$incidencia_type)){
//                $max--;
                $d = new Carbon($fecha);
                $maniana = $d->format("Y-m-d");
                return self::siguienteDiaHabil($maniana,$id,$incidencia_type,$max);
            }
            else{
                return array("dia" => $fecha,"max" => $max);
            }
        }else{
            return array("dia" => "nada");
        }
    }

    public static function siguienteDiaHabilMasDias($fecha,$id,$incidencia_type, $dias,$max)
    {
        $d = new Carbon($fecha);
        $diasRecorridos = 1;
        while ($diasRecorridos < $dias){
            $sig = $d->addDay()->format("Y-m-d");
            if(!self::hayIncidencia($sig,$id,$incidencia_type)){
                $d = new Carbon($sig);
                $diasRecorridos++;
            }
        }
        $max = $max - $dias;
        return self::siguienteDiaHabil($d,$id,$incidencia_type,$max);
    }


    public static function disponibilidadRegistrada($id, $incidencia_type, $centro_disponibilidad)
    {
        switch ($incidencia_type){
            case "App\Centro":
                //Se obtiene la disponibilidad del centro
                return Centro::find($id)->disponibilidades()->select("dia", "hora_inicio", "hora_fin")->get();
            break;
            case "App\Sala":
                //Se obtiene la disponibilidad de las salas de acuerdo a los disponibles del centro
                return Sala::find($id)->disponibilidades()->whereIn('dia', collect($centro_disponibilidad)->pluck('dia')->unique()->values()->toArray())->select("dia", "hora_inicio", "hora_fin")->get();
            break;
            case "App\Conciliador":
                //Se obtiene la disponibilidad del conciliador
                return Conciliador::find($id)->disponibilidades()->whereIn('dia', collect($centro_disponibilidad)->pluck('dia')->unique()->values()->toArray())->select("dia", "hora_inicio", "hora_fin")->get();
            break;
        }

    }
}
