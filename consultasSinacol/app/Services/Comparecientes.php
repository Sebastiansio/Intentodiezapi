<?php

namespace App\Services;

use App\Compareciente;
use App\Parte;

class Comparecientes
{
    /**
     * Indica si compareció la parte a la audiencia dada
     */
    public static function comparecio($audiencia_id, $parte_id)
    {

        // Para saber si compareció una persona moral, necesitamos saber si su representante legal compareció.

        $parte = Parte::find($parte_id);

        if (! $parte) {
            return false;
        }

        // Si se encuentra la parte en la tabla de comparecencias regresamos true
        if ($audiencia_id == '' || $parte_id == '') {
            return false;
        }

        if ((bool) Compareciente::where('audiencia_id', $audiencia_id)->where('parte_id', $parte_id)->exists()) {
            return true;
        }

        // Si no se encontró la parte en la tabla comparecencias, entonces buscamos si la parte comparece mediante representante
        return self::compareceRepresentado($audiencia_id, $parte);
    }

    /**
     * Si la parte comparece mediante representante, regresa true, false de no encontrar parte representante
     */
    public static function compareceRepresentado($audiencia_id, $parte)
    {

        return (bool) Compareciente::where('audiencia_id', $audiencia_id)
            ->whereHas('parte', function ($q) use ($parte, $audiencia_id) {
                $q->where('parte_representada_id', $parte->id)
                    ->whereHas('audienciaParte', function ($w) use ($audiencia_id) {
                        $w->where('audiencia_id', $audiencia_id);
                    });
            })
            ->where('presentado', true)
            ->exists();
    }
}
