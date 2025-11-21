<?php

namespace App\Services\GestionMultas;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class DiasLaborablesService
{
    protected $incidencias = [];

    public function __construct($multa, $fecha)
    {
        $this->incidencias = $this->obtenerIncidenciasCentro($multa, $fecha);
    }

    /**
     * Convierte una cadena fecha en una instancia de Carbon, si la fecha es nula convierte al día de hoy la instancia de carbon
     *
     * @param  null  $fecha
     */
    public function fechaInstancia($fecha = null): ?Carbon
    {
        $fecha = $fecha instanceof Carbon ? $fecha : Carbon::parse(is_string($fecha) ? $fecha : now());

        return $fecha;
    }

    /**
     * @param  null  $fecha
     * @return mixed
     */
    public function obtenerIncidenciasCentro($multa, $fecha = null)
    {
        $fecha = $this->fechaInstancia($fecha);

        $centro = $multa->audiencia->expediente->solicitud->centro;

        // Llave única para el cache de esta consulta
        $cacheKey = 'incidencias-centro-'.$centro->id.'-'.$fecha->format('Y-m-d');

        // Tiempo de cache en minutos
        $cacheTime = config('gestion-multas.minutos_cache', 30);

        return Cache::remember($cacheKey, $cacheTime, function () use ($centro, $fecha) {
            return $centro->incidencias()->whereDate('fecha_inicio', '>=', $fecha->format('Y-m-d'))
                ->orderBy('fecha_inicio')
                ->get();
        });
    }

    /**
     * Revisamos si la fecha dada es hábil o es no laborable
     */
    public function esDiaHabil($fecha)
    {
        $fecha = $this->fechaInstancia($fecha);

        // Si es fin de semana inmediatamente regresamos como false
        if ($fecha->isWeekend()) {
            return false;
        }

        // Si no se encontraron incidencias para la fecha entonces es día hábil
        if (collect($this->incidencias)->isEmpty()) {
            return true;
        }

        // Si sí se encontraron incidencias entonces es un día no laborable
        foreach ($this->incidencias as $incidencia) {
            if ($fecha->betweenIncluded($incidencia->fecha_inicio, $incidencia->fecha_fin)) {
                return false;
            }
        }

        // Finalmente si llegamos aquí es porque es un día hábil
        return true;
    }

    /**
     * Tenemos la fecha del evento y los días de expiración. sacamos el día de hoy y contamos cuántos días
     * hábiles han transcurrido desde la fecha de evento a la fecha de hoy
     *
     * @param  $fecha  string Fecha inicial desde donde se empieza a contar
     * @param  $diasExpiracion  int Son los días hábiles de expiración
     * @param  null  $hoy
     * @return \Illuminate\Config\Repository|int|mixed
     */
    public function diasHabilesRestantes($text, $multa, $fecha, $diasExpiracion, $hoy = null)
    {
        $fecha = $this->fechaInstancia($fecha);

        if (! $hoy) {
            $hoy = Carbon::now();
        } else {
            $hoy = $this->fechaInstancia($hoy);
        }

        // Inicializa el contador de días laborables
        $diasLaborablesHanPasado = 0;

        // Recorre cada día entre las dos fechas
        for ($date = $fecha->addDays(); $date->lte($hoy); $date->addDay()) {
            // Si el día es laborable, incrementa el contador
            if ($this->esDiaHabil($date)) {
                $diasLaborablesHanPasado++;
            }
        }

        // Resta los días no laborables a los días de expiración
        $diasHabilesRestantes = (int) $diasExpiracion - (int) $diasLaborablesHanPasado;

        return $diasHabilesRestantes;
    }
}
