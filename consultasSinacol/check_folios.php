<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "Verificando folios AMG/CI/2025/010000-010019:\n";
echo str_repeat("=", 60) . "\n\n";

for ($i = 10000; $i <= 10019; $i++) {
    $folio = sprintf("AMG/CI/2025/%06d", $i);
    $expediente = App\Expediente::where('folio', $folio)->first();
    
    if ($expediente) {
        $solicitud = $expediente->solicitud;
        echo "✓ EXISTE: $folio\n";
        echo "  - Consecutivo DB: {$expediente->consecutivo}\n";
        echo "  - Solicitud ID: {$expediente->solicitud_id}\n";
        echo "  - Centro ID: " . ($solicitud ? $solicitud->centro_id : 'N/A') . "\n";
        echo "  - Fecha creación: {$expediente->created_at}\n\n";
    }
}

echo "\nMáximo consecutivo en tabla expedientes (AMG 2025):\n";
$max = App\Expediente::where('anio', 2025)
    ->where('folio', 'like', 'AMG/CI/2025/%')
    ->max('consecutivo');
echo "Max consecutivo: " . ($max ?? 'ninguno') . "\n\n";

echo "Último expediente por orderBy consecutivo DESC (STRING SORT):\n";
$ultimo = App\Expediente::where('anio', 2025)
    ->where('folio', 'like', 'AMG/CI/2025/%')
    ->orderBy('consecutivo', 'desc')
    ->first();
    
if ($ultimo) {
    echo "- Folio: {$ultimo->folio}\n";
    echo "- Consecutivo: {$ultimo->consecutivo}\n";
    echo "- ID: {$ultimo->id}\n";
    echo "- Created: {$ultimo->created_at}\n";
}

echo "\nÚltimo expediente por orderByRaw CAST (NUMERIC SORT):\n";
$ultimo_numeric = App\Expediente::where('anio', 2025)
    ->where('folio', 'like', 'AMG/CI/2025/%')
    ->orderByRaw('CAST(consecutivo AS INTEGER) DESC')
    ->first();
    
if ($ultimo_numeric) {
    echo "- Folio: {$ultimo_numeric->folio}\n";
    echo "- Consecutivo: {$ultimo_numeric->consecutivo}\n";
    echo "- ID: {$ultimo_numeric->id}\n";
    echo "- Created: {$ultimo_numeric->created_at}\n";
    echo "\n✅ ESTE es el correcto - próximo consecutivo debería ser: " . ($ultimo_numeric->consecutivo + 1) . "\n";
}
