<?php
/**
 * Parser de CSV con Conceptos de Pago
 * 
 * Este script lee un CSV con columnas de conceptos (concepto_1, concepto_2, etc.)
 * y las convierte al formato esperado por CreateSolicitudFromCitadoService
 */

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\CreateSolicitudFromCitadoService;
use Illuminate\Support\Facades\Log;

if ($argc < 2) {
    echo "Uso: php import_conceptos_csv.php <ruta_archivo.csv>\n";
    echo "Ejemplo: php import_conceptos_csv.php ejemplo_conceptos.csv\n";
    exit(1);
}

$csvFile = $argv[1];

if (!file_exists($csvFile)) {
    echo "Error: El archivo '$csvFile' no existe.\n";
    exit(1);
}

echo "═══════════════════════════════════════════════════════════════\n";
echo "  IMPORTACIÓN DE CONVENIOS CON CONCEPTOS DESDE CSV\n";
echo "═══════════════════════════════════════════════════════════════\n\n";
echo "Archivo: $csvFile\n\n";

$file = fopen($csvFile, 'r');
if (!$file) {
    echo "Error: No se pudo abrir el archivo.\n";
    exit(1);
}

// Leer encabezados
$headers = fgetcsv($file);
if (!$headers) {
    echo "Error: El archivo está vacío o mal formado.\n";
    exit(1);
}

echo "Columnas detectadas: " . count($headers) . "\n";
echo "Encabezados: " . implode(', ', $headers) . "\n\n";

// Detectar columnas de conceptos (concepto_1, concepto_2, etc.)
$conceptoColumns = [];
foreach ($headers as $index => $header) {
    if (preg_match('/^concepto_(\d+)$/i', $header, $matches)) {
        $conceptoColumns[$matches[1]] = $index; // [concepto_id => column_index]
    }
}

if (empty($conceptoColumns)) {
    echo "⚠️  ADVERTENCIA: No se encontraron columnas de conceptos (concepto_1, concepto_2, etc.)\n";
    echo "   Se usarán conceptos por defecto.\n\n";
} else {
    echo "Conceptos detectados: " . implode(', ', array_keys($conceptoColumns)) . "\n\n";
}

echo str_repeat("-", 70) . "\n";

$service = new CreateSolicitudFromCitadoService();
$totalProcesadas = 0;
$totalExitosas = 0;
$totalErrores = 0;

