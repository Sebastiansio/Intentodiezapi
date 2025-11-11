<?php
// Script de prueba para ver los datos antes de procesarlos

require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

// Ver los últimos errores
echo "=== ÚLTIMOS ERRORES EN LOG ===\n";
$logFile = 'storage/logs/laravel.log';
if (file_exists($logFile)) {
    $logContent = file_get_contents($logFile);
    $lines = explode("\n", $logContent);
    $errorLines = array_filter($lines, fn($l) => strpos($l, 'ERROR') !== false);
    $lastError = end($errorLines);
    if ($lastError) {
        echo substr($lastError, 0, 300) . "...\n\n";
    } else {
        echo "Sin errores en el log.\n\n";
    }
} else {
    echo "El log no existe aún (primera ejecución).\n\n";
}

$testData = [
    'fecha_conflicto' => '2025-03-26',
    'tipo_solicitud_id' => '2',
    'giro_comercial_id' => 'Caza y captura',
    'objeto_solicitudes' => ['31'],
    'virtual' => '0',
    'curp' => 'AUSI910110HMCCLV07',
    'nombre' => 'XOCHITL',
    'primer_apellido' => 'PEREX',
    'segundo_apellido' => 'SALAZAR',
    'fecha_de_nacimiento' => '1987-01-09',
    'edad' => '32',
    'rfc' => 'AUSI910110AZ7',
    'genero' => 'MASCULINO',
    'nacionalidad' => 'MEXICANA',
    'correo' => 'genrico@gmail.com',
    'telefono' => '7222037407',
    'estado' => 'ESTADO DE MÉXICO',
    'tipo_vialidad' => 'CALLE',
    'vialidad' => 'ISABEL LA CATOLICA',
    'num_ext' => '115',
    'colonia' => 'VALLE',
    'municipio' => 'MOLINOS',
    'cp' => '52371',
    'nss' => '16099149748',
    'puesto' => 'Ensamblador',
    'salario' => 336.3,
    'periocidad' => 'DIARIO',
    'horas_sem' => '48',
    'fecha_ingreso' => '2013-02-26',
    'jornada' => 'Diurna',
    'labora_actualmente' => 'SI',
    'horario_laboral' => '11:00-19:01',
    'horario_comida' => '12:00-13:01',
    'dias_descanso' => '2 DÍAS LOS LUNES',
    'dias_vacaciones' => '10',
    'dias_aguinaldo' => '20',
    'prestaciones_adicionales' => 'NINGUNA OTRA PRESTACIÓN',
    'tipo_contacto' => 'TELEFONO',
    'solicitante' => [
        'tipo_persona_id' => '2',
        'nombre_comercial' => 'Empresa agme',
        'rfc' => 'XAXX010101000',
        'contactos' => [
            ['contacto' => '3324930611', 'tipo_contacto_id' => '1'],
            ['contacto' => 'sebastian@gmail.com', 'tipo_contacto_id' => '3']
        ],
        'domicilios' => [
            [
                'estado_id' => '14',
                'municipio' => 'zapopan',
                'cp' => '44050',
                'tipo_vialidad_id' => '3',
                'vialidad' => 'Xochitl',
                'num_ext' => '4',
                'num_int' => 'A',
                'asentamiento' => 'Ciudad del sol'
            ]
        ]
    ]
];

try {
    $service = new \App\Services\CreateSolicitudFromCitadoService();
    $result = $service->create($testData);
    if ($result) {
        echo "✓ Solicitud creada exitosamente con ID: " . $result->id . "\n";
        echo "  Folio: " . $result->folio . "\n";
        echo "  Año: " . $result->anio . "\n";
    } else {
        echo "✗ Servicio retornó null\n";
    }
} catch (\Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    echo "Código: " . $e->getCode() . "\n";
    echo "Archivo: " . $e->getFile() . ":" . $e->getLine() . "\n";
}
