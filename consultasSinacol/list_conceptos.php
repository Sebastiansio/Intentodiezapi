<?php
/**
 * Script para listar todos los conceptos de pago disponibles
 * Útil para saber qué IDs usar en el CSV
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "═══════════════════════════════════════════════════════════════\n";
echo "  CONCEPTOS DE PAGO DISPONIBLES EN EL SISTEMA\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

$conceptos = DB::table('concepto_pago_resoluciones')
    ->whereNull('deleted_at')
    ->orderBy('id')
    ->get(['id', 'nombre']);

if ($conceptos->isEmpty()) {
    echo "No se encontraron conceptos de pago en la base de datos.\n";
    exit(1);
}

echo sprintf("%-5s | %-40s\n", "ID", "NOMBRE DEL CONCEPTO");
echo str_repeat("-", 70) . "\n";

foreach ($conceptos as $concepto) {
    echo sprintf("%-5d | %-40s\n", $concepto->id, $concepto->nombre);
}

echo "\n" . str_repeat("═", 70) . "\n";
echo "Total de conceptos: " . $conceptos->count() . "\n\n";

echo "NOTA: El concepto ID 13 (si existe) es para DEDUCCIONES y se resta del total.\n";
echo "      Todos los demás conceptos se suman al monto total.\n\n";

// Buscar específicamente deducciones
$deduccion = $conceptos->firstWhere('id', 13);
if ($deduccion) {
    echo "⚠️  DEDUCCIÓN encontrada: ID {$deduccion->id} - {$deduccion->nombre}\n";
}
