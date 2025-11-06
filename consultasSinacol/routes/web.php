<?php

use App\Http\Controllers\AudienciasController;
use App\Http\Controllers\CargaMasivaController;
use Illuminate\Support\Facades\Route;

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
    return view('welcome');
});


Route::get('/audiencias/dia-siguiente', [AudienciasController::class, 'getAudienciasDiaSiguiente']);
Route::get('/mundo', [AudienciasController::class, 'getMundo']);
Route::get('/audiencias/por-dia', [AudienciasController::class, 'getAudienciasPorDia']);
Route::post('/expedientes/check-folio', [AudienciasController::class, 'checkFolioExists']);
Route::get('/audiencias/coutconcluidas', [AudienciasController::class, 'getTotalAudienciasCount']);
Route::post('/citas/datos-solicitud', [AudienciasController::class, 'datosSolicitud']);

Route::get('/carga-masiva', [CargaMasivaController::class, 'showUploadForm'])->name('carga.show');
Route::post('/carga-masiva', [CargaMasivaController::class, 'handleUpload'])->name('carga.handle');