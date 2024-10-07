<?php

namespace App\Traits;


use App\TipoParte;

trait Transformer
{
    /**
     * Transforma los datos de las partes
     * @param $datos
     * @param $parte
     * @param bool $domicilio
     * @return array
     */
    public function partesTransformer($datos, $parte, $domicilio = false)
    {
        $parteCat = TipoParte::where('nombre', 'ilike', $parte)->first();
        $persona =  $datos->where('tipo_parte_id', $parteCat->id)->first();

        $resultado = [];
        if($persona->tipoPersona->abreviatura == 'F'){
            $resultado = [
                'nombre' => $persona->nombre,
                'primer_apellido' => $persona->primer_apellido,
                'segundo_apellido' => $persona->segundo_apellido,
                'rfc' => $persona->rfc,
                'curp' => $persona->curp,
                'caracter_persona' => $persona->tipoPersona->nombre,
                'caracter_persona_id' => $persona->tipo_persona_id,
                'domicilios' => $this->domiciliosTransformer($persona->domicilios)
            ];
        }
        if($persona->tipoPersona->abreviatura == 'M'){
            $resultado = [
                'denominacion' => $persona->nombre_comercial,
                'rfc' => $persona->rfc,
                'caracter_persona' => $persona->tipoPersona->nombre,
                'caracter_persona_id' => $persona->tipo_persona_id,
                'domicilios' => $this->domiciliosTransformer($persona->domicilios)
            ];
        }
        if(!$domicilio){
            unset($resultado['domicilios']);
        }
        return $resultado;
    }

    /**
     * Transforma los datos que se van a reportar de domicilios
     * @param $datos
     * @return array
     */
    public function domiciliosTransformer($datos)
    {
        $domicilios = [];
        foreach($datos as $domicilio){
            $domicilios[] = [
                'tipo_vialidad' => $domicilio->tipo_vialidad,
                'tipo_vialidad_id' => $domicilio->tipo_vialidad_id,
                'vialidad' => $domicilio->vialidad,
                'num_ext' => $domicilio->num_ext,
                'num_int' => $domicilio->num_int,
                'tipo_asentamiento' => $domicilio->tipo_asentamiento,
                'tipo_asentamiento_id' => $domicilio->tipo_asentamiento_id,
                'asentamiento' => $domicilio->asentamiento,
                'municipio' => $domicilio->municipio,
                'estado' => $domicilio->estado,
                'estado_id' => $domicilio->estado_id,
                'cp' => $domicilio->cp,
                'latitud' => $domicilio->latitud,
                'longitud' => $domicilio->longitud,
                'entre_calle1' => $domicilio->entre_calle1,
                'entre_calle2' => $domicilio->entre_calle2,
                'referencias' => $domicilio->referencias,
            ];
        }
        return $domicilios;
    }

}
