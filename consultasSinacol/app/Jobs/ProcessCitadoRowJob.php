<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\CreateSolicitudFromCitadoService; // Tu servicio
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

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
                Log::warning('No se pudo crear la solicitud (Servicio retornó null).', [
                    'curp' => $this->rowData['curp'] ?? 'N/A',
                    'nombre' => $this->rowData['nombre'] ?? 'N/A'
                ]);
            } else {
                Log::info('Solicitud creada exitosamente con ID: ' . $solicitud->id, [
                    'solicitud_id' => $solicitud->id,
                    'curp' => $this->rowData['curp'] ?? 'N/A'
                ]);
            }

        } catch (\Exception $e) {
            // Si el servicio falla, se registra el error
            Log::error('Fallo al procesar una fila del Excel: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'curp' => $this->rowData['curp'] ?? 'N/A',
                'nombre' => $this->rowData['nombre'] ?? 'N/A',
                'linea' => $e->getLine(),
                'archivo' => $e->getFile()
            ]);

            // Limpiar el estado de la conexión sin lanzar excepciones
            try {
                // Si estamos en una transacción, intentar rollback
                if (DB::transactionLevel() > 0) {
                    DB::rollBack();
                }
            } catch (\Exception $_) {
                // Ignorar errores del rollback
            }

            // NO relanzar la excepción para que la transacción padre de Excel continúe
            // y las demás filas se puedan procesar
            // throw $e; // COMENTADO - Esto causaba el error 25P02
        }
    }
}