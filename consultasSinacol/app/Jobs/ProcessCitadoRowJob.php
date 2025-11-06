<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\CreateSolicitudFromCitadoService; // Tu servicio
use Illuminate\Support\Facades\Log;

class ProcessCitadoRowJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $rowData;

    /**
     * Crea una nueva instancia del Job.
     */
    public function __construct(array $rowData)
    {
        $this->rowData = $rowData;
    }

    /**
     * Ejecuta el Job.
     */
    public function handle(CreateSolicitudFromCitadoService $createSolicitudService)
    {
        try {
            // El Job llama al servicio con los datos de la fila.
            $solicitud = $createSolicitudService->create($this->rowData);

            if (is_null($solicitud)) {
                Log::warning('No se pudo crear la solicitud (Servicio retornó null).', $this->rowData);
            } else {
                Log::info('Solicitud creada exitosamente con ID: ' . $solicitud->id, $this->rowData);
            }

        } catch (\Exception $e) {
            // Si el servicio falla, se registra el error y los datos que fallaron.
            Log::error('Fallo al procesar una fila del Excel: ' . $e->getMessage(), $this->rowData);
        }
    }
}