<?php

namespace App\Services;

use ErrorException;
use Exception;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Str;
use Symfony\Component\Debug\Exception\FatalThrowableError;
use Throwable;

class StringTemplate
{
    /**
     * Traduce a HTML una plantilla blade pasada como cadena
     *
     * @param  $string  string Plantilla blade en una cadena
     * @param  $vars  array Variables que se van a sustituir en la plantilla
     *
     * @throws Exception
     * @throws FatalThrowableError
     */
    public static function render($string, $vars)
    {
        $php = Blade::compileString($string);
        $obLevel = ob_get_level();
        ob_start();
        extract($vars, EXTR_SKIP);
        try {
            eval('?'.'>'.$php);
        } catch (ErrorException $err) {
            //dd($err);
        } catch (Exception $e) {
            while (ob_get_level() > $obLevel) {
                ob_end_clean();
            }
            throw $e;
        } catch (Throwable $e) {
            while (ob_get_level() > $obLevel) {
                ob_end_clean();
            }
            throw new FatalThrowableError($e);
        }

        return ob_get_clean();
    }

    /**
     * Sustituye los placeholders del tipo: <strong class="mceNonEditable" var="variable">[--equis-texto--]</strong>
     * por {{$variable}}
     */
    public static function sustituyePlaceholders($string)
    {
        $string = preg_replace('/\[ESPACIO_FIRMA\]/', '&nbsp;&nbsp;', $string);
        $blade = preg_replace(
            '/<strong class="mceNonEditable" data-nombre="(\w+)">\[([\\p{L}_ &;]+)\]<\/strong>/i',
            '<strong>{!! \$$1 !!}</strong>',
            $string
        );

        return $blade;
    }

