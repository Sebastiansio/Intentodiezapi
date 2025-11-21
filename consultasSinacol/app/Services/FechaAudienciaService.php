<?php

namespace App\Services;

use App\Audiencia;
use App\Centro;
use App\Conciliador;
use App\Incidencia;
use App\RolConciliador;
use App\Solicitud;
use App\Traits\ValidateRange;
use Carbon\Carbon;
use DateTime;
use DateTimeZone;

class FechaAudienciaService
{
    use ValidateRange;

    /**
     * Aquí comienzan las funciones del algoritmo para asignar fecha a una audeincia al ratificar
     */
    public static function obtenerFechaAudiencia(string $hoy, Centro $centro, $min, $max, $virtual)
    {
        //Buscamos la primer fecha según el tipo de notificación
        $diaHabilCentro = Incidencia::siguienteDiaHabilMasDias($hoy, $centro->id, 'App\Centro', $min, $max);
        if ($diaHabilCentro['dia'] != 'nada') {
            $d = new Carbon($diaHabilCentro['dia']);
            [$hours, $minutes, $seg] = explode(':', $centro->duracionAudiencia);
            $duracion = $hours.'.'.$minutes / 60 * 100;
            $tiempoAdd = $duracion * 3600;
            $arreglo_horas = self::obtener_horas($centro->disponibilidades, $d->weekDay(), $tiempoAdd);
            //Valida horario del centro
            if (count($arreglo_horas) < 2) {
                return self::obtenerFechaAudiencia($diaHabilCentro['dia'], $centro, 1, $max, $virtual);
            }

            //obtenemos el arreglo de las horas
            $encontroSala = false;
            $encontroConciliador = false;
            $sala_id = null;
            $salas = $centro->salas()->orderBy('id', 'asc')->get();
            if ($virtual) {
                $rol = \App\RolAtencion::where('nombre', 'Conciliador virtual')->first();
                $sala_virtual = $centro->salas()->where('virtual', true)->first();
            } else {
                $rol = \App\RolAtencion::where('nombre', 'Conciliador en sala')->first();
            }
            $conciliadores = self::obtenerConciliadores($centro, $rol);

            foreach ($arreglo_horas as $hora_inicio) {
                $hora_fin = date('H:i:s', strtotime($hora_inicio) + $tiempoAdd);
                if ($virtual) {
                    $encontroSala = true;
                    $sala_id = $sala_virtual->id;
                } else {
                    foreach ($salas as $sala) {
                        if (! $sala->virtual) {
                            $disponibilidad = $sala->disponibilidades()->where('dia', $d->weekday())->first();
                            if ($disponibilidad != null) {
                                if (self::validarIncidencia($diaHabilCentro['dia'], $sala->id, 'App\Sala')) {
                                    $audiencias = Audiencia::where('audiencias.fecha_audiencia', $diaHabilCentro['dia'])
                                        ->where('audiencias.hora_inicio', $hora_inicio)
                                        ->where('audiencias.hora_fin', $hora_fin)
                                        ->whereHas('salasAudiencias', function ($q) use ($sala) {
                                            return $q->where('sala_id', $sala->id);
                                        })
                                        ->whereHas('expediente.solicitud', function ($q) {
                                            return $q->whereRaw('incidencia is not true');
                                        })
                                        ->get();

                                    if (count($audiencias) == 0) {
                                        if ($disponibilidad['hora_inicio'] <= $hora_inicio && $disponibilidad['hora_fin'] >= $hora_fin) {
                                            $encontroSala = true;
                                            $sala_id = $sala->id;
                                            break;
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
                // baja
                $centroApoyo = Centro::find($centro->id);
                $valCentro = $centroApoyo->apoyo_virtual;
                if ($virtual && $valCentro) {
                    //baja
                    // Nos traemos el pull de conciliadores con rol Conciliador de Apoyo
                    $conciliadoresCom = self::obtenerConciliadoresComodin();
                    foreach ($conciliadoresCom as $conciliadorC) {
                        if (self::validarHoraComida($conciliadorC, $hora_inicio, $hora_fin)) {
                            $conciliador = Conciliador::find($conciliadorC->conciliador_id);
                            $disponibilidad = $conciliador->disponibilidades()->where('dia', $d->weekday())->first();
                            if ($disponibilidad != null) {
                                //                        Validamos si el conciliador no tiene una incidencia
                                if (self::validarIncidencia($diaHabilCentro['dia'], $conciliador->id, 'App\Conciliador')) {
                                    $audiencias = Audiencia::select('audiencias.*')
                                        ->join('expedientes', 'audiencias.expediente_id', 'expedientes.id')
                                        ->join('solicitudes', 'expedientes.solicitud_id', 'solicitudes.id')
                                        ->whereRaw('solicitudes.incidencia is not true')
                                        ->where('audiencias.fecha_audiencia', $diaHabilCentro['dia'])
                                        ->where('audiencias.hora_inicio', $hora_inicio)
                                        ->where('audiencias.hora_fin', $hora_fin)
                                        ->where('conciliador_id', $conciliador->id)
                                        ->get();
                                    if (count($audiencias) == 0) {
                                        $audienciasQ = Audiencia::select('audiencias.*')
                                            ->where('audiencias.fecha_audiencia', $diaHabilCentro['dia'])
                                            ->where('conciliador_id', $conciliador->id)
                                            ->orderBy('audiencias.hora_fin', 'asc')
                                            ->get();
                                        if (count($audienciasQ) > 0) {
                                            $choca_audiencia = false;
                                            foreach ($audienciasQ as $audienciaQ) {
                                                $hora_inicio_audiencia = $audienciaQ->hora_inicio;
                                                $hora_fin_audiencia = $audienciaQ->hora_fin;
                                                $hora_inicio_audiencia_nueva = $hora_inicio;
                                                $hora_fin_audiencia_nueva = $hora_fin;
                                                if (! self::rangesNotOverlapOpen($hora_inicio_audiencia, $hora_fin_audiencia, $hora_inicio_audiencia_nueva, $hora_fin_audiencia_nueva)) {
                                                    $choca_audiencia = true;
                                                }
                                            }
                                            if (! $choca_audiencia) {
                                                $encontroConciliador = true;
                                                $conciliador_id = $conciliador->id;
                                                break;
                                            }
                                        } else {
                                            $encontroConciliador = true;
                                            $conciliador_id = $conciliador->id;
                                            break;
                                        }
                                    }
                                }
                            }
                        }
                    }
                } else {
                    $conciliadores = self::obtenerConciliadores($centro, $rol);
                    foreach ($conciliadores as $conciliadorE) {
                        //Validamos que la hora de inicio del conciliador no sea igual a la hora de inicio de la audiencia
                        if (self::validarHoraComida($conciliadorE, $hora_inicio, $hora_fin)) {
                            $conciliador = Conciliador::find($conciliadorE->id);
                            $disponibilidad = $conciliador->disponibilidades()->where('dia', $d->weekday())->first();
                            if ($disponibilidad != null) {
                                //                        Validamos si el conciliador no tiene una incidencia
                                if (self::validarIncidencia($diaHabilCentro['dia'], $conciliador->id, 'App\Conciliador')) {
                                    $audiencias = Audiencia::join('conciliadores_audiencias', 'audiencias.id', '=', 'conciliadores_audiencias.audiencia_id')
                                        ->select('audiencias.*')
                                        ->join('expedientes', 'audiencias.expediente_id', 'expedientes.id')
                                        ->join('solicitudes', 'expedientes.solicitud_id', 'solicitudes.id')
                                        ->whereRaw('solicitudes.incidencia is not true')
                                        ->where('audiencias.fecha_audiencia', $diaHabilCentro['dia'])
                                        ->where('audiencias.hora_inicio', $hora_inicio)
                                        ->where('audiencias.hora_fin', $hora_fin)
                                        ->where('audiencias.conciliador_id', $conciliador->id)
                                        ->get();
                                    if (count($audiencias) == 0) {
                                        $audienciasQ = Audiencia::join('conciliadores_audiencias', 'audiencias.id', '=', 'conciliadores_audiencias.audiencia_id')
                                            ->select('audiencias.*')
                                            ->where('audiencias.fecha_audiencia', $diaHabilCentro['dia'])
                                            ->where('audiencias.conciliador_id', $conciliador->id)
                                            ->orderBy('audiencias.hora_fin', 'asc')
                                            ->get();
                                        if (count($audienciasQ) > 0) {
                                            $choca_audiencia = false;
                                            foreach ($audienciasQ as $audienciaQ) {
                                                $hora_inicio_audiencia = $audienciaQ->hora_inicio;
                                                $hora_fin_audiencia = $audienciaQ->hora_fin;
                                                $hora_inicio_audiencia_nueva = $hora_inicio;
                                                $hora_fin_audiencia_nueva = $hora_fin;
                                                if (! self::rangesNotOverlapOpen($hora_inicio_audiencia, $hora_fin_audiencia, $hora_inicio_audiencia_nueva, $hora_fin_audiencia_nueva)) {
                                                    if ($disponibilidad['hora_inicio'] <= $hora_inicio && $disponibilidad['hora_fin'] >= $hora_fin) {
                                                        $choca_audiencia = true;
                                                    }
                                                }
                                            }
                                            if (! $choca_audiencia) {
                                                if ($disponibilidad['hora_inicio'] <= $hora_inicio && $disponibilidad['hora_fin'] >= $hora_fin) {
                                                    $encontroConciliador = true;
                                                    $conciliador_id = $conciliador->id;
                                                    break;
                                                }
                                            }
                                        } else {
                                            if ($disponibilidad['hora_inicio'] <= $hora_inicio && $disponibilidad['hora_fin'] >= $hora_fin) {
                                                $encontroConciliador = true;
                                                $conciliador_id = $conciliador->id;
                                                break;
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
                if ($encontroSala && $encontroConciliador) {
                    $horaInicioSalaDisponible = $hora_inicio;
                    $horaFinSalaDisponible = $hora_fin;
                    break;
                }
            }
            if ($encontroSala && $encontroConciliador) {
                return [
                    'fecha_audiencia' => $diaHabilCentro['dia'],
                    'hora_inicio' => $horaInicioSalaDisponible,
                    'hora_fin' => $horaFinSalaDisponible,
                    'sala_id' => $sala_id,
                    'conciliador_id' => $conciliador_id,
                    'encontro_audiencia' => true];
            } else {
                return self::obtenerFechaAudiencia($diaHabilCentro['dia'], $centro, 0, $diaHabilCentro['max'], $virtual);
            }
        } else {
            return [
                'fecha_audiencia' => null,
                'hora_inicio' => null,
                'hora_fin' => null,
                'sala_id' => null,
                'conciliador_id' => null,
                'encontro_audiencia' => false];
        }
    }

    public static function obtenerFechaAudienciaDoble(string $hoy, Centro $centro, $min, $max, $virtual = false)
    {
        $diaHabilCentro = Incidencia::siguienteDiaHabilMasDias($hoy, $centro->id, 'App\Centro', $min, $max);
        if ($diaHabilCentro['dia'] != 'nada') {
            $d = new Carbon($diaHabilCentro['dia']);
            [$hours, $minutes, $seg] = explode(':', $centro->duracionAudiencia);
            $duracion = $hours.'.'.$minutes / 60 * 100;
            $tiempoAdd = $duracion * 3600;
            $arreglo_horas = self::obtener_horas($centro->disponibilidades, $d->weekDay(), $tiempoAdd);
            //obtenemos el arreglo de las horas
            $encontroSala = false;
            $encontroConciliador = false;
            $sala_id1 = null;
            $sala_id2 = null;
            $salas = $centro->salas()->orderBy('id', 'asc')->get();
            if ($virtual) {
                $rol = \App\RolAtencion::where('nombre', 'Conciliador virtual')->first();
                $sala_virtual = $centro->salas()->where('virtual', true)->first();
            } else {
                $rol = \App\RolAtencion::where('nombre', 'Conciliador en sala')->first();
            }
            $conciliadores = self::obtenerConciliadores($centro, $rol);
            foreach ($arreglo_horas as $hora_inicio) {
                $hora_fin = date('H:i:s', strtotime($hora_inicio) + $tiempoAdd);
                if ($virtual) {
                    $encontroSala = true;
                    $sala_id1 = $sala_virtual->id;
                    $sala_id2 = $sala_virtual->id;
                } else {
                    foreach ($salas as $sala) {
                        if (! $sala->virtual) {
                            $disponibilidad = $sala->disponibilidades()->where('dia', $d->weekday())->first();
                            if ($disponibilidad != null) {
                                if (self::validarIncidencia($diaHabilCentro['dia'], $sala->id, 'App\Sala')) {
                                    $audiencias = Audiencia::join('salas_audiencias', 'audiencias.id', '=', 'salas_audiencias.audiencia_id')
                                        ->join('expedientes', 'audiencias.expediente_id', 'expedientes.id')
                                        ->join('solicitudes', 'expedientes.solicitud_id', 'solicitudes.id')
                                        ->whereRaw('solicitudes.incidencia is not true')
                                        ->where('audiencias.fecha_audiencia', $diaHabilCentro['dia'])
                                        ->where('audiencias.hora_inicio', $hora_inicio)
                                        ->where('audiencias.hora_fin', $hora_fin)
                                        ->where('salas_audiencias.sala_id', $sala->id)
                                        ->select('audiencias.*')
                                        ->get();
                                    if (count($audiencias) == 0) {
                                        //Buscamos la segunda audiencia
                                        $sala_segunda = self::obtenerSegundaSalaAudiencia($diaHabilCentro['dia'], $hora_inicio, $hora_fin, $sala->id, $salas);
                                        if ($sala_segunda != null) {
                                            $encontroSala = true;
                                            $sala_id1 = $sala->id;
                                            $sala_id2 = $sala_segunda;
                                            break;
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
                foreach ($conciliadores as $conciliador) {
                    if (self::validarHoraComida($conciliador, $hora_inicio, $hora_fin)) {
                        $disponibilidad = $conciliador->disponibilidades()->where('dia', $d->weekday())->first();
                        if ($disponibilidad != null) {
                            if (self::validarIncidencia($diaHabilCentro['dia'], $conciliador->id, 'App\Conciliador')) {
                                $audiencias = Audiencia::join('conciliadores_audiencias', 'audiencias.id', '=', 'conciliadores_audiencias.audiencia_id')
                                    ->join('expedientes', 'audiencias.expediente_id', 'expedientes.id')
                                    ->join('solicitudes', 'expedientes.solicitud_id', 'solicitudes.id')
                                    ->whereRaw('solicitudes.incidencia is not true')
                                    ->where('audiencias.fecha_audiencia', $diaHabilCentro['dia'])
                                    ->where('audiencias.hora_inicio', $hora_inicio)
                                    ->where('audiencias.hora_fin', $hora_fin)
                                    ->where('conciliadores_audiencias.conciliador_id', $conciliador->id)
                                    ->select('audiencias.*')
                                    ->get();
                                if (count($audiencias) == 0) {
                                    $conciliador_segundo = self::obtenerSegundoConciliadorAudiencia($diaHabilCentro['dia'], $hora_inicio, $hora_fin, $conciliador->id, $conciliadores);
                                    if ($conciliador_segundo != null) {
                                        $encontroConciliador = true;
                                        $conciliador_id1 = $conciliador->id;
                                        $conciliador_id2 = $conciliador_segundo;
                                        break;
                                    }
                                }
                            }
                        }
                    }
                }
                if ($encontroSala && $encontroConciliador) {
                    $horaInicioSalaDisponible = $hora_inicio;
                    $horaFinSalaDisponible = $hora_fin;
                    break;
                }
            }
            if ($encontroSala && $encontroConciliador) {
                return [
                    'fecha_audiencia' => $diaHabilCentro['dia'],
                    'hora_inicio' => $horaInicioSalaDisponible,
                    'hora_fin' => $horaFinSalaDisponible,
                    'sala_id' => $sala_id1,
                    'sala2_id' => $sala_id2,
                    'conciliador_id' => $conciliador_id1,
                    'conciliador2_id' => $conciliador_id2,
                    'encontro_audiencia' => true,
                ];
            } else {
                return self::obtenerFechaAudienciaDoble($diaHabilCentro['dia'], $centro, 0, $diaHabilCentro['max']);
            }
        } else {
            return [
                'fecha_audiencia' => null,
                'hora_inicio' => null,
                'hora_fin' => null,
                'sala_id' => null,
                'conciliador_id' => null,
                'encontro_audiencia' => false];
        }
    }

    public static function obtenerSegundaSalaAudiencia(string $fecha, string $hora_inicio, string $hora_fin, $sala_id, $salas)
    {
        $encontroSala = false;
        $d = new Carbon($fecha);
        foreach ($salas as $sala) {
            if ($sala->id != $sala_id && ! $sala->virtual) {
                $disponibilidad = $sala->disponibilidades()->where('dia', $d->weekday())->first();
                if ($disponibilidad != null) {
                    if (self::validarIncidencia($fecha, $sala->id, 'App\Sala')) {
                        $audiencias = Audiencia::join('salas_audiencias', 'audiencias.id', '=', 'salas_audiencias.audiencia_id')
                            ->join('expedientes', 'audiencias.expediente_id', 'expedientes.id')
                            ->join('solicitudes', 'expedientes.solicitud_id', 'solicitudes.id')
                            ->whereRaw('solicitudes.incidencia is not true')
                            ->where('audiencias.fecha_audiencia', $fecha)
                            ->where('audiencias.hora_inicio', $hora_inicio)
                            ->where('audiencias.hora_fin', $hora_fin)
                            ->where('salas_audiencias.sala_id', $sala->id)
                            ->select('audiencias.*')
                            ->get();
                        if (count($audiencias) == 0) {
                            //Buscamos la segunda audiencia
                            $encontroSala = true;
                            $sala_encontrada_id = $sala->id;
                            break;
                        }
                    }
                }
            }
        }
        if ($encontroSala) {
            return $sala_encontrada_id;
        } else {
            return null;
        }
    }

    public static function obtenerSegundoConciliadorAudiencia(string $fecha, string $hora_inicio, string $hora_fin, $conciliador_id, $conciliadores)
    {
        $encontroConciliador = false;
        $d = new Carbon($fecha);
        foreach ($conciliadores as $conciliador) {
            if (self::validarHoraComida($conciliador, $hora_inicio, $hora_fin)) {
                if ($conciliador->id != $conciliador_id) {
                    $disponibilidad = $conciliador->disponibilidades()->where('dia', $d->weekday())->first();
                    if ($disponibilidad != null) {
                        if (self::validarIncidencia($fecha, $conciliador->id, 'App\Conciliador')) {
                            $audiencias = Audiencia::join('conciliadores_audiencias', 'audiencias.id', '=', 'conciliadores_audiencias.audiencia_id')
                                ->select('audiencias.*')
                                ->where('audiencias.fecha_audiencia', $fecha)
                                ->where('audiencias.hora_inicio', $hora_inicio)
                                ->where('audiencias.hora_fin', $hora_fin)
                                ->where('conciliadores_audiencias.conciliador_id', $conciliador->id)
                                ->get();
                            if (count($audiencias) == 0) {
                                //Buscamos la segunda audiencia
                                $encontroConciliador = true;
                                $conciliador_encontrado_id = $conciliador->id;
                                break;
                            }
                        }
                    }
                }
            }
        }
        if ($encontroConciliador) {
            return $conciliador_encontrado_id;
        } else {
            return null;
        }
    }

    /**
     * Aquí comienzan las funciones del algoritmo para asignar fecha a una audiencia al crearse una a partir de otra
     */

    /**
     * Determina la próxima fecha hábil para una cita.
     *
     * @param  string  $asunto
     * @param  string  $horario
     * @param  int  $junta
     * @param  int  $add  Por default son tres días hábiles.
     * @return mixed|string|void
     */
    public static function proximaFechaCita(string $hoy, Centro $centro, $min, $max, Conciliador $conciliador, $virtual)
    {
        //Buscamos la primer fecha según el tipo de notificación
        $diaHabilCentro = Incidencia::siguienteDiaHabilMasDias($hoy, $centro->id, 'App\Centro', $min, $max);
        if ($diaHabilCentro['dia'] != 'nada') {
            $d = new Carbon($diaHabilCentro['dia']);
            [$hours, $minutes, $seg] = explode(':', $centro->duracionAudiencia);
            $duracion = $hours.'.'.$minutes / 60 * 100;
            $tiempoAdd = $duracion * 3600;
            $arreglo_horas = self::obtener_horas($centro->disponibilidades, $d->weekDay(), $tiempoAdd);
            //obtenemos el arreglo de las horas
            $encontroSala = false;
            $encontroConciliador = false;
            $sala_id = null;
            if ($virtual) {
                $rol = \App\RolAtencion::where('nombre', 'Conciliador virtual')->first();
                $sala_virtual = $centro->salas()->where('virtual', true)->first();
            } else {
                $salas = $centro->salas()->orderBy('id', 'asc')->get();
            }
            foreach ($arreglo_horas as $hora_inicio) {
                $hora_fin = date('H:i:s', strtotime($hora_inicio) + $tiempoAdd);
                if ($virtual) {
                    $encontroSala = true;
                    $sala_id = $sala_virtual->id;
                } else {
                    foreach ($salas as $sala) {
                        if (! $sala->virtual) {
                            $disponibilidad = $sala->disponibilidades()->where('dia', $d->weekday())->first();
                            if ($disponibilidad != null) {
                                if (self::validarIncidencia($diaHabilCentro['dia'], $sala->id, 'App\Sala')) {
                                    $audiencias = Audiencia::join('salas_audiencias', 'audiencias.id', '=', 'salas_audiencias.audiencia_id')
                                        ->join('expedientes', 'audiencias.expediente_id', 'expedientes.id')
                                        ->join('solicitudes', 'expedientes.solicitud_id', 'solicitudes.id')
                                        ->whereRaw('solicitudes.incidencia is not true')
                                        ->where('audiencias.fecha_audiencia', $diaHabilCentro['dia'])
                                        ->where('audiencias.hora_inicio', $hora_inicio)
                                        ->where('audiencias.hora_fin', $hora_fin)
                                        ->where('salas_audiencias.sala_id', $sala->id)
                                        ->select('audiencias.*')
                                        ->get();
                                    if (count($audiencias) == 0) {
                                        $encontroSala = true;
                                        $sala_id = $sala->id;
                                        break;
                                    }
                                }
                            }
                        }
                    }
                }
                $disponibilidad = $conciliador->disponibilidades()->where('dia', $d->weekday())->first();
                if ($disponibilidad != null) {
                    if (self::validarHoraComida($conciliador, $hora_inicio, $hora_fin)) {
                        if (self::validarIncidencia($diaHabilCentro['dia'], $conciliador->id, 'App\Conciliador')) {
                            $audiencias = Audiencia::join('conciliadores_audiencias', 'audiencias.id', '=', 'conciliadores_audiencias.audiencia_id')
                                ->select('audiencias.*')
                                ->join('expedientes', 'audiencias.expediente_id', 'expedientes.id')
                                ->join('solicitudes', 'expedientes.solicitud_id', 'solicitudes.id')
                                ->whereRaw('solicitudes.incidencia is not true')
                                ->where('audiencias.fecha_audiencia', $diaHabilCentro['dia'])
                                ->where('audiencias.hora_inicio', $hora_inicio)
                                ->where('audiencias.hora_fin', $hora_fin)
                                ->where('conciliadores_audiencias.conciliador_id', $conciliador->id)
                                ->get();
                            if (count($audiencias) == 0) {
                                $encontroConciliador = true;
                                $conciliador_id = $conciliador->id;
                            }
                        }
                    }
                }

                if ($encontroSala && $encontroConciliador) {
                    $horaInicioSalaDisponible = $hora_inicio;
                    $horaFinSalaDisponible = $hora_fin;
                    break;
                }
            }
            if ($encontroSala && $encontroConciliador) {
                return [
                    'fecha_audiencia' => $diaHabilCentro['dia'],
                    'hora_inicio' => $horaInicioSalaDisponible,
                    'hora_fin' => $horaFinSalaDisponible,
                    'sala_id' => $sala_id,
                    'conciliador_id' => $conciliador_id,
                    'encontro_audiencia' => true];
            } else {
                return self::proximaFechaCita($diaHabilCentro['dia'], $centro, 0, $diaHabilCentro['max'], $conciliador, $virtual);
            }
        } else {
            return [
                'fecha_audiencia' => null,
                'hora_inicio' => null,
                'hora_fin' => null,
                'sala_id' => null,
                'conciliador_id' => null,
                'encontro_audiencia' => false];
        }
    }

    public static function proximaFechaCitaDoble(string $hoy, Centro $centro, $min, $max, $conciliadoresAudiencia, $virtual)
    {
        $diaHabilCentro = Incidencia::siguienteDiaHabilMasDias($hoy, $centro->id, 'App\Centro', $min, $max);
        if ($diaHabilCentro['dia'] != 'nada') {
            $d = new Carbon($diaHabilCentro['dia']);
            [$hours, $minutes, $seg] = explode(':', $centro->duracionAudiencia);
            $duracion = $hours.'.'.$minutes / 60 * 100;
            $tiempoAdd = $duracion * 3600;
            $arreglo_horas = self::obtener_horas($centro->disponibilidades, $d->weekDay(), $tiempoAdd);
            //obtenemos el arreglo de las horas
            $encontroSala = false;
            $encontroConciliador = false;
            $sala_id1 = null;
            $sala_id2 = null;
            $conciliadores = [];
            foreach ($conciliadoresAudiencia as $conciliadorAudiencia) {
                $conciliadores[] = $conciliadorAudiencia->conciliador;
            }
            if ($virtual) {
                $sala_virtual = $centro->salas()->where('virtual', true)->first();
            } else {
                $salas = $centro->salas()->orderBy('id', 'asc')->get();
            }
            foreach ($arreglo_horas as $hora_inicio) {
                $hora_fin = date('H:i:s', strtotime($hora_inicio) + $tiempoAdd);
                if ($virtual) {
                    $encontroSala = true;
                    $sala_id1 = $sala_virtual->id;
                    $sala_id2 = $sala_virtual->id;
                } else {
                    foreach ($salas as $sala) {
                        if (! $sala->virtual) {
                            $disponibilidad = $sala->disponibilidades()->where('dia', $d->weekday())->first();
                            if ($disponibilidad != null) {
                                if (self::validarIncidencia($diaHabilCentro['dia'], $sala->id, 'App\Sala')) {
                                    $audiencias = Audiencia::join('salas_audiencias', 'audiencias.id', '=', 'salas_audiencias.audiencia_id')
                                        ->join('expedientes', 'audiencias.expediente_id', 'expedientes.id')
                                        ->join('solicitudes', 'expedientes.solicitud_id', 'solicitudes.id')
                                        ->whereRaw('solicitudes.incidencia is not true')
                                        ->where('audiencias.fecha_audiencia', $diaHabilCentro['dia'])
                                        ->where('audiencias.hora_inicio', $hora_inicio)
                                        ->where('audiencias.hora_fin', $hora_fin)
                                        ->where('salas_audiencias.sala_id', $sala->id)
                                        ->select('audiencias.*')
                                        ->get();
                                    if (count($audiencias) == 0) {
                                        //Buscamos la segunda audiencia
                                        $sala_segunda = self::obtenerSegundaSalaAudiencia($diaHabilCentro['dia'], $hora_inicio, $hora_fin, $sala->id, $salas);
                                        if ($sala_segunda != null) {
                                            $encontroSala = true;
                                            $sala_id1 = $sala->id;
                                            $sala_id2 = $sala_segunda;
                                            break;
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
                foreach ($conciliadores as $conciliador) {
                    $disponibilidad = $conciliador->disponibilidades()->where('dia', $d->weekday())->first();
                    if ($disponibilidad != null) {
                        if (self::validarHoraComida($conciliador, $hora_inicio, $hora_fin)) {
                            if (self::validarIncidencia($diaHabilCentro['dia'], $conciliador->id, 'App\Conciliador')) {
                                $audiencias = Audiencia::join('conciliadores_audiencias', 'audiencias.id', '=', 'conciliadores_audiencias.audiencia_id')
                                    ->join('expedientes', 'audiencias.expediente_id', 'expedientes.id')
                                    ->join('solicitudes', 'expedientes.solicitud_id', 'solicitudes.id')
                                    ->whereRaw('solicitudes.incidencia is not true')
                                    ->where('audiencias.fecha_audiencia', $diaHabilCentro['dia'])
                                    ->where('audiencias.hora_inicio', $hora_inicio)
                                    ->where('audiencias.hora_fin', $hora_fin)
                                    ->where('conciliadores_audiencias.conciliador_id', $conciliador->id)
                                    ->select('audiencias.*')
                                    ->get();
                                if (count($audiencias) == 0) {
                                    $conciliador_segundo = self::obtenerSegundoConciliadorAudiencia($diaHabilCentro['dia'], $hora_inicio, $hora_fin, $conciliador->id, $conciliadores);
                                    if ($conciliador_segundo != null) {
                                        $encontroConciliador = true;
                                        $conciliador_id1 = $conciliador->id;
                                        $conciliador_id2 = $conciliador_segundo;
                                        break;
                                    }
                                }
                            }
                        }
                    }
                }
                if ($encontroSala && $encontroConciliador) {
                    $horaInicioSalaDisponible = $hora_inicio;
                    $horaFinSalaDisponible = $hora_fin;
                    break;
                }
            }
            if ($encontroSala && $encontroConciliador) {
                return [
                    'fecha_audiencia' => $diaHabilCentro['dia'],
                    'hora_inicio' => $horaInicioSalaDisponible,
                    'hora_fin' => $horaFinSalaDisponible,
                    'sala_id' => $sala_id1,
                    'sala2_id' => $sala_id2,
                    'conciliador_id' => $conciliador_id1,
                    'conciliador2_id' => $conciliador_id2,
                    'encontro_audiencia' => true,
                ];
            } else {
                return self::proximaFechaCitaDoble($diaHabilCentro['dia'], $centro, 0, $diaHabilCentro['max'], $conciliadoresAudiencia, $virtual);
            }
        } else {
            return [
                'fecha_audiencia' => null,
                'hora_inicio' => null,
                'hora_fin' => null,
                'sala_id' => null,
                'conciliador_id' => null,
                'encontro_audiencia' => false];
        }
    }

    public static function obtenerSegundaSala($sala, $dia, $hora_inicio, $hora_fin)
    {
        $segundaSala = false;
        $segundaSala_id = null;
        $d = new Carbon($dia);
        foreach ($sala->centro->salas()->inRandomOrder()->get() as $sala2) {
            $hora_inicio_sala = '00:00:00';
            $hora_fin_sala = '23:59:59';
            if ($sala2->id != $sala->id) {
                if (! $sala2->virtual) {
                    if (self::validarIncidencia($dia, $sala2->id, 'App\Sala')) {
                        foreach ($sala2->disponibilidades as $disponibilidad) {
                            if ($d->weekday() == $disponibilidad->dia) {
                                $hora_inicio_sala = $disponibilidad->hora_inicio;
                                $hora_fin_sala = $disponibilidad->hora_fin;
                                $audiencias = Audiencia::join('conciliadores_audiencias', 'audiencias.id', '=', 'conciliadores_audiencias.audiencia_id')
                                    ->select('audiencias.*')
                                    ->where('audiencias.fecha_audiencia', $dia)
                                    ->where('conciliadores_audiencias.conciliador_id', $sala2->id)
                                    ->get();
                                if (count($audiencias) > 0) {
                                    $choca_audiencia = false;
                                    foreach ($audiencias as $audiencia) {
                                        $hora_inicio_audiencia = $audiencia->hora_inicio;
                                        $hora_fin_audiencia = $audiencia->hora_fin;
                                        $hora_inicio_audiencia_nueva = $hora_inicio;
                                        $hora_fin_audiencia_nueva = $hora_fin;

                                        if (! self::rangesNotOverlapOpen($hora_inicio_audiencia, $hora_fin_audiencia, $hora_inicio_audiencia_nueva, $hora_fin_audiencia_nueva)) {
                                            $choca_audiencia = true;
                                        }
                                    }
                                    if (! $choca_audiencia) {
                                        return ['segunda_sala' => true, 'segunda_sala_id' => $sala2->id];
                                        break;
                                    }
                                } else {
                                    return ['segunda_sala' => true, 'segunda_sala_id' => $sala2->id];
                                    break;
                                }
                            }
                        }
                    }
                }
            }
        }

        return ['segunda_sala' => false, 'segunda_sala_id' => null];
    }

    public static function obtenerSegundoConciliador($conciliador, $dia, $hora_inicio, $hora_fin)
    {
        $encontroConciliador = false;
        $d = new Carbon($dia);
        foreach ($conciliador->centro->conciliadores()->inRandomOrder()->get() as $conciliador2) {
            if ($conciliador2->id != $conciliador->id) {
                if (self::validarIncidencia($dia, $conciliador2->id, 'App\Conciliador')) {
                    if (self::validarHoraComida($conciliador2, $hora_inicio, $hora_fin)) {
                        foreach ($conciliador2->disponibilidades as $disponibilidad) {
                            if ($d->weekday() == $disponibilidad->dia) {
                                $audiencias = Audiencia::join('conciliadores_audiencias', 'audiencias.id', '=', 'conciliadores_audiencias.audiencia_id')
                                    ->select('audiencias.*')
                                    ->where('audiencias.fecha_audiencia', $dia)
                                    ->where('conciliadores_audiencias.conciliador_id', $conciliador2->id)
                                    ->get();
                                if (count($audiencias) > 0) {
                                    $choca_audiencia = false;
                                    foreach ($audiencias as $audiencia) {
                                        $hora_inicio_audiencia = $audiencia->hora_inicio;
                                        $hora_fin_audiencia = $audiencia->hora_fin;
                                        $hora_inicio_audiencia_nueva = $hora_inicio;
                                        $hora_fin_audiencia_nueva = $hora_fin;

                                        if (! self::rangesNotOverlapOpen($hora_inicio_audiencia, $hora_fin_audiencia, $hora_inicio_audiencia_nueva, $hora_fin_audiencia_nueva)) {
                                            $choca_audiencia = true;
                                        }
                                    }
                                    if (! $choca_audiencia) {
                                        return ['segundo_conciliador' => true, 'segundo_conciliador_id' => $conciliador2->id];
                                        break;
                                    }
                                } else {
                                    return ['segundo_conciliador' => true, 'segundo_conciliador_id' => $conciliador2->id];
                                    break;
                                }
                            }
                        }
                    }
                }
            }
        }

        return ['segundo_conciliador' => false, 'segundo_conciliador_id' => null];
    }

    /**
     * Aqui van las funciones que usan los dos algoritmos
     */
    public static function getHoraFinalNuevaAudiencia($fecha, $audiencias, $duracion)
    {
        $ultima_audiencia_dia = $audiencias->last();
        $valores = self::obtenerValoresSuma($duracion);
        $fecha_asignable = date('H:i:s', strtotime('+'.$valores[0].' hour +'.$valores[1].' minutes +'.$valores[2].' seconds', strtotime($ultima_audiencia_dia->hora_fin)));

        return $fecha_asignable;
    }

    public static function obtenerValoresSuma($duracion)
    {
        return $separa = explode(':', $duracion);
    }

    public static function rangesNotOverlapOpen($start_time1, $end_time1, $start_time2, $end_time2)
    {
        $utc = new DateTimeZone('UTC');

        $start1 = new DateTime($start_time1, $utc);
        $end1 = new DateTime($end_time1, $utc);
        if ($end1 < $start1) {
            throw new Exception('Range is negative.');
        }

        $start2 = new DateTime($start_time2, $utc);
        $end2 = new DateTime($end_time2, $utc);
        if ($end2 < $start2) {
            throw new Exception('Range is negative.');
        }

        return ($end1 <= $start2) || ($end2 <= $start1);
    }

    public static function obtener_horas($disponibilidades, $dia, $duracion)
    {
        $arregloHoras = [];
        //Obtenemos la disponibilidad corresponidiente al día
        $disponibilidad = $disponibilidades->where('dia', $dia)->first();
        if ($disponibilidad != null) {

            //Obtenemos la hora inicio que será la primer hora en el arreglo

            $hora_actual_time = strtotime($disponibilidad->hora_inicio);
            $hora_fin_time = strtotime($disponibilidad->hora_fin);
            $arregloHoras[] = date('H:i:s', $hora_actual_time);
            while ($hora_fin_time >= $hora_actual_time) {
                $arregloHoras[] = date('H:i:s', $hora_actual_time + $duracion);
                $hora_actual_time = $hora_actual_time + $duracion;
            }
            array_pop($arregloHoras);

            return $arregloHoras;
        } else {
            return [];
        }

    }

    public static function obtenerConciliadores(Centro $centro, $rol)
    {
        //        Validamos si es la primer confirmación del día
        $audiencias = Audiencia::whereBetween('created_at', [date('Y-m-d').' 00:00:00', date('Y-m-d').' 23:59:59'])->count();
        if ($audiencias == 0) {
            $conciliadores = Conciliador::where('centro_id', $centro->id)->inRandomOrder()->get();
            $i = 1;
            foreach ($conciliadores as $conciliador) {
                $conciliador->update(['orden' => $i]);
                $i++;
            }
        }
        $conciliadores = Centro::find($centro->id)->conciliadores()->whereHas('rolesConciliador', function ($q) use ($rol) {
            return $q->where('rol_atencion_id', $rol->id);
            // })->orderBy('orden','asc')->get();
        })->inRandomOrder()->get();

        return $conciliadores;
    }

    public static function validarIncidencia($fecha, $id, $incidencia_type)
    {
        $d = new Carbon($fecha);
        if ($d->isWeekend()) {
            return false;
        }
        $fechaInicioEv = $fecha.' 00:00:00';
        $fechaFinEv = $fecha.' 23:59:59';
        $incidencia = Incidencia::whereDate('fecha_inicio', '<=', $fechaFinEv)
            ->whereDate('fecha_fin', '>=', $fechaInicioEv)
            ->where('incidenciable_type', $incidencia_type)
            ->where('incidenciable_id', $id)->get();
        if (count($incidencia) > 0) {
            return false;
        } else {
            return true;
        }
    }

    public static function validarHoraComida($inhabilitable, $hora_inicio, $hora_fin)
    {
        if ($inhabilitable->horario_comida != null) {
            if ($inhabilitable->horario_comida->hora_inicio != $hora_inicio) {
                return true;
            } else {
                return false;
            }
        }

        return true;
    }

    public static function calcularFechaNotificador($fecha)
    {
        $fecha_d = new Carbon($fecha);
        $fecha_nueva = $fecha_d;
        $num = 0;
        for ($i = 1; $i <= 50; $i++) {
            $fecha_nueva = $fecha_nueva->addDay();
            if (! $fecha_nueva->isWeekend()) {
                $num++;
                if ($num >= 15) {
                    break;
                }
            }
        }

        return $fecha_nueva->format('d/m/Y');
    }

    public static function validarFechasAsignables(Solicitud $solicitud, $fecha_solicitada)
    {
        if ($solicitud->tipo_solicitud_id == 1) {
            $dt = new Carbon($solicitud->created_at);
            $dt2 = new Carbon($fecha_solicitada);
            /*$dias = $dt->diffInDaysFiltered(function(Carbon $date) {
                return !$date->isWeekend();
            }, $dt2);*/
            $dias = $dt->diffInDays($dt2);

            return $dias;
        } else {
            return 1;
        }
    }

    // baja
    public static function obtenerConciliadoresComodin()
    {
        // Obtenemos los conciliadores de apoyo para atender audiencias virtuales
        $conciliadores = RolConciliador::whereRolAtencionId(4)->inRandomOrder()->get();

        return $conciliadores;
    }
}
