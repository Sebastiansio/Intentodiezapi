<?php
/**
 * Script para probar el parseo de conceptos desde CSV
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$csvFile = 'citado_completo_corregido.csv';

if (!file_exists($csvFile)) {
    echo "Error: No se encontró el archivo '$csvFile'\n";
    exit(1);
}

echo "═══════════════════════════════════════════════════════════════\n";
echo "  TEST: PARSEO DE CONCEPTOS DESDE CSV\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

$file = fopen($csvFile, 'r');
if (!$file) {
    echo "Error: No se pudo abrir el archivo.\n";
    exit(1);
}

// Leer encabezados
$headers = fgetcsv($file);
if (!$headers) {
    echo "Error: El archivo está vacío.\n";
    exit(1);
}

echo "Total de columnas: " . count($headers) . "\n\n";

// Detectar columnas de conceptos
$conceptoColumns = [];
foreach ($headers as $index => $header) {
    if (preg_match('/^concepto[_\s]*(\d+)$/i', trim($header), $matches)) {
        $conceptoColumns[$matches[1]] = $index;
    }
}

if (empty($conceptoColumns)) {
    echo "⚠️  NO se encontraron columnas de conceptos en el CSV\n";
    echo "Columnas detectadas: " . implode(', ', array_slice($headers, 0, 10)) . "...\n\n";
} else {
    echo "✓ Columnas de conceptos detectadas:\n";
    foreach ($conceptoColumns as $conceptoId => $colIndex) {
        echo "  - Concepto ID {$conceptoId}: columna #{$colIndex} ({$headers[$colIndex]})\n";
    }
    echo "\n";
}

echo str_repeat("-", 70) . "\n\n";

// Leer primera fila de datos (no vacía)
$primeraFila = null;
$rowNum = 0;
while (($row = fgetcsv($file)) !== false) {
    $rowNum++;
    // Saltar filas completamente vacías
    if (count(array_filter($row)) > 0) {
        $primeraFila = $row;
        break;
    }
}

if (!$primeraFila) {
    echo "⚠️  No se encontraron filas con datos en el CSV\n";
    exit(0);
}

echo "Primera fila con datos (fila #{$rowNum}):\n";
echo str_repeat("-", 70) . "\n";

// Crear array asociativo
$data = array_combine($headers, $primeraFila);

// Mostrar datos básicos
echo "Nombre: " . ($data['nombre'] ?? 'N/A') . "\n";
echo "CURP: " . ($data['curp'] ?? 'N/A') . "\n";
echo "Salario: " . ($data['salario'] ?? 'N/A') . "\n\n";

// Mostrar conceptos
echo "Conceptos detectados:\n";
echo str_repeat("-", 70) . "\n";

$conceptos = [];
$totalConceptos = 0;

foreach ($conceptoColumns as $conceptoId => $colIndex) {
    $valor = $primeraFila[$colIndex] ?? '';
    $monto = floatval(str_replace(',', '', $valor));
    
    if ($monto > 0) {
        $totalConceptos += $monto;
        $conceptos[] = [
            'concepto_id' => (int)$conceptoId,
            'monto' => $monto
        ];
        
        $signo = ($conceptoId == 13) ? '-' : '+';
        echo sprintf("  %s Concepto %d: \$%s\n", $signo, $conceptoId, number_format($monto, 2));
    } else {
        echo sprintf("  · Concepto %d: (vacío o 0)\n", $conceptoId);
    }
}

echo "\n" . str_repeat("-", 70) . "\n";
echo "Total de conceptos con monto: " . count($conceptos) . "\n";
echo "Suma bruta: \$" . number_format($totalConceptos, 2) . "\n\n";

if (!empty($conceptos)) {
    echo "✓ Array de conceptos generado correctamente\n";
    echo "Formato esperado por el servicio:\n\n";
    echo json_encode(['conceptos' => $conceptos], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    echo "\n\n";
} else {
    echo "⚠️  No se generaron conceptos (todos están vacíos o son 0)\n\n";
}

fclose($file);

echo str_repeat("═", 70) . "\n";
echo "TEST COMPLETADO\n";
echo str_repeat("═", 70) . "\n";
