<?php

namespace App\Helper;

use App\Persona;
use App\Solicitud;
use App\User;
use Exception;

class SolicitudHelper
{
    public static function updateStatusSolicitud($solicitud, $user, $status)
    {
        $nombre_completo = self::getNombreCompleto($solicitud);
        try {
            $modified_user_id = $user->id;

            if ($status == 'cancelar_confirmar') {
                if ($solicitud->modified_user_id == $modified_user_id) {
                    self::handleSolicitud($solicitud, 'sin_confirmar', null);
                }

                return ['valido' => true, 'mensaje' => false, 'nombre' => $nombre_completo];
            }
            if (($solicitud->code_estatus != 'sin_confirmar' && $solicitud->modified_user_id != $modified_user_id) || $solicitud->ratificada) {
                return ['valido' => false, 'mensaje' => true, 'nombre' => $nombre_completo];

            }

            self::handleSolicitud($solicitud, $status, $modified_user_id);

            return ['valido' => true, 'mensaje' => true, 'nombre' => $nombre_completo];
        } catch (Exception $e) {
            return ['valido' => false, 'mensaje' => true, 'nombre' => $nombre_completo];
        }
    }

    private static function getNombreCompleto($solicitud)
    {
        if (isset($solicitud->modified_user_id)) {
            $user_persona_id = User::where('id', $solicitud->modified_user_id)->first('persona_id')->persona_id;
            $get_user = Persona::where('id', $user_persona_id)->first();

            return $get_user->nombre.' '.$get_user->primer_apellido.' '.$get_user->segundo_apellido;
        }

        return '';
    }

    private static function handleSolicitud($solicitud, $status, $modified_user_id)
    {
        Solicitud::find($solicitud->id)->update(['code_estatus' => $status, 'modified_user_id' => $modified_user_id]);
    }
}
