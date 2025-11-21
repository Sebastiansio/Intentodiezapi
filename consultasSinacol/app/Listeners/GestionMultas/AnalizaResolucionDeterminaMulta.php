<?php

namespace App\Listeners\GestionMultas;

use App\Audiencia;
use App\AudienciaParte;
use App\ClasificacionArchivo;
use App\Events\GenerateDocumentResolution;
use App\Models\GestionMultas\Multa;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class AnalizaResolucionDeterminaMulta implements ShouldQueue
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(GenerateDocumentResolution $event): void
    {
        try {

            $clasificacion_id = $event->clasificacion_id;

            // Si no se trata de un doc de multa entonces salimos sin crear multa
            // Verificamos si la constante existe antes de usarla
            $actaMultaId = defined('App\ClasificacionArchivo::ACTA_MULTA') 
                ? ClasificacionArchivo::ACTA_MULTA 
                : null;
            
            // Si no existe la constante o no coincide con la clasificación, salimos
            if ($actaMultaId === null || $clasificacion_id !== $actaMultaId) {
                return;
            }

            $fecha_en_vigor = config('gestion-multas.entra_en_vigor_desde');
            $audiencia_id = $event->idAudiencia;
            $citado_id = $event->idSolicitado;

            if (! $audiencia_id || ! $citado_id) {
                return;
            }

            // Si no tiene multa registrada el citado
            if (Multa::where('citado_id', $citado_id)->first()) {
                return;
            }

            // Si no se trata de una multa con fecha de audiencia posterior al 15 de Mayo del 2023 salimos sin crear multa
            if (! Audiencia::where('id', $audiencia_id)->where('fecha_audiencia', '>=', $fecha_en_vigor)->count()) {
                return;
            }

            // Buscamos el registro de la audiencia_parte
            $audienciaParte = AudienciaParte::where('audiencia_id', $audiencia_id)
                ->where('parte_id', $citado_id)
                ->first();

            // Generamos su multa
            $multa = Multa::create([
                'audiencia_id' => $audiencia_id,
                'citado_id' => $citado_id,
                'audiencia_parte_id' => $audienciaParte->id ?? null,
                'fecha_multa' => Carbon::today()->format('Y-m-d'),
                'fecha_estatus' => now(),
                'state' => 'por_confirmar',
            ]);

        } catch (\Exception $e) {
            Log::error(json_encode($event).' ERROR: '.$e->getMessage());
        }
    }
}
