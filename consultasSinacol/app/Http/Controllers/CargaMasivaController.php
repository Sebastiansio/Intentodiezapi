<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\CitadoImport;
use App\TipoSolicitud;
use App\GiroComercial;
use App\ObjetoSolicitud;
use App\Estado;
use App\TipoVialidad;
use App\Conciliador;


class CargaMasivaController extends Controller
{
    public function showUploadForm()
    {
        // Mostrar la vista completa que incluye los datos del solicitante
        // Cargamos datos mínimos para los select (si fallan, pasamos arrays vacíos)
        $tipo_solicitudes = TipoSolicitud::pluck('nombre','id')->toArray();
        $giros_comerciales = GiroComercial::pluck('nombre','id')->toArray();
        $objeto_solicitudes = ObjetoSolicitud::pluck('nombre','id')->toArray();
        $estados = Estado::all();
        $tipo_vialidades = TipoVialidad::all();
        
        // Obtener conciliadores activos
        $conciliadores = Conciliador::select('id', 'persona_id')
            ->with(['persona:id,nombre,primer_apellido,segundo_apellido'])
            ->whereHas('persona')
            ->get()
            ->map(function($conciliador) {
                $persona = $conciliador->persona;
                return [
                    'id' => $conciliador->id,
                    'nombre_completo' => trim($persona->nombre . ' ' . $persona->primer_apellido . ' ' . $persona->segundo_apellido)
                ];
            });

        return view('solicitante.carga_masiva', compact('tipo_solicitudes','giros_comerciales','objeto_solicitudes','estados','tipo_vialidades','conciliadores'));
    }

    public function handleUpload(Request $request)
    {
        // Validamos el archivo y los campos mínimos del formulario
        $request->validate([
            'archivo_citados' => 'required|mimes:xlsx,csv,txt',
            'fecha_conflicto' => 'required|string',
            'tipo_solicitud_id' => 'required|integer',
            'giro_comercial_id' => 'required', // aceptamos id o nombre
            'objeto_solicitudes' => 'required|array',
        ]);

        try {
            // Recolectamos los datos del solicitante y campos comunes
            $solicitante = $request->input('solicitante', []);
            $common = $request->only(['fecha_conflicto','tipo_solicitud_id','giro_comercial_id','objeto_solicitudes','virtual','conciliador_id']);

            // Recolectar datos del representante legal si existen
            $representante = null;
            if ($request->has('tiene_representante') && $request->input('tiene_representante') == '1') {
                $representante = $request->input('representante', []);
                
                \Log::info('CargaMasiva: Datos del representante legal detectados', [
                    'nombre' => $representante['nombre'] ?? 'N/A',
                    'primer_apellido' => $representante['primer_apellido'] ?? 'N/A',
                    'curp' => $representante['curp'] ?? 'N/A'
                ]);
            }

            // Normalizar fecha_conflicto (aceptamos dd/mm/YYYY)
            try {
                $fechaConf = \Carbon\Carbon::createFromFormat('d/m/Y', $common['fecha_conflicto']);
                $common['fecha_conflicto'] = $fechaConf->toDateString();
            } catch (\Exception $e) {
                try {
                    $common['fecha_conflicto'] = (new \Carbon\Carbon($common['fecha_conflicto']))->toDateString();
                } catch (\Exception $e2) {
                    return redirect()->back()->withErrors(['fecha_conflicto' => 'Formato de fecha inválido. Usa dd/mm/YYYY o YYYY-mm-dd.']);
                }
            }

            // Si el archivo es CSV, normalizar encoding a UTF-8 para evitar mojibake
            $uploaded = $request->file('archivo_citados');
            if ($uploaded && strtolower($uploaded->getClientOriginalExtension()) === 'csv') {
                $path = $uploaded->getRealPath();
                $content = file_get_contents($path);
                $encoding = mb_detect_encoding($content, ['UTF-8','ISO-8859-1','WINDOWS-1252'], true);
                if ($encoding && strtoupper($encoding) !== 'UTF-8') {
                    $utf8 = mb_convert_encoding($content, 'UTF-8', $encoding);
                    file_put_contents($path, $utf8);
                }
            }

            // Pasamos el solicitante, representante y la info común al importador
            Excel::import(new CitadoImport($solicitante, $common, $representante), $uploaded ?? $request->file('archivo_citados'));

            // Obtener estadísticas básicas del archivo
            $filePath = $uploaded->getRealPath();
            $fileSize = round(filesize($filePath) / 1024, 2); // KB
            $fileName = $uploaded->getClientOriginalName();
            
            // Contar filas del CSV/Excel (aproximado)
            $rowCount = 0;
            if (strtolower($uploaded->getClientOriginalExtension()) === 'csv') {
                $file = fopen($filePath, 'r');
                while (fgets($file) !== false) {
                    $rowCount++;
                }
                fclose($file);
                $rowCount = max(0, $rowCount - 1); // Restar encabezado
            }

            \Log::info('CargaMasiva: Archivo procesado', [
                'archivo' => $fileName,
                'tamaño_kb' => $fileSize,
                'filas_estimadas' => $rowCount,
                'solicitante' => $solicitante['nombre'] ?? 'N/A',
                'fecha_conflicto' => $common['fecha_conflicto'],
                'tiene_representante' => $representante ? 'Sí' : 'No'
            ]);

            // Guardar timestamp de inicio de procesamiento en sesión
            session(['carga_masiva_timestamp' => now()->format('Y-m-d H:i:s')]);

            return redirect()->back()->with([
                'success' => 'Archivo procesado correctamente',
                'archivo_info' => [
                    'nombre' => $fileName,
                    'tamaño' => $fileSize . ' KB',
                    'filas' => $rowCount > 0 ? $rowCount : 'calculando...',
                    'timestamp' => now()->format('d/m/Y H:i:s')
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('CargaMasiva: Error en upload', [
                'mensaje' => $e->getMessage(),
                'linea' => $e->getLine(),
                'archivo' => $e->getFile()
            ]);
            
            return redirect()->back()->with([
                'error' => 'Error al procesar el archivo',
                'error_detalle' => $e->getMessage(),
                'error_contexto' => 'Línea ' . $e->getLine() . ' en ' . basename($e->getFile())
            ]);
        }
    }
}