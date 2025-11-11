<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

// Verificar que las solicitudes fueron creadas correctamente
echo "=== VERIFICACIÓN DE SOLICITUDES CREADAS ===\n";

$solicitudes = DB::table('solicitudes')->orderBy('id', 'desc')->limit(5)->get();

foreach ($solicitudes as $sol) {
    echo sprintf(
        "\nID: %d | Folio: %d | Año: %d | Fecha Conflicto: %s | Estatus: %d\n",
        $sol->id,
        $sol->folio,
        $sol->anio,
        $sol->fecha_conflicto,
        $sol->estatus_solicitud_id
    );
    
    // Verificar partes (solicitante y citado)
    $partes = DB::table('partes')->where('solicitud_id', $sol->id)->get();
    foreach ($partes as $parte) {
        $tipoParteId = $parte->tipo_parte_id;
        $tipoParteNombre = ($tipoParteId == 1) ? 'SOLICITANTE' : (($tipoParteId == 2) ? 'CITADO' : 'OTRO');
        echo "  ├─ Parte: $tipoParteNombre (ID $parte->id) | Nombre: {$parte->nombre} {$parte->primer_apellido}\n";
        
        // Verificar contactos
        $contactos = DB::table('contactos')
            ->where('contactable_id', $parte->id)
            ->where('contactable_type', 'App\Parte')
            ->get();
        foreach ($contactos as $cont) {
            echo "  │  └─ Contacto: {$cont->contacto}\n";
        }
        
        // Verificar domicilios
        $domicilios = DB::table('domicilios')
            ->where('domiciliable_id', $parte->id)
            ->where('domiciliable_type', 'App\Parte')
            ->get();
        foreach ($domicilios as $dom) {
            echo "  │  └─ Domicilio: {$dom->vialidad} #{$dom->num_ext}, {$dom->municipio}\n";
        }
    }
}

echo "\n=== LOGS RECIENTES ===\n";
if (file_exists('storage/logs/laravel.log')) {
    $log = file_get_contents('storage/logs/laravel.log');
    $lines = explode("\n", $log);
    $lastLines = array_slice($lines, -5);
    foreach ($lastLines as $line) {
        if (!empty($line)) {
            if (strpos($line, 'ERROR') !== false) {
                echo "[ERROR] " . substr($line, 0, 200) . "\n";
            } else if (strpos($line, 'INFO') !== false) {
                echo "[INFO] " . substr($line, 0, 200) . "\n";
            }
        }
    }
}

echo "\n✅ Verificación completada.\n";
