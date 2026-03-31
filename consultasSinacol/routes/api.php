<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AudienciasController;
use App\Http\Controllers\ReporteController;
use App\Http\Controllers\Api\DashboardController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// Rutas de Dashboard para conciliadores y sus audiencias
Route::get('dashboard/centros', [DashboardController::class, 'getCentros']);
Route::get('dashboard/conciliadores/listado', [DashboardController::class, 'getListaConciliadores']);
Route::get('dashboard/configuracion/conciliadores', [DashboardController::class, 'obtenerConfiguracionConciliadores']);
Route::post('dashboard/configuracion/conciliadores', [DashboardController::class, 'guardarConfiguracionConciliadores']);
Route::get('dashboard/resumen-general', [DashboardController::class, 'getResumenGeneral']);
Route::get('dashboard/conciliadores', [DashboardController::class, 'getConciliadores']);
Route::get('dashboard/conciliadores/{id}/audiencias', [DashboardController::class, 'getAudiencias']);
Route::get('dashboard/conciliadores/{id}/estadisticas', [DashboardController::class, 'getEstadisticas']);

Route::get('citas/datos-solicitud/{folio}/{anio}', [AudienciasController::class, 'datosSolicitud'])
    ->where([
        'folio' => '[a-zA-Z0-9\-]+', // Allows alphanumeric characters and hyphens
        'anio' => '[0-9]{4}',      // Requires a 4-digit number for the year     // Requires a numeric ID
    ]);


Route::get('/reporte-expedientes', [ReporteController::class, 'expedientesPorFecha']);