<?php

use App\Http\Controllers\AudienciasController;
use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ReporteController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return redirect('/login');
});

// Rutas de autenticación
Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::get('/logout', [AuthController::class, 'logout'])->name('logout');

// Ruta protegida para la vista de consulta de expedientes
Route::get('/consulta-expedientes', function () {
    return view('reportes.expedientes');
})->middleware('check.authenticated');


Route::get('/audiencias/dia-siguiente', [AudienciasController::class, 'getAudienciasDiaSiguiente']);
Route::get('/mundo', [AudienciasController::class, 'getMundo']);
Route::get('/audiencias/por-dia', [AudienciasController::class, 'getAudienciasPorDia']);
Route::post('/expedientes/check-folio', [AudienciasController::class, 'checkFolioExists']);
Route::get('/audiencias/coutconcluidas', [AudienciasController::class, 'getTotalAudienciasCount']);
Route::post('/citas/datos-solicitud', [AudienciasController::class, 'datosSolicitud']);
Route::get('/reporte-expedientes', [ReporteController::class, 'expedientesPorFecha']);