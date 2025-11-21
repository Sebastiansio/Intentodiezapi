<?php

namespace App\Services\GestionMultas;

use Illuminate\Support\Facades\Log;

/**
 * Class GMLogService
 *
 * Esta clase se encarga de manejar los mensajes de registro (logs) de la aplicación.
 */
class GMLogService
{
    /**
     * Método mágico que se llama cuando se intenta llamar a un método que no existe en esta clase.
     *
     * Este método se encarga de registrar los mensajes en el canal 'gm' de Laravel. Si se le pasan más de
     * dos argumentos, el primer argumento se utiliza como cadena de formato y los argumentos restantes
     * se sustituyen en esa cadena.
     *
     * @param  string  $method  Nombre del método que se intenta llamar.
     * @param  array  $arguments  Argumentos que se pasaron al método.
     */
    public function __call(string $method, array $arguments)
    {
        if (config('gestion-multas.debug')) {

            $trace = debug_backtrace(null, 2);
            $lineNumber = $trace[1]['line'] ?? '';
            $file = $trace[1]['file'] ?? '';

            if (! method_exists($this, $method)) {
                if (count($arguments) >= 2) {
                    $format = array_shift($arguments);
                    $msg = sprintf($format, ...$arguments);
                } else {
                    $msg = $arguments[0] ?? '';
                }
                Log::channel('gm')->$method(sprintf('[%s L:%s] %s', $file, $lineNumber, $msg));
            }
        }
    }
}