// Procesar cada fila
while (($row = fgetcsv($file)) !== false) {
    $totalProcesadas++;
    
    // Crear array asociativo con los encabezados
    $data = array_combine($headers, $row);
    
    // Parsear conceptos de pago
    $conceptos = [];
    foreach ($conceptoColumns as $conceptoId => $columnIndex) {
        $monto = isset($row[$columnIndex]) ? floatval($row[$columnIndex]) : 0;
        
        // Solo agregar si el monto es mayor a 0
        if ($monto > 0) {
            $conceptos[] = [
                'concepto_id' => (int)$conceptoId,
                'monto' => $monto,
                'dias' => null,
                'otro' => ''
            ];
        }
    }
    
    // Preparar datos para el servicio
    $citadoData = [
        'nombre' => $data['nombre'] ?? '',
        'primer_apellido' => $data['primer_apellido'] ?? '',
        'segundo_apellido' => $data['segundo_apellido'] ?? '',
        'curp' => $data['curp'] ?? '',
        'fecha_conflicto' => $data['fecha_conflicto'] ?? null,
        'fecha_ingreso' => $data['fecha_ingreso'] ?? '2020-01-01', // REQUERIDO
        'salario' => isset($data['salario']) ? floatval($data['salario']) : null,
        'conceptos' => $conceptos, // ⭐ Agregar conceptos parseados
        
        // Datos laborales adicionales (puedes agregarlos al CSV si los tienes)
        'puesto' => $data['puesto'] ?? 'Empleado',
        'periocidad' => $data['periocidad'] ?? 'DIARIO',
        'horas_sem' => $data['horas_sem'] ?? '48',
        'jornada' => $data['jornada'] ?? 'Diurna',
        'labora_actualmente' => $data['labora_actualmente'] ?? 'NO',
        'horario_laboral' => $data['horario_laboral'] ?? '09:00-18:00',
        'horario_comida' => $data['horario_comida'] ?? '14:00-15:00',
        'dias_descanso' => $data['dias_descanso'] ?? 'DOMINGO',
        'dias_vacaciones' => $data['dias_vacaciones'] ?? '6',
        'dias_aguinaldo' => $data['dias_aguinaldo'] ?? '15',
        'prestaciones_adicionales' => $data['prestaciones_adicionales'] ?? 'NINGUNA',
        
        // Datos personales adicionales
        'rfc' => $data['rfc'] ?? '',
        'nss' => $data['nss'] ?? '',
        'genero' => $data['genero'] ?? 'MASCULINO',
        'nacionalidad' => $data['nacionalidad'] ?? 'MEXICANA',
        'correo' => $data['correo'] ?? '',
        'telefono' => $data['telefono'] ?? '',
        'fecha_de_nacimiento' => $data['fecha_de_nacimiento'] ?? '1990-01-01',
        
        // Domicilio
        'estado' => $data['estado'] ?? 'JALISCO',
        'municipio' => $data['municipio'] ?? 'GUADALAJARA',
        'cp' => $data['cp'] ?? '44100',
        'tipo_vialidad' => $data['tipo_vialidad'] ?? 'CALLE',
        'vialidad' => $data['vialidad'] ?? 'PRINCIPAL',
        'num_ext' => $data['num_ext'] ?? '1',
        'colonia' => $data['colonia'] ?? 'CENTRO',
        
        // Datos por defecto
        'tipo_solicitud_id' => $data['tipo_solicitud_id'] ?? 2,
        'giro_comercial_id' => $data['giro_comercial_id'] ?? 'Servicios',
        'objeto_solicitudes' => isset($data['objeto_solicitudes']) ? explode('|', $data['objeto_solicitudes']) : ['31'],
        'virtual' => $data['virtual'] ?? '0',
        
        // Agregar solicitante por defecto (deberías tenerlo en el CSV o configurarlo)
        'solicitante' => [
            'tipo_persona_id' => '2',
            'nombre_comercial' => $data['solicitante_nombre'] ?? 'Empresa Genérica SA de CV',
            'rfc' => $data['solicitante_rfc'] ?? 'XAXX010101000',
            'contactos' => [
                ['contacto' => $data['solicitante_telefono'] ?? '3312345678', 'tipo_contacto_id' => '1'],
                ['contacto' => $data['solicitante_email'] ?? 'empresa@example.com', 'tipo_contacto_id' => '3']
            ],
            'domicilios' => [
                [
                    'estado_id' => $data['solicitante_estado_id'] ?? '14',
                    'municipio' => $data['solicitante_municipio'] ?? 'Guadalajara',
                    'cp' => $data['solicitante_cp'] ?? '44100',
                    'tipo_vialidad_id' => '3',
                    'vialidad' => $data['solicitante_calle'] ?? 'Av. Principal',
                    'num_ext' => $data['solicitante_num_ext'] ?? '100',
                    'asentamiento' => $data['solicitante_colonia'] ?? 'Centro'
                ]
            ]
        ]
    ];
    
    try {
        $solicitud = $service->create($citadoData);
        
        if ($solicitud) {
            $totalExitosas++;
            $montoTotal = array_sum(array_column($conceptos, 'monto'));
            echo sprintf(
                "✓ [%d] %s %s - Folio: %s - Conceptos: %d - Total: $%s\n",
                $totalProcesadas,
                $data['nombre'],
                $data['primer_apellido'],
                $solicitud->folio . '/' . $solicitud->anio,
                count($conceptos),
                number_format($montoTotal, 2)
            );
        } else {
            $totalErrores++;
            echo sprintf(
                "✗ [%d] %s %s - Error: Servicio retornó null\n",
                $totalProcesadas,
                $data['nombre'],
                $data['primer_apellido']
            );
        }
        
    } catch (\Exception $e) {
        $totalErrores++;
        echo sprintf(
            "✗ [%d] %s %s - Error: %s\n",
            $totalProcesadas,
            $data['nombre'],
            $data['primer_apellido'],
            $e->getMessage()
        );
        Log::error('Error importando desde CSV', [
            'fila' => $totalProcesadas,
            'curp' => $data['curp'] ?? 'N/A',
            'error' => $e->getMessage()
        ]);
    }
}

fclose($file);

echo "\n" . str_repeat("═", 70) . "\n";
echo "RESUMEN DE IMPORTACIÓN\n";
echo str_repeat("═", 70) . "\n";
echo sprintf("Total procesadas:  %d\n", $totalProcesadas);
echo sprintf("Exitosas:          %d (%.1f%%)\n", $totalExitosas, ($totalExitosas/$totalProcesadas)*100);
echo sprintf("Con errores:       %d (%.1f%%)\n", $totalErrores, ($totalErrores/$totalProcesadas)*100);
echo str_repeat("═", 70) . "\n";
