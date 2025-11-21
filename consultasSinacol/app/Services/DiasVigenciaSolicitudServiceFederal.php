<?php

namespace App\Services;

use App\Solicitud;
use Carbon\Carbon;

/**
 * Provee metodos para validar si una solicitud aun se puede operar
 * Class DiasVigenciaSolicitudServiceFederal
 */
class DiasVigenciaSolicitudServiceFederal implements DiasVigenciaSolicitudService
{
    /**
     * Funcion para validar si una solicitud aun se puede operar
     */
    public function getSolicitudVigente($solicitud_id, $fecha_solicitada)
    {
        $solicitud = Solicitud::find($solicitud_id);
        $dias = 1;
        if ($solicitud->tipo_solicitud_id == 1) {
            $dt = new Carbon($solicitud->created_at);
            $dt2 = new Carbon($fecha_solicitada);
            $dias = $dt->diffInDays($dt2);
        }
        if ($dias > env('DIAS_VIGENCIA_SOLICITUD_FEDERAL', 45)) {
            return false;
        }

        return true;
    }
}
