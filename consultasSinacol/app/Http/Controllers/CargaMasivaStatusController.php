<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class CargaMasivaStatusController extends Controller
{
    /**
     * Obtiene el estado de la última carga masiva
     */
    public function getStatus(Request $request)
    {
        try {
            $minutosAtras = 5; // Buscar registros de los últimos 5 minutos
            $fechaLimite = Carbon::now()->subMinutes($minutosAtras);
            
            // Obtener solicitudes creadas recientemente
            $solicitudesRecientes = DB::table('solicitudes')
                ->where('created_at', '>=', $fechaLimite)
                ->orderBy('created_at', 'desc')
                ->get(['id', 'folio', 'anio', 'created_at']);
            
            $totalSolicitudes = $solicitudesRecientes->count();
            
            // Contar expedientes y audiencias creados
            $expedientesCreados = 0;
            $audienciasCreadas = 0;
            $conceptosCreados = 0;
            $solicitudesConError = 0;
            
            foreach ($solicitudesRecientes as $sol) {
                // Verificar expediente
                $expediente = DB::table('expedientes')
                    ->where('solicitud_id', $sol->id)
                    ->first();
                
                if ($expediente) {
                    $expedientesCreados++;
                    
                    // Verificar audiencia
                    $audiencia = DB::table('audiencias')
                        ->where('expediente_id', $expediente->id)
                        ->first();
                    
                    if ($audiencia) {
                        $audienciasCreadas++;
                        
                        // Contar conceptos
                        $audiencia_parte = DB::table('audiencias_partes')
                            ->join('partes', 'audiencias_partes.parte_id', '=', 'partes.id')
                            ->where('audiencias_partes.audiencia_id', $audiencia->id)
                            ->where('partes.tipo_parte_id', 2)
                            ->first(['audiencias_partes.id']);
                        
                        if ($audiencia_parte) {
                            $conceptos = DB::table('resolucion_parte_conceptos')
                                ->where('audiencia_parte_id', $audiencia_parte->id)
                                ->count();
                            $conceptosCreados += $conceptos;
                        }
                    }
                } else {
                    $solicitudesConError++;
                }
            }
            
            // Calcular progreso estimado
            $progreso = $totalSolicitudes > 0 ? round(($expedientesCreados / $totalSolicitudes) * 100) : 0;
            
            return response()->json([
                'success' => true,
                'timestamp' => now()->format('Y-m-d H:i:s'),
                'resumen' => [
                    'total_solicitudes' => $totalSolicitudes,
                    'expedientes_creados' => $expedientesCreados,
                    'audiencias_creadas' => $audienciasCreadas,
                    'conceptos_creados' => $conceptosCreados,
                    'errores' => $solicitudesConError,
                    'progreso_porcentaje' => $progreso,
                    'completado' => $progreso >= 100
                ],
                'ultimas_solicitudes' => $solicitudesRecientes->take(5)->map(function($sol) {
                    return [
                        'folio' => $sol->folio . '/' . $sol->anio,
                        'fecha' => Carbon::parse($sol->created_at)->format('H:i:s')
                    ];
                })
            ]);
            
        } catch (\Exception $e) {
            Log::error('CargaMasivaStatus: Error', [
                'mensaje' => $e->getMessage(),
                'linea' => $e->getLine()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Error al obtener estado',
                'detalle' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Obtiene los logs más recientes de la carga
     */
    public function getLogs(Request $request)
    {
        try {
            $logFile = storage_path('logs/laravel.log');
            
            if (!file_exists($logFile)) {
                return response()->json([
                    'success' => false,
                    'mensaje' => 'Archivo de logs no encontrado'
                ]);
            }
            
            // Leer las últimas 50 líneas del log
            $lines = [];
            $file = new \SplFileObject($logFile, 'r');
            $file->seek(PHP_INT_MAX);
            $lastLine = $file->key();
            $linesToRead = min(50, $lastLine);
            
            $file->seek(max(0, $lastLine - $linesToRead));
            while (!$file->eof()) {
                $line = $file->current();
                if (!empty(trim($line))) {
                    $lines[] = $line;
                }
                $file->next();
            }
            
            // Filtrar solo logs relevantes a la carga masiva
            $logsRelevantes = array_filter($lines, function($line) {
                return strpos($line, 'CreateSolicitud') !== false 
                    || strpos($line, 'CitadoImport') !== false
                    || strpos($line, 'CargaMasiva') !== false
                    || strpos($line, 'ProcessCitadoRow') !== false;
            });
            
            return response()->json([
                'success' => true,
                'logs' => array_values($logsRelevantes),
                'total_lineas' => count($logsRelevantes)
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
