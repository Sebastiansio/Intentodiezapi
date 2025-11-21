<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Documento;
use App\Services\CreateSolicitudFromCitadoService;
use ZipArchive;

class DescargaDocumentosController extends Controller
{
    /**
     * Genera un archivo ZIP con todos los documentos de la sesión de carga masiva
     * y lo devuelve para descarga
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function descargarZip(Request $request)
    {
        try {
            // PASO 1: Buscar TODOS los documentos generados (puede tomar 5-10 segundos)
            Log::info('DescargaZip: Buscando documentos generados...');
            
            $documentos_info = CreateSolicitudFromCitadoService::buscarTodosLosDocumentos(10); // Esperar 10 segundos
            $documentos_ids = array_column($documentos_info, 'id');
            
            // Fallback: Verificar si hay IDs en sesión
            if (empty($documentos_ids)) {
                $documentos_ids = session('documentos_generados', []);
            }
            
            if (empty($documentos_ids)) {
                Log::warning('DescargaZip: No se encontraron documentos para descargar');
                return response()->json([
                    'error' => 'No hay documentos generados para descargar',
                    'mensaje' => 'Los documentos pueden estar aún generándose. Por favor intenta nuevamente en 10-15 segundos.'
                ], 404);
            }
            
            Log::info('DescargaZip: Iniciando generación de ZIP', [
                'total_documentos' => count($documentos_ids),
                'ids' => $documentos_ids
            ]);
            
            // PASO 2: Obtener los documentos de la base de datos
            $documentos = Documento::whereIn('id', $documentos_ids)
                ->with(['clasificacionArchivo', 'tipoDocumento'])
                ->get();
            
            if ($documentos->isEmpty()) {
                return response()->json([
                    'error' => 'No se encontraron documentos en la base de datos'
                ], 404);
            }
            
            // PASO 3: Crear un nombre único para el archivo ZIP
            $timestamp = now()->format('Y-m-d_His');
            $zipFileName = "documentos_carga_masiva_{$timestamp}.zip";
            $zipPath = storage_path("app/temp/{$zipFileName}");
            
            // Asegurar que existe el directorio temporal
            if (!file_exists(storage_path('app/temp'))) {
                mkdir(storage_path('app/temp'), 0755, true);
            }
            
            // PASO 4: Crear el archivo ZIP
            $zip = new ZipArchive();
            
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                Log::error('DescargaZip: No se pudo crear el archivo ZIP', ['path' => $zipPath]);
                return response()->json([
                    'error' => 'No se pudo crear el archivo ZIP'
                ], 500);
            }
            
            $archivos_agregados = 0;
            
            // PASO 5: Agregar cada documento al ZIP
            foreach ($documentos as $documento) {
                try {
                    // El campo 'ruta' contiene la ruta del PDF en storage
                    if (empty($documento->ruta)) {
                        Log::warning('DescargaZip: Documento sin ruta', ['id' => $documento->id]);
                        continue;
                    }
                    
                    // Obtener la ruta completa del archivo
                    $rutaCompleta = storage_path('app/' . $documento->ruta);
                    
                    // Verificar que el archivo existe
                    if (!file_exists($rutaCompleta)) {
                        Log::warning('DescargaZip: Archivo no encontrado', [
                            'id' => $documento->id,
                            'ruta' => $rutaCompleta
                        ]);
                        continue;
                    }
                    
                    // Generar un nombre descriptivo para el archivo en el ZIP
                    $clasificacion = $documento->clasificacionArchivo->nombre ?? 'documento';
                    $tipo = $documento->tipoDocumento->nombre ?? 'pdf';
                    
                    // Sanitizar el nombre del archivo
                    $clasificacion = $this->sanitizarNombreArchivo($clasificacion);
                    $tipo = $this->sanitizarNombreArchivo($tipo);
                    
                    // Nombre final: clasificacion_tipo_id.pdf
                    $nombreEnZip = "{$clasificacion}_{$tipo}_{$documento->id}.pdf";
                    
                    // Agregar el archivo al ZIP
                    $zip->addFile($rutaCompleta, $nombreEnZip);
                    $archivos_agregados++;
                    
                    Log::debug('DescargaZip: Archivo agregado al ZIP', [
                        'id' => $documento->id,
                        'nombre' => $nombreEnZip
                    ]);
                    
                } catch (\Exception $e) {
                    Log::error('DescargaZip: Error al agregar documento al ZIP', [
                        'id' => $documento->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }
            
            // Cerrar el ZIP
            $zip->close();
            
            Log::info('DescargaZip: ZIP generado exitosamente', [
                'archivos_agregados' => $archivos_agregados,
                'total_documentos' => $documentos->count(),
                'ruta' => $zipPath
            ]);
            
            if ($archivos_agregados == 0) {
                // Eliminar el ZIP vacío
                if (file_exists($zipPath)) {
                    unlink($zipPath);
                }
                
                return response()->json([
                    'error' => 'No se encontraron archivos PDF para agregar al ZIP'
                ], 404);
            }
            
            // Limpiar la lista de documentos del servicio
            CreateSolicitudFromCitadoService::limpiarDocumentosGenerados();
            session()->forget('documentos_generados');
            
            // Devolver el archivo para descarga y luego eliminarlo
            return response()->download($zipPath, $zipFileName)->deleteFileAfterSend(true);
            
        } catch (\Exception $e) {
            Log::error('DescargaZip: Error general', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'error' => 'Error al generar el archivo ZIP: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Sanitiza un nombre de archivo para evitar caracteres problemáticos
     *
     * @param string $nombre
     * @return string
     */
    private function sanitizarNombreArchivo(string $nombre): string
    {
        // Reemplazar espacios y caracteres especiales
        $nombre = str_replace(' ', '_', $nombre);
        $nombre = preg_replace('/[^A-Za-z0-9_\-]/', '', $nombre);
        $nombre = strtolower($nombre);
        
        return $nombre;
    }
    
    /**
     * Verifica si hay documentos disponibles para descargar
     * Este método NO espera, solo verifica si ya hay documentos registrados
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function verificarDocumentos()
    {
        // Primero buscar documentos sin esperar (para verificación rápida)
        $documentos_info = CreateSolicitudFromCitadoService::buscarTodosLosDocumentos(0); // 0 segundos = sin espera
        $documentos_ids = array_column($documentos_info, 'id');
        
        // Fallback a sesión
        if (empty($documentos_ids)) {
            $documentos_ids = session('documentos_generados', []);
        }
        
        Log::debug('VerificarDocumentos: Estado actual', [
            'disponibles' => !empty($documentos_ids),
            'total' => count($documentos_ids)
        ]);
        
        return response()->json([
            'disponibles' => !empty($documentos_ids),
            'total' => count($documentos_ids),
            'mensaje' => empty($documentos_ids) 
                ? 'Los documentos aún se están generando, espera unos segundos...' 
                : 'Documentos listos para descargar'
        ]);
    }
}
