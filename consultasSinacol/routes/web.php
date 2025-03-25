<?php

use App\Http\Controllers\AudienciasController;
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
Route::get('/expedientes/check-folio', [AudienciasController::class, 'checkFolioExists']);
Route::get('/audiencias/coutconcluidas', [AudienciasController::class, 'getTotalAudienciasCount']);