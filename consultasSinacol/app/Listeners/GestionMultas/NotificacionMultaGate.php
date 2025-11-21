<?php

namespace App\Listeners\GestionMultas;

class NotificacionMultaGate
{
    public function handle($event)
    {

        // Se manda a signo si se cumplen estas reglas:

        //1. Si existe el tipo de notificación
        //2. El tipo de notificacion es multa
        //3. El atributo llamado "todos" del evento es un objeto

        // Si no se cumple la anterior regla entonces no se interrump la ejecución de la cadena de listeners
        if (isset($event->tipo_notificacion) && $event->tipo_notificacion === 'multa' && ! is_object($event->todos)) {
            return false;
        }
        // Regresamos de nuevo a false el atributo "todos" para no cambiar la naturaleza original del evento
        // Sólo se convirtió como objeto para detectar que proviene del Módulo GM y proceder con su envío
        // Si no es un objeto quiere decir que no proviene del GM y por lo tanto no se debe enviar a signo.
        $event->todos = false;
    }
}
