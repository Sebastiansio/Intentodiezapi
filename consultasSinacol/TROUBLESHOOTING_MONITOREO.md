# 🔧 Troubleshooting - Sistema de Monitoreo de Carga Masiva

## Problema: Los contadores no se actualizan (muestra 0)

### ✅ Solución Implementada

Se realizaron los siguientes cambios para resolver el problema:

### 1. **Rutas API Registradas** ✅
- Agregadas en `routes/api.php`:
  ```php
  Route::get('carga-masiva/status', [CargaMasivaStatusController::class, 'getStatus']);
  Route::get('carga-masiva/logs', [CargaMasivaStatusController::class, 'getLogs']);
  ```

### 2. **Timestamp de Sesión** ✅
- El sistema ahora guarda el timestamp del inicio del procesamiento
- Usa ese timestamp para buscar solicitudes creadas desde ese momento
- Ventana de búsqueda ampliada a 60 minutos como fallback

### 3. **Logging Mejorado** ✅
- Logs en backend (CargaMasivaStatusController)
- Logs en frontend (consola del navegador)
- Muestra cantidad de jobs pendientes

### 4. **Verificación de Jobs en Cola** ✅
- Cuenta jobs pendientes en la tabla `jobs`
- Solo marca como completado cuando progreso = 100% Y jobs_pendientes = 0

---

## 📋 Pasos de Verificación

### 1. Verificar que las Rutas están Registradas

```bash
cd "c:\Users\juan.aguirre\Documents\Api Sinacol\Intentodiezapi\consultasSinacol"
php artisan route:clear
php artisan route:list --path=carga-masiva
```

**Resultado esperado:**
```
+--------+----------+-------------------------+
| Method | URI                     | Action               |
+--------+----------+-------------------------+
| GET    | api/carga-masiva/status | ...@getStatus       |
| GET    | api/carga-masiva/logs   | ...@getLogs         |
+--------+----------+-------------------------+
```

### 2. Probar el Endpoint Directamente

Abrir en el navegador o Postman:
```
http://tu-dominio/api/carga-masiva/status
```

**Respuesta esperada:**
```json
{
  "success": true,
  "timestamp": "2025-11-20 18:30:00",
  "resumen": {
    "total_solicitudes": 5,
    "expedientes_creados": 5,
    "audiencias_creadas": 5,
    "conceptos_creados": 15,
    "errores": 0,
    "progreso_porcentaje": 100,
    "completado": true,
    "jobs_pendientes": 0
  },
  "ultimas_solicitudes": [...]
}
```

### 3. Verificar los Logs del Backend

```bash
tail -f storage/logs/laravel.log
```

Buscar líneas como:
```
[INFO] CargaMasivaStatus: Consultando estado
[INFO] CargaMasivaStatus: Solicitudes encontradas - total: 5
[INFO] CargaMasivaStatus: Resultado - solicitudes: 5, expedientes: 5
```

### 4. Verificar la Consola del Navegador

1. Abrir DevTools (F12)
2. Ir a la pestaña Console
3. Buscar mensajes como:
```
🚀 Iniciando monitoreo de progreso...
📡 Check #1 - Consultando /api/carga-masiva/status...
📥 Respuesta recibida: 200 OK
✅ Datos recibidos: {success: true, ...}
📊 Progreso actualizado: {total_solicitudes: 5, ...}
```

---

## 🐛 Problemas Comunes

### Problema 1: "404 Not Found" al consultar `/api/carga-masiva/status`

**Causa:** Las rutas no están registradas o el cache de rutas está desactualizado.

**Solución:**
```bash
php artisan route:clear
php artisan config:clear
php artisan cache:clear
```

---

### Problema 2: Los contadores siguen en 0 pero las solicitudes SÍ se crearon

**Causa:** El timestamp de búsqueda no coincide con cuando se crearon las solicitudes.

**Solución 1:** Revisar logs para ver qué timestamp se está usando:
```bash
tail -f storage/logs/laravel.log | grep "CargaMasivaStatus"
```

**Solución 2:** Verificar manualmente cuántas solicitudes hay:
```bash
php artisan tinker
>>> DB::table('solicitudes')->where('created_at', '>=', now()->subHours(1))->count();
```

