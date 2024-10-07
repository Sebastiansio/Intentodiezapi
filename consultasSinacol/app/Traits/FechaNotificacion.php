<?php
namespace App\Traits;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Domicilio;
trait FechaNotificacion{
    public static function obtenerFechaLimiteNotificacion(Domicilio $centro = null,Domicilio $domicilioCitado = null,$fecha_audiencia = null){
        if($centro != null){
    //        Obtenemos la latitud del centro
            $lat_centro = $centro->latitud;
            $lon_centro = $centro->longitud;
            $lat_citado = $domicilioCitado->latitud;
            $lon_citado = $domicilioCitado->longitud;            
        }else{
            $lat_centro = 19.3137542;
            $lon_centro = -99.6386443;
            $lat_citado = 24.852421;
            $lon_citado = -102.294305;
            $fecha_audiencia = "2020/10/16";
        }
        $sql = "select (point(".$lon_centro.",".$lat_centro.") <@> point(".$lon_citado.",".$lat_citado.")) as distancia";
        $cons = DB::select($sql);
        $con = (int)$cons[0]->distancia;
        if($con < 200){
            $dias = 5;
        }else if($con < 400){
            $dias = 8;
        }else if($con < 600){
            $dias = 9;
        }else if($con < 800){
            $dias = 10;
        }else if($con < 1000){
            $dias = 11;
        }else{
            $dias =12;
        }
        $fecha = self::ultimoDiaHabilMenosDias($fecha_audiencia, $dias);
        return $fecha;
    }
    public static function ultimoDiaHabilDesde($fecha){

        $d = new Carbon($fecha);
        $ayer = $d->subDay()->format("Y-m-d");
        if(self::esFeriado($ayer)){
            $d = new Carbon($fecha);
            $ayer = $d->subDay()->format("Y-m-d");
            return self::ultimoDiaHabilDesde($ayer);
        }
        else{
            return $ayer;
        }
    }
    public static function ultimoDiaHabilMenosDias($fecha,$dias){
        $fecha = new Carbon($fecha);
        $diasRecorridos = 1;
        while ($diasRecorridos < $dias){
            $ayer = $fecha->subDay()->format("Y-m-d");
            if(!self::esFeriado($ayer)){
                $fecha = new Carbon($ayer);
                $diasRecorridos++;
            }
        }
        return self::ultimoDiaHabilDesde($fecha);
    }
    public static function esFeriado($fecha)
    {
        $d = new Carbon($fecha);
        if($d->isWeekend()){
            return true;
        }
        return false;

//        return Feriado::whereRaw("(fecha)::date = ?",[$fecha])
//            ->first() ? true : false;

    }
}
