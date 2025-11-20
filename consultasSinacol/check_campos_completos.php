<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "═══════════════════════════════════════════════════════════════\n";
echo "  ANÁLISIS DE CAMPOS CSV vs SERVICIO\n";
echo "═══════════════════════════════════════════════════════════════\n\n";

// Leer headers del CSV
$csv = fopen('citado_completo_corregido.csv', 'r');
$headers = fgetcsv($csv);
fclose($csv);

// Campos que usa CreateSolicitudFromCitadoService según el código
$camposUsados = [
    // SOLICITUD
    'fecha_conflicto' => 'Solicitud',
    'giro_comercial_id' => 'Solicitud',
    'tipo_solicitud_id' => 'Solicitud (opcional)',
    
    // PARTE CITADO (persona física)
    'nombre' => 'Parte Citado',
    'primer_apellido' => 'Parte Citado',
    'segundo_apellido' => 'Parte Citado',
    'curp' => 'Parte Citado',
    'rfc' => 'Parte Citado (opcional)',
    'genero' => 'Persona (opcional)',
    'nacionalidad' => 'Persona (opcional)',
    'estado' => 'Domicilio',
    
    // DOMICILIO CITADO
    'tipo_vialidad' => 'Domicilio',
    'vialidad' => 'Domicilio',
    'num_ext' => 'Domicilio',
    'num_int' => 'Domicilio',
    'colonia' => 'Domicilio',
    'municipio' => 'Domicilio',
    'cp' => 'Domicilio',
    
    // CONTACTO CITADO
    'correo' => 'Contacto',
    'telefono' => 'Contacto',
    'tipo_contacto' => 'Contacto',
    
    // DATOS LABORALES
    'nss' => 'Datos Laborales',
    'puesto' => 'Datos Laborales (ocupacion_id)',
    'salario' => 'Datos Laborales',
    'periocidad' => 'Datos Laborales (periodicidad_id)',
    'horas_sem' => 'Datos Laborales',
    'fecha_ingreso' => 'Datos Laborales',
    'fecha_salida' => 'Datos Laborales',
    'jornada' => 'Datos Laborales (jornada_id)',
    
    // INSTRUMENTO (objeto solicitud)
    'instrumento' => 'Objeto Solicitud',
    'detalle_instrumento' => 'Objeto Solicitud',
    'fecha_instrumento' => 'Objeto Solicitud',
    
    // CONCEPTOS DE PAGO
    'concepto_1' => 'Conceptos Pago (ID 1)',
    'concepto_2' => 'Conceptos Pago (ID 2)',
    'concepto_3' => 'Conceptos Pago (ID 3)',
    'concepto_4' => 'Conceptos Pago (ID 4)',
    'concepto_5' => 'Conceptos Pago (ID 5)',
    'concepto_13' => 'Conceptos Pago (ID 13 - Deducción)',
];

echo "CAMPOS DEL CSV QUE SE ESTÁN USANDO:\n";
echo str_repeat("-", 70) . "\n";

$encontrados = [];
$noEncontrados = [];

foreach ($camposUsados as $campo => $descripcion) {
    // Buscar coincidencia (ignorar mayúsculas y acentos)
    $encontrado = false;
    foreach ($headers as $header) {
        $headerNorm = strtolower(str_replace(['á','é','í','ó','ú','Á','É','Í','Ó','Ú',' '], 
                                             ['a','e','i','o','u','a','e','i','o','u','_'], 
                                             $header));
        $campoNorm = strtolower($campo);
        
        if ($headerNorm === $campoNorm || strpos($headerNorm, $campoNorm) !== false) {
            echo "  ✓ {$campo} → {$header} ({$descripcion})\n";
            $encontrados[] = $campo;
            $encontrado = true;
            break;
        }
    }
    
    if (!$encontrado) {
        $noEncontrados[] = [$campo, $descripcion];
    }
}

if (!empty($noEncontrados)) {
    echo "\n" . str_repeat("═", 70) . "\n";
    echo "⚠️  CAMPOS REQUERIDOS NO ENCONTRADOS EN EL CSV:\n";
    echo str_repeat("-", 70) . "\n";
    
    foreach ($noEncontrados as $item) {
        echo "  ✗ {$item[0]} ({$item[1]})\n";
    }
}

echo "\n" . str_repeat("═", 70) . "\n";
echo "CAMPOS DEL CSV NO UTILIZADOS:\n";
echo str_repeat("-", 70) . "\n";

foreach ($headers as $header) {
    $headerNorm = strtolower(str_replace(['á','é','í','ó','ú','Á','É','Í','Ó','Ú',' '], 
                                         ['a','e','i','o','u','a','e','i','o','u','_'], 
                                         $header));
    
    $usado = false;
    foreach ($camposUsados as $campo => $desc) {
        $campoNorm = strtolower($campo);
        if ($headerNorm === $campoNorm || strpos($headerNorm, $campoNorm) !== false) {
            $usado = true;
            break;
        }
    }
    
    if (!$usado) {
        echo "  • {$header}\n";
    }
}

echo "\n" . str_repeat("═", 70) . "\n";
echo "RESUMEN:\n";
echo "  Total campos en CSV: " . count($headers) . "\n";
echo "  Campos utilizados: " . count($encontrados) . "\n";
echo "  Campos faltantes: " . count($noEncontrados) . "\n";
echo "  Campos no utilizados: " . (count($headers) - count($encontrados)) . "\n";
echo str_repeat("═", 70) . "\n";
