<?php

namespace App\Services;

use App\Contador;

class ContadorServiceFederal implements ContadorService
{
    /**
     * {@inheritDoc}
     */
    public function getContador($anio, $tipo_contador_id, $centro_id)
    {
        $contador = Contador::where('anio', $anio)
            ->where('tipo_contador_id', $tipo_contador_id)
            ->where('centro_id', $centro_id)->first();
        if ($contador) {
            $contador->contador = $contador->contador + 1;
            $contador->save();
        } else {
            $contador = Contador::create(
                [
                    'anio' => $anio,
                    'tipo_contador_id' => $tipo_contador_id,
                    'centro_id' => $centro_id,
                    'contador' => 0,
                ]
            );
            $contador->contador = $contador->contador + 1;
            $contador->save();
        }

        return $contador->contador;
    }
}
