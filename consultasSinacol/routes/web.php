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


Route::post('/audiencias', [AudienciasController::class, 'getAudiencias']);
Route::get('/mundo', [AudienciasController::class, 'getHolaMundo']);