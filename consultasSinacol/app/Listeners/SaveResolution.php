<?php

namespace App\Listeners;

use App\Events\GenerateDocumentResolution;
use App\Traits\GenerateDocument;
// use Illuminate\Contracts\Queue\ShouldQueue; // TEMPORALMENTE DESHABILITADO PARA DEBUG
use Illuminate\Support\Facades\Log;

class SaveResolution // implements ShouldQueue // TEMPORALMENTE QUITADO
{
    use GenerateDocument;

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
        Log::info('SaveResolution: Listener ejecutándose', [
            'audiencia_id' => $event->idAudiencia,
            'solicitud_id' => $event->idSolicitud,
            'clasificacion_id' => $event->clasificacion_id,
            'plantilla_id' => $event->plantilla_id
        ]);
        
        try {
            $resultado = $this->generarConstancia(
                $event->idAudiencia,
                $event->idSolicitud,
                $event->clasificacion_id,
                $event->plantilla_id,
                $event->idSolicitante,
                $event->idSolicitado,
                $event->idDocumento,
                $event->idParteAsociada,
                $event->idPago
            );
            
            Log::info('SaveResolution: Documento generado', [
                'resultado' => $resultado,
                'clasificacion_id' => $event->clasificacion_id
            ]);
        } catch (\Exception $e) {
            Log::error('SaveResolution: Error al generar documento', [
                'error' => $e->getMessage(),
                'linea' => $e->getLine(),
                'archivo' => basename($e->getFile()),
                'trace' => $e->getTraceAsString()
            ]);
            // NO lanzar la excepción para que no aborte la transacción principal
            // Los documentos pueden regenerarse después si es necesario
        }
    }
}
