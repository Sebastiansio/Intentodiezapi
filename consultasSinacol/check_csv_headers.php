<?php

$csv = fopen('citado_completo_corregido.csv', 'r');
$headers = fgetcsv($csv);
fclose($csv);

echo "═══════════════════════════════════════════════════════════════\n";
echo "  COLUMNAS DEL CSV (Total: " . count($headers) . ")\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

foreach ($headers as $i => $h) {
    echo ($i + 1) . ". " . $h . "\n";
}
