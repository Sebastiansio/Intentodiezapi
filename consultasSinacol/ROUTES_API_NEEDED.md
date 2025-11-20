<?php

/*
|--------------------------------------------------------------------------
| Rutas API para Carga Masiva
|--------------------------------------------------------------------------
|
| Agregar estas rutas al archivo routes/api.php
|
*/

// En routes/api.php agregar:

Route::get('/carga-masiva/status', 'CargaMasivaStatusController@getStatus');
Route::get('/carga-masiva/logs', 'CargaMasivaStatusController@getLogs');
