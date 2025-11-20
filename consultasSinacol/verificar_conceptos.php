<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

// Buscar las últimas 3 solicitudes
$solicitudes = DB::table('solicitudes')
    ->orderBy('id', 'desc')
    ->limit(3)
    ->get(['id', 'folio', 'anio']);

echo "═══════════════════════════════════════════════════════════════\n";
echo "  VERIFICACIÓN DE CONCEPTOS DE PAGO IMPORTADOS\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

foreach ($solicitudes as $solicitud) {
    echo "Solicitud: {$solicitud->folio}/{$solicitud->anio} (ID: {$solicitud->id})\n";
    echo str_repeat("-", 70) . "\n";
    
    // Buscar expediente y audiencia
    $expediente = DB::table('expedientes')
        ->where('solicitud_id', $solicitud->id)
        ->first();
    
    if (!$expediente) {
        echo "  ✗ No tiene expediente\n\n";
        continue;
    }
    
    $audiencia = DB::table('audiencias')
        ->where('expediente_id', $expediente->id)
        ->first();
    
    if (!$audiencia) {
        echo "  ✗ No tiene audiencia\n\n";
        continue;
    }
    
    echo "  Expediente: {$expediente->folio}\n";
    echo "  Audiencia: {$audiencia->folio}\n\n";
    
    // Buscar audiencia_parte (citado)
    $audiencia_parte = DB::table('audiencias_partes')
        ->join('partes', 'audiencias_partes.parte_id', '=', 'partes.id')
        ->where('audiencias_partes.audiencia_id', $audiencia->id)
        ->where('partes.tipo_parte_id', 2) // Citado
        ->select('audiencias_partes.id', 'partes.nombre', 'partes.primer_apellido')
        ->first();
    
    if (!$audiencia_parte) {
        echo "  ✗ No se encontró audiencia_parte para citado\n\n";
        continue;
    }
    
    // Buscar conceptos de pago
    $conceptos = DB::table('resolucion_parte_conceptos as rpc')
        ->join('concepto_pago_resoluciones as cpr', 'rpc.concepto_pago_resoluciones_id', '=', 'cpr.id')
        ->where('rpc.audiencia_parte_id', $audiencia_parte->id)
        ->select('cpr.id as concepto_id', 'cpr.nombre as concepto_nombre', 'rpc.monto', 'rpc.dias')
        ->get();
    
    if ($conceptos->isEmpty()) {
        echo "  ✗ NO se encontraron conceptos de pago\n\n";
        continue;
    }
    
    echo "  ✓ Conceptos de pago encontrados: {$conceptos->count()}\n\n";
    
    $total = 0;
    foreach ($conceptos as $concepto) {
        $monto = floatval($concepto->monto);
        $signo = ($concepto->concepto_id == 13) ? '-' : '+';
        echo sprintf("    %s ID %d: %-40s $%s\n", 
            $signo,
            $concepto->concepto_id,
            $concepto->concepto_nombre,
            number_format($monto, 2)
        );
        
        if ($concepto->concepto_id == 13) {
            $total -= $monto;
        } else {
            $total += $monto;
        }
    }
    
    echo "\n  " . str_repeat("-", 68) . "\n";
    echo sprintf("  TOTAL:  $%s\n", number_format($total, 2));
    
    // Verificar pago diferido (comentado - tabla no existe)
    /*
    $pago_diferido = DB::table('resolucion_pago_diferidos')
        ->where('audiencia_id', $audiencia->id)
        ->first();
    */
    $pago_diferido = null;
    
    if ($pago_diferido) {
        echo sprintf("  ✓ Pago diferido: $%s (Estado: %s)\n", 
            number_format($pago_diferido->monto, 2),
            $pago_diferido->code_estatus
        );
    } else {
        echo "  ✗ No se creó pago diferido\n";
    }
    
    echo "\n";
}
