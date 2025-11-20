<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RatificacionRealizada
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public $audiencia_id;

    public $tipo_notificacion;

    public $parte_id;

    public $todos;

    public function __construct($audiencia_id, $tipo_notificacion, $todos = true, $parte_id = null)
    {
        $this->audiencia_id = $audiencia_id;
        $this->tipo_notificacion = $tipo_notificacion;
        $this->parte_id = $parte_id;
        $this->todos = $todos;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn(): array
    {
        return new PrivateChannel('channel-name');
    }
}
