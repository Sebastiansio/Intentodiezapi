<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;


class GenerateDocumentResolution
{
    use Dispatchable, InteractsWithSockets, SerializesModels;
    
    public $idAudiencia, 
        $idSolicitud, 
        $clasificacion_id,
        $plantilla_id, 
        $idSolicitante = null, 
        $idSolicitado = null, 
        $idDocumento = null,
        $idParteAsociada = null,
        $idPago = null;
    
    /**
     * Create a new event instance.
     * 
     * @param int $idAudiencia 
     * @param int $idSolicitud
     * @param int $clasificacion_id
     * @param int $plantilla_id
     * @param int|null $idSolicitante
     * @param int|null $idSolicitado
     * @param int|null $idDocumento
     * @param int|null $idParteAsociada
     * @param int|null $idPago
     * 
     * @return void
     */
    public function __construct(
        $idAudiencia, 
        $idSolicitud, 
        $clasificacion_id,
        $plantilla_id, 
        $idSolicitante = null, 
        $idSolicitado = null,
        $idDocumento = null,
        $idParteAsociada = null,
        $idPago = null
    )
    {
        $this->idAudiencia = $idAudiencia;
        $this->idSolicitud = $idSolicitud;
        $this->clasificacion_id = $clasificacion_id;
        $this->plantilla_id = $plantilla_id;
        $this->idSolicitante = $idSolicitante;
        $this->idSolicitado = $idSolicitado;
        $this->idDocumento = $idDocumento;
        $this->idParteAsociada = $idParteAsociada;
        $this->idPago = $idPago;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        return new PrivateChannel('channel-name');
    }
}