**Solución 3:** Forzar una ventana de tiempo más amplia editando `CargaMasivaStatusController`:
```php
// Cambiar de 60 a 120 minutos
$fechaLimite = Carbon::now()->subMinutes(120);
```

---

### Problema 3: Los jobs no se están procesando

**Causa:** El worker de queue no está corriendo o está en modo `sync`.

**Verificar configuración de queue:**
```bash
# Ver archivo .env
cat .env | grep QUEUE_CONNECTION
```

**Si dice `QUEUE_CONNECTION=database`:**
```bash
# Verificar tabla jobs
php artisan tinker
>>> DB::table('jobs')->count();
```

**Iniciar el worker:**
```bash
php artisan queue:work
```

**Si dice `QUEUE_CONNECTION=sync`:**
Los jobs se procesan inmediatamente (no hay cola). El monitoreo debería funcionar de inmediato.

---

### Problema 4: CORS error en la consola

**Error:** `Access to fetch at 'http://...' from origin '...' has been blocked by CORS`

**Solución:** Agregar configuración CORS en `config/cors.php`:
```php
'paths' => ['api/*', 'sanctum/csrf-cookie'],
'allowed_origins' => ['*'],
```

---

### Problema 5: La barra de progreso no aparece

**Causa:** El elemento `progress-container` no se encuentra.

**Verificar en el navegador (DevTools > Elements):**
Buscar `<div id="progress-container">`

Si no existe, la sesión no tiene `archivo_info` (el archivo no se procesó correctamente).

---

## 🧪 Script de Prueba Rápida

Crear archivo `test_monitoring.php` en la raíz:

```php
<?php
require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

// Simular request
$request = Illuminate\Http\Request::create('/api/carga-masiva/status', 'GET');
$response = $kernel->handle($request);

echo "Status Code: " . $response->getStatusCode() . "\n";
echo "Content:\n";
echo $response->getContent() . "\n";
```

Ejecutar:
```bash
php test_monitoring.php
```

---

## 📊 Monitoreo en Tiempo Real

Abrir 3 terminales:

**Terminal 1 - Logs generales:**
```bash
tail -f storage/logs/laravel.log
```

**Terminal 2 - Queue worker:**
```bash
php artisan queue:work --verbose
```

**Terminal 3 - Consulta de estado:**
```bash
# Cada 5 segundos
while ($true) { curl http://localhost/api/carga-masiva/status | ConvertFrom-Json | ConvertTo-Json; Start-Sleep -Seconds 5 }
```

---

## ✅ Checklist de Verificación

Antes de reportar un problema, verificar:

- [ ] Las rutas API están registradas (`route:list`)
- [ ] El endpoint `/api/carga-masiva/status` responde (Postman/navegador)
- [ ] La consola del navegador muestra los mensajes de monitoreo
- [ ] Los logs del backend muestran las consultas
- [ ] Las solicitudes SÍ se están creando en la base de datos
- [ ] El timestamp de sesión está guardado correctamente
- [ ] Los jobs se están procesando (si usa cola)
- [ ] No hay errores CORS en la consola

---

## 🆘 Última Solución: Recargar Todo

```bash
# Limpiar todo
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Recargar configuración
php artisan config:cache
php artisan route:cache

# Reiniciar servidor
php artisan serve --host=0.0.0.0 --port=8000
```

---

## 📞 Información de Debug para Reportar

Si el problema persiste, recolectar:

1. **Output de route:list:**
   ```bash
   php artisan route:list --path=carga-masiva > routes.txt
   ```

2. **Logs del backend:**
   ```bash
   tail -n 100 storage/logs/laravel.log > backend_logs.txt
   ```

3. **Consola del navegador:**
   - Copiar todos los mensajes de la consola

4. **Respuesta del API:**
   ```bash
   curl http://localhost/api/carga-masiva/status > api_response.json
   ```

5. **Estado de la base de datos:**
   ```bash
   php artisan tinker --execute="echo 'Solicitudes: '.DB::table('solicitudes')->where('created_at', '>=', now()->subHour())->count().'\n';"
   ```

---

**Última actualización:** Noviembre 2025  
**Versión:** 2.0 con timestamp de sesión
