<?php

namespace App\Services;

use App\Audiencia;
use App\TipoPersonaConfianza;
use DB;

class PersonaConfianzaService
{
    /**
     * Obtiene los datos de las personas de confianza
     */
    public function get(int $audiencia_id)
    {
        $datosPersonaConfianza = DB::table('persona_confianza as pc')
            ->leftJoin('cat_tipo_persona_confianza as cpc', 'cpc.id', '=', 'pc.tipo_persona_confianza_id')
            ->leftJoin('partes as p', 'pc.parte_id', '=', 'p.id')
            ->select([
                'pc.id',
                'pc.parte_id',
                'pc.tipo_persona_confianza_id',
                'p.nombre as nombre_parte',
                'p.primer_apellido as primer_apellido_parte',
                'p.segundo_apellido as segundo_apellido_parte',
                'p.nombre_comercial',
                'p.tipo_persona_id',
                'cpc.dependencia as dependencia',
                'pc.nombre',
                'pc.apellido_paterno',
                'pc.apellido_materno',
                'pc.curp',
            ])
            ->where('pc.audiencia_id', $audiencia_id)
            ->get();

        $objDatosPersonaConfianza = [];

        foreach ($datosPersonaConfianza as $pc) {

            $obj = new \StdClass;

            $obj->id = $pc->id;
            $obj->parte_id = $pc->parte_id;

            if ($pc->tipo_persona_id == 1) {
                $obj->nombre_compareciente = $pc->nombre_parte.' '.$pc->primer_apellido_parte.' '.$pc->segundo_apellido_parte;
            } else {
                $obj->nombre_compareciente = $pc->nombre_comercial;
            }

            $obj->nombre = $pc->nombre.' '.$pc->apellido_paterno.' '.$pc->apellido_materno;
            $obj->dependencia = $pc->dependencia;
            $obj->curp = ($pc->curp) ? $pc->curp : '';

            $obj->tipo_persona_confianza_id = $pc->tipo_persona_confianza_id;
            $obj->pc_nombre = $pc->nombre;
            $obj->pc_apellido_paterno = $pc->apellido_paterno;
            $obj->pc_apellido_materno = ($pc->apellido_materno) ? $pc->apellido_materno : '';

            $objDatosPersonaConfianza[] = $obj;
        }

        return $objDatosPersonaConfianza;
    }

    public function partesSinPersonaConfianza($audiencia_id)
    {
        $audiencia = Audiencia::find($audiencia_id);
        $partes = [];

        foreach ($audiencia->audienciaParte as $key => $parte) {
            $parte->parte->tipoParte = $parte->parte->tipoParte;
            $partes[$key] = $parte->parte;
        }

        $objPartesSinPersonaConfianza = [];

        foreach ($partes as $parte) {

            $tienePersonaConfianza = DB::table('persona_confianza')
                ->where('audiencia_id', $audiencia_id)
                ->where('parte_id', $parte->id)
                ->exists();

            if ($tienePersonaConfianza || $parte->tipo_persona_id == 3 || $parte->tipo_parte_id == 3) {
                continue;
            }

            $obj = new \StdClass;
            $obj->id = $parte->id;

            if ($parte->tipo_persona_id == 1) {
                $nombreParte = $parte->nombre.' '.$parte->primer_apellido.' '.$parte->segundo_apellido;
            } else {
                $nombreParte = $parte->nombre_comercial;
            }

            $obj->nombre = $parte->tipoParte->nombre.' | '.$nombreParte;

            $objPartesSinPersonaConfianza[] = $obj;
        }

        return $objPartesSinPersonaConfianza;
    }

    /**
     * Obtiene los tipos de persona de confianza
     */
    public function getTiposPersonaConfianza()
    {
        return TipoPersonaConfianza::select([
            'id',
            'dependencia',
        ])
            ->get();
    }
}
