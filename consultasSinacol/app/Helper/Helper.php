<?php

namespace App\Helper;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Helper
{
    ///Función que se crea para generar la contraseña
    ///Se obtimiza código con esta funcón ya que se utiliza en diferentes lugares
    ///MigrarPartesUsers, SolicitudController, GenerateDocument
    public function getPassword()
    {
        return str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    public function generarUrlQR($uuid, $token)
    {
        $_uid = $uuid;
        $_token = $token;
        if (! isset($uuid)) {
            $_uid = '00000000-0000-0000-0000-000000000000';
            $_token = '00000000000000000000000000000';
        }

        return env('APP_URL').'/api/documentos/getFile/'.$_uid.'/'.$_token;
    }

    public function mensajeError($e)
    {
        Log::error('En script:'.$e->getFile().' En línea: '.$e->getLine().
            ' Se emitió el siguiente mensaje: '.$e->getMessage().
            ' Con código: '.$e->getCode().' La traza es: '.$e->getTraceAsString());
    }

    public function fechaUltimaAudiencia($solicitud)
    {
        $response = null;
        $ultimaAudiencia = $solicitud->expediente->audiencia()->whereNotNull('fecha_audiencia')->orderBy('created_at', 'DESC')->first();
        if ($ultimaAudiencia) {
            $response = ['id' => $ultimaAudiencia->id, 'fecha_audiencia' => $ultimaAudiencia->fecha_audiencia, 'hora_inicio' => (new Carbon($ultimaAudiencia->hora_inicio))->format('H:i'), 'hora_fin' => (new Carbon($ultimaAudiencia->hora_fin))->format('H:i'), 'finalizada' => $ultimaAudiencia->finalizada ? '1' : 0];
        }

        return $response;
    }

    public static function resultadoFechaPropuesta($fechaUltimaAudiencia, $centro_id, $minimo_dia_habil, $maximo_dia_habil)
    {
        $resultados = DB::select('SELECT * FROM calcular_periodo_general(?, ?, ?, ?, ?, ?)', [$fechaUltimaAudiencia, $centro_id, $minimo_dia_habil, $maximo_dia_habil, env("DIAS_CALCULAR_PERIODO_GENERAL", 45), 'habiles']);
        $fecha_minima = Carbon::parse($resultados[0]->fecha_minima);
        $fecha_maxima = Carbon::parse($resultados[0]->fecha_max);
        $fecha_audiencia = Carbon::parse($resultados[0]->fecha_minima);

        return [$fecha_minima, $fecha_maxima, $fecha_audiencia];
    }

    public static function parseDate($date)
    {
        $formats = ['d/m/Y', 'Y/m/d', 'Y-m-d', 'd-m-Y'];
        foreach ($formats as $format) {
            try {
                return Carbon::createFromFormat($format, $date)->format('d/m/Y');
            } catch (\Exception $e) {
            }
        }

        return $date;
    }

    public static function formatDate($date)
    {
        return Carbon::parse($date)->format('d-m-Y');
    }

    /**
     * Obtiene el tamaño máximo de archivo permitido para subir
     *
     * @return int Tamaño máximo en bytes
     */
    public static function getMaximoFileSize()
    {
        $value = ini_get('upload_max_filesize');

        $value = trim($value);

        $unit = strtolower($value[strlen($value) - 1]);

        if (in_array($unit, ['k', 'm', 'g'])) {
            // Obtener el número
            $number = substr($value, 0, -1);
        } else {
            $number = $value;
            $unit = 'b';
        }

        switch ($unit) {
            case 'k':
                return $number * 1024;
            case 'm':
                return $number * 1024 * 1024;
            case 'g':
                return $number * 1024 * 1024 * 1024;
            default:
                return $number;
        }
    }

    public static function domiciliosTransformer($domicilio)
    {

        $dom = [];
        $segmento_calle = [];
        if ($domicilio->tipo_vialidad) {
            $segmento_calle[] = $domicilio->tipo_vialidad;
        }
        $segmento_calle[] = $domicilio->tipo_vialidad.' '.$domicilio->vialidad;
        $segmento_calle[] = $domicilio->num_ext;

        $dom[] = preg_replace('/\b(\w+)\b\s+\b\1\b/', '$1', implode(' ', $segmento_calle));

        if ($domicilio->num_int) {
            $dom[] = $domicilio->num_int;
        }

        if ($domicilio->asentamiento) {
            $dom[] = $domicilio->asentamiento;
        }
        if ($domicilio->cp && $domicilio->cp != '00000') {
            $dom[] = 'CP '.$domicilio->cp;
        }
        if ($domicilio->estado_id == '09') {
            if ($domicilio->municipio) {
                $dom[] = 'ALCALDÍA '.$domicilio->municipio;
            }
        } else {
            if ($domicilio->municipio) {
                $dom[] = 'MUNICIPIO '.$domicilio->municipio;
            }
        }

        // Cuando se trata de ciudad de méxico no se agrega la palabra "ESTADO"
        $cdmx = [
            'CIUDAD DE MÉXICO', 'CDMX', 'CIUDAD DE MEXICO', 'CD MEXICO', 'CD MÉXICO',
        ];

        if ($domicilio->estado && ($domicilio->estado_id != '09' || ! in_array(mb_strtoupper($domicilio->estado), $cdmx))) {
            $dom[] = $domicilio->estado;
        } else {
            $dom[] = $domicilio->estado;
        }

        return mb_strtoupper(implode(', ', $dom));
    }
}
