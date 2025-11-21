<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AudienciasController;
use App\Http\Controllers\CargaMasivaStatusController;

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

Route::get('citas/datos-solicitud/{folio}/{anio}', [AudienciasController::class, 'datosSolicitud'])
    ->where([
        'folio' => '[a-zA-Z0-9\-]+', // Allows alphanumeric characters and hyphens
        'anio' => '[0-9]{4}',      // Requires a 4-digit number for the year     // Requires a numeric ID
    ]);

// Rutas para monitoreo de carga masiva
Route::get('carga-masiva/status', [CargaMasivaStatusController::class, 'getStatus']);
Route::get('carga-masiva/logs', [CargaMasivaStatusController::class, 'getLogs']);