    /**
     * Sustituye los placeholders del tipo: <strong class="mceNonEditable" var="variable">[--equis-texto--]</strong>
     * por {{$variable}}
     */
    public static function sustituyePlaceholdersConditionals($string, $vars)
    {
        //dd($vars);
        if (Str::contains($string, '[REPETIR')) {
            $countRepetir = substr_count($string, '[FIN_REPETIR');
        }
        if (Str::contains($string, '[SI_')) {
            $countSi = substr_count($string, '[FIN_SI');

            $countHayPagos = substr_count($string, '[SI_RESOLUCION_PAGOS]');
            if (isset($vars['resolucion_pagos']) && $countHayPagos > 0) {
                for ($i = 0; $i < $countHayPagos; $i++) {
                    $htmlA = Str::before($string, '[SI_RESOLUCION_PAGOS]');
                    $htmlB = Str::after($string, '[FIN_SI_RESOLUCION_PAGOS]');
                    if ($vars['resolucion_pagos'] == 'Si') { //resolucion tiene pagos
                        // texto para pagos en resolucion
                        $sliceHayPagos = Str::after($string, '[SI_RESOLUCION_PAGOS]');
                        $sliceHayPagos = Str::before($sliceHayPagos, '[FIN_SI_RESOLUCION_PAGOS]');
                        // dd($sliceHayPagos);
                        $string = $htmlA.$sliceHayPagos.$htmlB;
                    } else { //solicitud no individual
                        $string = $htmlA.$htmlB;
                    }
                }
            }

            $countPagosDiferidos = substr_count($string, '[SI_RESOLUCION_PAGO_DIFERIDO]');
            if (isset($vars['resolucion_total_diferidos'])) {
                if ($countPagosDiferidos > 0) {
                    for ($i = 0; $i < $countPagosDiferidos; $i++) {
                        if ($vars['resolucion_total_diferidos'] > 0) { // Hay pagos diferidos
                            // texto de pagos diferidos
                            $sliceDiferido = Str::after($string, '[SI_RESOLUCION_PAGO_DIFERIDO]');
                            $sliceDiferido = Str::before($sliceDiferido, '[SI_RESOLUCION_PAGO_NO_DIFERIDO]');
                            $htmlA = Str::before($string, '[SI_RESOLUCION_PAGO');
                            $htmlB = Str::after($string, '[FIN_SI_RESOLUCION_PAGO]');

                            $string = $htmlA.$sliceDiferido.$htmlB;
                        } else { //Sin pagos diferidos
                            $sliceDiferido = Str::after($string, '[SI_RESOLUCION_PAGO_NO_DIFERIDO]');
                            $sliceDiferido = Str::before($sliceDiferido, '[FIN_SI_RESOLUCION_PAGO]');

                            $htmlA = Str::before($string, '[SI_RESOLUCION_PAGO_DIFERIDO');
                            $htmlB = Str::after($string, '[FIN_SI_RESOLUCION_PAGO]');

                            $string = $htmlA.$sliceDiferido.$htmlB;
                            // break;
                        }
                    }
                }
            }

            $countAudienciaSeparada = substr_count($string, '[SI_AUDIENCIA_POR_SEPARADO]');
            if (isset($vars['audiencia_multiple'])) {
                if ($vars['audiencia_multiple'] != null && $countAudienciaSeparada > 0) {
                    for ($i = 0; $i < $countAudienciaSeparada; $i++) {
                        if ($vars['audiencia_multiple'] == 'Si') { // Audiencia en salas diferentes
                            // texto de audiencia por separado
                            $sliceSeparado = Str::after($string, '[SI_AUDIENCIA_POR_SEPARADO]');
                            $sliceSeparado = Str::before($sliceSeparado, '[FIN_SI_AUDIENCIA_POR_SEPARADO]');
                            $htmlA = Str::before($string, '[SI_AUDIENCIA_POR_SEPARADO');
                            $htmlB = Str::after($string, '[FIN_SI_AUDIENCIA_POR_SEPARADO]');

                            $string = $htmlA.$sliceSeparado.$htmlB;
                        } else { //audiencia en misma sala
                            // texto de
                            $sliceSeparado = '';
                            $htmlA = Str::before($string, '[SI_AUDIENCIA_POR_SEPARADO');
                            $htmlB = Str::after($string, '[FIN_SI_AUDIENCIA_POR_SEPARADO]');

                            $string = $htmlA.$sliceSeparado.$htmlB;
                            // break;
                        }
                    }
                }
            }
            $countSolicitudRatificada = substr_count($string, '[SI_SOLICITUD_RATIFICADA]');
            if (isset($vars['solicitud_estatus_solicitud_id']) && $countSolicitudRatificada > 0) {
                for ($i = 0; $i < $countSolicitudRatificada; $i++) {
                    $htmlA = Str::before($string, '[SI_SOLICITUD_RATIFICADA');
                    $htmlB = Str::after($string, '[FIN_SI_SOLICITUD_RATIFICADA]');
                    if ($vars['solicitud_estatus_solicitud_id'] != 1) { //solicitud ratificada o termindada
                        // texto de datos de acceso a buzon
                        $sliceRatificada = Str::after($string, '[SI_SOLICITUD_RATIFICADA]');
                        $sliceRatificada = Str::before($sliceRatificada, '[SI_SOLICITUD_NO_RATIFICADA]');

                        $string = $htmlA.$sliceRatificada.$htmlB;
                    } else { //solicitud no ratificada
                        $sliceRatificada = Str::after($string, '[SI_SOLICITUD_NO_RATIFICADA]');
                        $sliceRatificada = Str::before($sliceRatificada, '[FIN_SI_SOLICITUD_RATIFICADA]');

                        $string = $htmlA.$sliceRatificada.$htmlB;
                    }
                }
            }
            $countSolicitudVirtual = substr_count($string, '[SI_SOLICITUD_VIRTUAL]');
            if (isset($vars['solicitud_virtual']) && $countSolicitudVirtual > 0) {
                for ($i = 0; $i < $countSolicitudVirtual; $i++) {
                    if ($vars['solicitud_virtual'] == 'Si') { //solicitud es virtual
                        $htmlA = Str::before($string, '[SI_SOLICITUD_VIRTUAL');
                        $htmlB = Str::after($string, '[FIN_SI_SOLICITUD_VIRTUAL]');
                        $sliceVirtual = Str::after($string, '[SI_SOLICITUD_VIRTUAL]');
                        $sliceVirtual = Str::before($sliceVirtual, '[SI_SOLICITUD_NO_VIRTUAL]');

                        $string = $htmlA.$sliceVirtual.$htmlB;
                    } elseif ($vars['solicitud_virtual'] == 'No') { //solicitud no virtual
                        $htmlA = Str::before($string, '[SI_SOLICITUD_VIRTUAL');
                        $htmlB = Str::after($string, '[FIN_SI_SOLICITUD_VIRTUAL]');
                        $sliceVirtual = Str::after($string, '[SI_SOLICITUD_NO_VIRTUAL]');
                        $sliceVirtual = Str::before($sliceVirtual, '[FIN_SI_SOLICITUD_VIRTUAL]');

                        $string = $htmlA.$sliceVirtual.$htmlB;
                    }
                }
            }
            $countSolicitudIndividual = substr_count($string, '[SI_SOLICITUD_TIPO_INDIVIDUAL]');
            if (isset($vars['solicitud_tipo_solicitud_id']) && $countSolicitudIndividual > 0) {
                for ($i = 0; $i < $countSolicitudIndividual; $i++) {
                    $htmlA = Str::before($string, '[SI_SOLICITUD_TIPO_INDIVIDUAL]');
                    $htmlB = Str::after($string, '[FIN_SI_SOLICITUD_TIPO]');
                    if ($vars['solicitud_tipo_solicitud_id'] == 1) { //solicitud individual
                        // texto para solicitud individual
                        $sliceIndividual = Str::after($string, '[SI_SOLICITUD_TIPO_INDIVIDUAL]');
                        $sliceIndividual = Str::before($sliceIndividual, '[FIN_SI_SOLICITUD_TIPO]');
                        $string = $htmlA.$sliceIndividual.$htmlB;
                    } else { //solicitud no individual
                        $string = $htmlA.$htmlB;
                    }
                }
            }
            $countCentroVirtual = substr_count($string, '[SI_CENTRO_ATIENDE_VIRTUAL]');
            if (isset($vars['centro_tipo_atencion_centro_id']) && $countCentroVirtual > 0) {
                for ($i = 0; $i < $countCentroVirtual; $i++) {
                    if ($vars['centro_tipo_atencion_centro_id'] == 1) { //centro atiende unicamente virtual
                        $htmlA = Str::before($string, '[SI_CENTRO_ATIENDE_VIRTUAL');
                        $htmlB = Str::after($string, '[FIN_SI_CENTRO_ATIENDE]');
                        $sliceCentroVirtual = Str::after($string, '[SI_CENTRO_ATIENDE_VIRTUAL]');
                        $sliceCentroVirtual = Str::before($sliceCentroVirtual, '[SI_CENTRO_NO_ATIENDE_VIRTUAL]');

                        $string = $htmlA.$sliceCentroVirtual.$htmlB;
                    } else { //centro atiende presencial y mixto
                        $htmlA = Str::before($string, '[SI_CENTRO_ATIENDE_VIRTUAL');
                        $htmlB = Str::after($string, '[FIN_SI_CENTRO_ATIENDE]');

                        $sliceCentroVirtual = Str::after($string, '[SI_CENTRO_NO_ATIENDE_VIRTUAL]');
                        $sliceCentroVirtual = Str::before($sliceCentroVirtual, '[FIN_SI_CENTRO_ATIENDE]');

                        $string = $htmlA.$sliceCentroVirtual.$htmlB;
                    }
                }
            }

            $countTipoNotificacion = substr_count($string, '[SI_SOLICITADO_NOTIFICACION_BUZON_COMPARECENCIA]');
            if (isset($vars['solicitado_tipo_notificacion'])) {
                if ($countTipoNotificacion > 0) {
                    if ($vars['solicitado_tipo_notificacion'] != null && $vars['solicitado_tipo_notificacion'] != '--') {
                        for ($i = 0; $i < $countTipoNotificacion; $i++) {
                            $htmlA = Str::before($string, '[SI_SOLICITADO_NOTIFICACION_BUZON_COMPARECENCIA');
                            $htmlB = Str::after($string, '[FIN_SI_SOLICITADO_NOTIFICACION]');
                            if ($vars['solicitado_tipo_notificacion'] == 4 || $vars['solicitado_tipo_notificacion'] == 7) { // Notificado por buzón electrónico o por comparecencia
                                $sliceNotificacion = Str::after($string, '[SI_SOLICITADO_NOTIFICACION_BUZON_COMPARECENCIA]');
                                $sliceNotificacion = Str::before($sliceNotificacion, '[SI_SOLICITADO_NOTIFICACION_NO_BUZON_COMPARECENCIA]');

                                $string = $htmlA.$sliceNotificacion.$htmlB;
                            } else { //otro tipo de notificacion

                                $sliceNotificacion = Str::after($string, '[SI_SOLICITADO_NOTIFICACION_NO_BUZON_COMPARECENCIA]');
                                $sliceNotificacion = Str::before($sliceNotificacion, '[FIN_SI_SOLICITADO_NOTIFICACION]');
                                $string = $htmlA.$sliceNotificacion.$htmlB;
                            }
                        }
                    } else {
                        $htmlA = Str::before($string, '[SI_SOLICITADO_NOTIFICACION_BUZON_COMPARECENCIA');
                        $htmlB = Str::after($string, '[FIN_SI_SOLICITADO_NOTIFICACION]');
                        $sliceNotificacion = '';
                        $string = $htmlA.$sliceNotificacion.$htmlB;
                    }
                }
            }

            $countTipoNotificacionExitosa = substr_count($string, '[SI_SOLICITANTE_NOTIFICA]');
            if (isset($vars['solicitado_tipo_notificacion'])) {
                if ($countTipoNotificacionExitosa > 0) {
                    if ($vars['solicitado_tipo_notificacion'] != null && $vars['solicitado_tipo_notificacion'] != '--') {
                        for ($i = 0; $i < $countTipoNotificacionExitosa; $i++) {
                            $htmlA = Str::before($string, '[SI_SOLICITANTE_N');
                            $htmlB = Str::after($string, '[FIN_SI_SOLICITANTE_NOTIFICA]');
                            switch ($vars['solicitado_tipo_notificacion']) {
                                case 1: // El solicitante entrega citatorio a solicitados
                                    // texto de notificacion por solicitante
                                    $sliceNotificacion = Str::after($string, '[SI_SOLICITANTE_NOTIFICA]');
                                    $sliceNotificacion = Str::before($sliceNotificacion, '[SI_NO_NOTIFICA]');

                                    $string = $htmlA.$sliceNotificacion.$htmlB;
                                    break;
                                default: //2 y 3
                                    // case 2: //El actuario del centro entrega citatorio a solicitados
                                    // texto de notificacion por actuario
                                    $sliceNotificacion = Str::after($string, '[SI_NO_NOTIFICA]');
                                    $sliceNotificacion = Str::before($sliceNotificacion, '[FIN_SI_SOLICITANTE_NOTIFICA]');
                                    $string = $htmlA.$sliceNotificacion.$htmlB;
                                    // default: // 3
                                    // $string = $htmlA . $htmlB;
                                    break;
                            }
                        }
                    } else {
                        $htmlA = Str::before($string, '[SI_SOLICITANTE_N');
                        $htmlB = Str::after($string, '[FIN_SI_SOLICITANTE_NOTIFICA]');
                        $sliceNotificacion = '';
                        $string = $htmlA.$sliceNotificacion.$htmlB;
                    }
                }
            }

            // $countTipoNotificacion = substr_count($string,'[SI_SOLICITADO_FUE_NOTIFICADO]');
            // if (isset($vars['solicitado_tipo_notificacion'])){
            //   if ($countTipoNotificacion >0 ){
            //     if($vars['solicitado_tipo_notificacion'] != null && $vars['solicitado_tipo_notificacion'] != "--"){
            //       for ($i=0; $i < $countTipoNotificacion; $i++) {
            //         $htmlA = Str::before($string, '[SI_SOLICITADO_FUE_NOTIFICADO');
            //         $htmlB = Str::after($string, '[FIN_SI_SOLICITADO_FUE_NOTIFICADO]');
            //         if($vars['solicitado_tipo_notificacion'] == 4 || $vars['solicitado_tipo_notificacion'] == 7) { // Notificado por buzón electrónico o por comparecencia
            //           $sliceNotificacion = Str::after($string, '[SI_SOLICITADO_FUE_NOTIFICADO]');
            //           $sliceNotificacion = Str::before($sliceNotificacion, '[SI_SOLICITADO_NO_FUE_NOTIFICADO]');

            //           $string = $htmlA . $sliceNotificacion . $htmlB;
            //         }else{ //otro tipo de notificacion

            //           $sliceNotificacion = Str::after($string, '[SI_SOLICITADO_NO_FUE_NOTIFICADO]');
            //           $sliceNotificacion = Str::before($sliceNotificacion, '[FIN_SI_SOLICITADO_FUE_NOTIFICADO]');
            //           $string = $htmlA . $sliceNotificacion . $htmlB;
            //         }
            //       }
            //     }else{
            //       $htmlA = Str::before($string, '[SI_SOLICITADO_FUE_NOTIFICADO');
            //       $htmlB = Str::after($string, '[FIN_SI_SOLICITADO_FUE_NOTIFICADO]');
            //       $sliceNotificacion = "";
            //       $string = $htmlA . $sliceNotificacion . $htmlB;
            //     }
            //   }
            // }

            $countComparecio = substr_count($string, '[SI_SOLICITADO_FUE_NOTIFICADO]');
            if (isset($vars['solicitado_finalizado'])) {
                if ($countComparecio > 0) {
                    if ($vars['solicitado_finalizado'] != null && $vars['solicitado_finalizado'] != '--') {
                        for ($i = 0; $i < $countComparecio; $i++) {
                            $htmlA = Str::before($string, '[SI_SOLICITADO_FUE_NOTIFICADO');
                            $htmlB = Str::after($string, '[FIN_SI_SOLICITADO_FUE_NOTIFICADO]');
                            if ($vars['solicitado_finalizado'] == 'Si') { // Notificado por buzón electrónico o por comparecencia
                                $sliceComparecio = Str::after($string, '[SI_SOLICITADO_FUE_NOTIFICADO]');
                                $sliceComparecio = Str::before($sliceComparecio, '[SI_SOLICITADO_NO_FUE_NOTIFICADO]');

                                $string = $htmlA.$sliceComparecio.$htmlB;
                            } else {
                                //otro tipo de notificacion

                                $sliceComparecio = Str::after($string, '[SI_SOLICITADO_NO_FUE_NOTIFICADO]');
                                $sliceComparecio = Str::before($sliceComparecio, '[FIN_SI_SOLICITADO_FUE_NOTIFICADO]');
                                $string = $htmlA.$sliceComparecio.$htmlB;
                            }
                        }
                    } else {
                        $htmlA = Str::before($string, '[SI_SOLICITADO_FUE_NOTIFICADO');
                        $htmlB = Str::after($string, '[FIN_SI_SOLICITADO_FUE_NOTIFICADO]');
                        $sliceComparecio = '';
                        $string = $htmlA.$sliceComparecio.$htmlB;
                    }
                }
            }

            $countComparecio = substr_count($string, '[SI_SOLICITADO_COMPARECIO]');
            if (isset($vars['solicitado_comparecio'])) {
                if ($countComparecio > 0) {
                    if ($vars['solicitado_comparecio'] != null && $vars['solicitado_comparecio'] != '--') {
                        for ($i = 0; $i < $countComparecio; $i++) {
                            $htmlA = Str::before($string, '[SI_SOLICITADO_COMPARECIO');
                            $htmlB = Str::after($string, '[FIN_SI_SOLICITADO_COMPARECIO]');
                            if ($vars['solicitado_comparecio'] == 'Si') { // Notificado por buzón electrónico o por comparecencia
                                $sliceComparecio = Str::after($string, '[SI_SOLICITADO_COMPARECIO]');
                                $sliceComparecio = Str::before($sliceComparecio, '[SI_SOLICITADO_NO_COMPARECIO]');

                                $string = $htmlA.$sliceComparecio.$htmlB;
                            } else {
                                //otro tipo de notificacion

                                $sliceComparecio = Str::after($string, '[SI_SOLICITADO_NO_COMPARECIO]');
                                $sliceComparecio = Str::before($sliceComparecio, '[FIN_SI_SOLICITADO_COMPARECIO]');
                                $string = $htmlA.$sliceComparecio.$htmlB;
                            }
                        }
                    } else {
                        $htmlA = Str::before($string, '[SI_SOLICITADO_COMPARECIO');
                        $htmlB = Str::after($string, '[FIN_SI_SOLICITADO_COMPARECIO]');
                        $sliceComparecio = '';
                        $string = $htmlA.$sliceComparecio.$htmlB;
                    }
                }
            }

            $partes = ['solicitado', 'solicitante'];
            foreach ($partes as $key => $parteL) {
                $htmlA = '';
                $htmlB = '';
                $slice = '';
                $parte = strtoupper($parteL);
                $countPersona = substr_count($string, '[SI_'.$parte.'_TIPO_PERSONA_FISICA]');
                // $countPersonaMoral = substr_count($string,'[SI_'.$parte.'_IPO_PERSONA_MORAL]');
                $countGenero = substr_count($string, '[SI_'.$parte.'_GENERO_MASCULINO]');
                // $countGeneroFem = substr_count($string,'[SI_'.$parte.'_IPO_PERSONA_MORAL]');
                if (isset($vars[$parteL.'_genero_id']) && $vars[$parteL.'_genero_id'] != null && $countGenero > 0) {
                    for ($i = 0; $i < $countGenero; $i++) {
                        switch ($vars[$parteL.'_genero_id']) {
                            case 2:
                                $count = substr_count($string, '[SI_'.$parte.'_GENERO_MASCULINO]');
                                if ($count > 0) {
                                    //Texto entre condiciones
                                    $slice = Str::after($string, '[SI_'.$parte.'_GENERO_MASCULINO]');
                                    $slice = Str::before($slice, '[SI');

                                    $htmlA = Str::before($string, '[SI_');
                                    $htmlB = Str::after($string, '[FIN_SI_'.$parte.'_GENERO]');

                                    $string = $htmlA.$slice.$htmlB;
                                }
                                break;
                            case 1:
                                $count = substr_count($string, '[SI_'.$parte.'_GENERO_FEMENINO]');
                                if ($count > 0) {
                                    $slice = Str::after($string, '[SI_'.$parte.'_GENERO_FEMENINO]');
                                    $slice = Str::before($slice, '[FIN_SI');

                                    $htmlA = Str::before($string, '[SI_'.$parte);
                                    $htmlB = Str::after($string, '[FIN_SI_'.$parte.'_GENERO]');
                                    $string = $htmlA.$slice.$htmlB;
                                }
                                break;
                        }
                    }
                }

                if (isset($vars[$parteL.'_tipo_persona_id']) && $vars[$parteL.'_tipo_persona_id'] != null && ($countPersona > 0)) {
                    for ($i = 0; $i < $countPersona; $i++) {
                        switch ($vars[$parteL.'_tipo_persona_id']) {
                            case 1: //fisica
                                $count = substr_count($string, '[SI_'.$parte.'_TIPO_PERSONA_FISICA]');
                                if ($count > 0) {
                                    //Texto entre condiciones
                                    $sliceFisica = Str::after($string, '[SI_'.$parte.'_TIPO_PERSONA_FISICA]');
                                    $sliceFisica = Str::before($sliceFisica, '[SI_');
                                    $htmlA = Str::before($string, '[SI_'.$parte.'_TIPO_PERSONA_FISICA]');
                                    $htmlB = Str::after($string, '[FIN_SI_'.$parte.'_TIPO_PERSONA]');
                                    $string = $htmlA.$sliceFisica.$htmlB;
                                }
                                break;
                            case 2: //moral
                                $count = substr_count($string, '[SI_'.$parte.'_TIPO_PERSONA_MORAL]');
                                if ($count > 0) {
                                    $sliceMoral = Str::after($string, '[SI_'.$parte.'_TIPO_PERSONA_MORAL]');
                                    $sliceMoral = Str::before($sliceMoral, '[FIN_SI');

                                    $htmlA = Str::before($string, '[SI_');
                                    $htmlB = Str::after($string, '[FIN_SI_'.$parte.'_TIPO_PERSONA]');
                                    $string = $htmlA.$sliceMoral.$htmlB;
                                }
                                break;
                        }
                    }
                }
            }
        }

        return $string;
    }

