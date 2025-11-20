<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

echo "═══════════════════════════════════════════════════════════════\n";
echo "  VERIFICACIÓN DE RELACIÓN: CONCEPTOS → PARTE\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

// Buscar último concepto creado
$concepto = DB::table('resolucion_parte_conceptos')
    ->orderBy('id', 'desc')
    ->first();

if (!$concepto) {
    echo "No se encontraron conceptos en la base de datos.\n";
    exit(1);
}

echo "Último Concepto Creado:\n";
echo str_repeat("-", 70) . "\n";
echo "ID: {$concepto->id}\n";
echo "Concepto de Pago ID: {$concepto->concepto_pago_resoluciones_id}\n";
echo "Monto: \$" . number_format($concepto->monto, 2) . "\n";
echo "Audiencia Parte ID: {$concepto->audiencia_parte_id}\n\n";

// Buscar la audiencia_parte
$audiencia_parte = DB::table('audiencias_partes')
    ->where('id', $concepto->audiencia_parte_id)
    ->first();

if (!$audiencia_parte) {
    echo "✗ ERROR: No se encontró audiencia_parte con ID {$concepto->audiencia_parte_id}\n";
    exit(1);
}

echo "Audiencia Parte:\n";
echo str_repeat("-", 70) . "\n";
echo "ID: {$audiencia_parte->id}\n";
echo "Parte ID: {$audiencia_parte->parte_id}\n";
echo "Audiencia ID: {$audiencia_parte->audiencia_id}\n\n";

// Buscar la parte (citado)
$parte = DB::table('partes')
    ->where('id', $audiencia_parte->parte_id)
    ->first();

if (!$parte) {
    echo "✗ ERROR: No se encontró parte con ID {$audiencia_parte->parte_id}\n";
    exit(1);
}

echo "Parte (Citado):\n";
echo str_repeat("-", 70) . "\n";
echo "ID: {$parte->id}\n";
echo "Nombre: {$parte->nombre} {$parte->primer_apellido} {$parte->segundo_apellido}\n";
echo "CURP: {$parte->curp}\n";
echo "Tipo Parte: " . ($parte->tipo_parte_id == 2 ? 'CITADO ✓' : "Tipo {$parte->tipo_parte_id}") . "\n";
echo "Solicitud ID: {$parte->solicitud_id}\n\n";

// Buscar todos los conceptos de esta parte
$conceptos_parte = DB::table('resolucion_parte_conceptos as rpc')
    ->join('concepto_pago_resoluciones as cpr', 'rpc.concepto_pago_resoluciones_id', '=', 'cpr.id')
    ->where('rpc.audiencia_parte_id', $audiencia_parte->id)
    ->select('rpc.id', 'cpr.nombre', 'rpc.monto', 'cpr.id as concepto_id')
    ->get();

echo "Todos los Conceptos de esta Parte:\n";
echo str_repeat("-", 70) . "\n";

if ($conceptos_parte->isEmpty()) {
    echo "✗ No se encontraron conceptos\n";
} else {
    $total = 0;
    foreach ($conceptos_parte as $c) {
        $signo = $c->concepto_id == 13 ? '-' : '+';
        echo sprintf("  %s [%d] %-45s \$%s\n", 
            $signo,
            $c->concepto_id,
            $c->nombre,
            number_format($c->monto, 2)
        );
        
        if ($c->concepto_id == 13) {
            $total -= $c->monto;
        } else {
            $total += $c->monto;
        }
    }
    echo "\n  " . str_repeat("-", 68) . "\n";
    echo sprintf("  TOTAL: \$%s\n", number_format($total, 2));
}

echo "\n" . str_repeat("═", 70) . "\n";
echo "✓ VERIFICACIÓN COMPLETA\n";
echo str_repeat("═", 70) . "\n\n";

echo "CADENA DE RELACIONES:\n";
echo "  Concepto (ID: {$concepto->id})\n";
echo "    → audiencia_parte_id: {$concepto->audiencia_parte_id}\n";
echo "      → Audiencia Parte (ID: {$audiencia_parte->id})\n";
echo "        → parte_id: {$audiencia_parte->parte_id}\n";
echo "          → Parte/Citado: {$parte->nombre} {$parte->primer_apellido}\n";
echo "            CURP: {$parte->curp}\n";
echo "            Solicitud ID: {$parte->solicitud_id}\n\n";

echo "✓ Los conceptos SÍ están correctamente relacionados con la parte (citado)\n";