    /**
     * Regresa una cadena HTML compilada desde placeholders pasando por plantilla blade hasta html
     *
     * @param  $string  string Cadena con placeholders
     * @param  $vars  array Variables a sustituir en plantilla blade
     *
     * @throws Exception
     * @throws FatalThrowableError
     */
    public static function renderPlantillaPlaceholders($string, $vars)
    {
        $string = self::sustituyePlaceholdersConditionals($string, $vars);
        $vars_necesarias = [];
        if (preg_match_all('/\[(\w+)\]/', $string, $vars_necesarias) && isset($vars_necesarias[1])) {
            foreach ($vars_necesarias[1] as $varname) {
                if (! isset($vars[mb_strtolower($varname)])) {
                    $vars[mb_strtolower($varname)] = '<span style="color: red;">'.$varname.'</span>';
                }
            }
        }
        $blade = self::sustituyePlaceholders($string);

        return self::render($blade, $vars);
    }

    /**
     * Regresa una cadena HTML compilada desde placeholders pasando por plantilla blade hasta html
     *
     * @param  $string  string Cadena con placeholders
     * @param  $vars  array Variables a sustituir en plantilla blade
     *
     * @throws Exception
     * @throws FatalThrowableError
     */
    public static function renderOficioPlaceholders($string, $vars)
    {
        // $string = self::sustituyePlaceholdersConditionals($string,$vars);
        $blade = self::sustituyePlaceholders($string);

        return self::render($blade, $vars);
    }
}
