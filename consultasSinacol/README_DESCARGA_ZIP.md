# Sistema de Descarga de Documentos ZIP - Carga Masiva

**Fecha**: 21 de noviembre de 2025  
**Implementación**: Descarga automática de PDFs generados (CON ESPERA INTELIGENTE)

---

## 📋 Resumen

Al finalizar la carga masiva de convenios, el sistema ahora genera un archivo ZIP con todos los documentos PDF creados (Citatorios, Acuses y Convenios) y permite descargarlos en un solo archivo.

**🔑 CAMBIO IMPORTANTE**: Los documentos se generan de forma **asíncrona** (eventos en cola), por lo que el sistema espera automáticamente y verifica cada 5 segundos hasta encontrarlos (máximo 30 segundos).

---

## 🔧 Archivos Modificados/Creados

### 1. **CreateSolicitudFromCitadoService.php** (Modificado)

**Cambios principales**:
- Agregado array estático `$solicitudesAudienciasCreadas` para rastrear qué se procesó
- Método `registrarSolicitudAudiencia()`: Guarda solicitud_id y audiencia_id al crearlos
- Método `buscarTodosLosDocumentos($segundos_espera)`: **NUEVO - CLAVE DEL SISTEMA**
  - Espera X segundos para que los PDFs se generen
  - Busca TODOS los documentos de las solicitudes/audiencias procesadas
  - Retorna lista de IDs de documentos encontrados

**Ubicación**: `app/Services/CreateSolicitudFromCitadoService.php`

**Flujo de rastreo**:
```php
// Al final de createAudiencia()
self::registrarSolicitudAudiencia($solicitud->id, $audiencia->id);

// Cuando se necesite descargar
$documentos = self::buscarTodosLosDocumentos(10); // Espera 10 segundos
```

---

### 2. **DescargaDocumentosController.php** (Nuevo)

**Método principal**: `descargarZip(Request $request)`

**Flujo ACTUALIZADO**:
```php
// PASO 1: Buscar documentos (ESPERA 10 SEGUNDOS)
$documentos_info = CreateSolicitudFromCitadoService::buscarTodosLosDocumentos(10);

// PASO 2: Extraer IDs
$documentos_ids = array_column($documentos_info, 'id');

// PASO 3: Si no hay, mostrar mensaje de espera
if (empty($documentos_ids)) {
    return response()->json([
        'error' => 'Los documentos aún se están generando...',
        'mensaje' => 'Intenta nuevamente en 10-15 segundos.'
    ], 404);
}

// PASO 4-7: Crear ZIP y descargar (igual que antes)
```

**Método de verificación**: `verificarDocumentos()`
```php
// NUEVA LÓGICA: Buscar sin esperar (0 segundos)
$documentos = buscarTodosLosDocumentos(0);

return response()->json([
    'disponibles' => !empty($documentos),
    'total' => count($documentos),
    'mensaje' => empty($documentos) 
        ? 'Documentos aún generándose...' 
        : 'Listos para descargar'
]);
```

**Ubicación**: `app/Http/Controllers/DescargaDocumentosController.php`

---

### 3. **CargaMasivaController.php** (Modificado)

**Cambios**:
- Importa `CreateSolicitudFromCitadoService`
- Llama a `limpiarDocumentosGenerados()` al iniciar nueva carga

**Ubicación**: `app/Http/Controllers/CargaMasivaController.php`

---

### 4. **web.php** (Rutas agregadas)

```php
// Descarga de ZIP
Route::get('/carga-masiva/descargar-zip', 
    [DescargaDocumentosController::class, 'descargarZip'])
    ->name('carga.descargar.zip');

// Verificación AJAX
Route::get('/carga-masiva/verificar-documentos', 
    [DescargaDocumentosController::class, 'verificarDocumentos'])
    ->name('carga.verificar.documentos');
```

**Ubicación**: `routes/web.php`

---

### 5. **carga_masiva.blade.php** (Vista modificada)

**Cambios JavaScript IMPORTANTES**:

#### Sistema de Reintentos Automáticos
```javascript
let intentosVerificacion = 0;
const MAX_INTENTOS = 6; // 6 × 5seg = 30 segundos máximo

async function verificarDocumentosDisponibles() {
    // Verifica si hay documentos
    const response = await fetch('/carga-masiva/verificar-documentos');
    const data = await response.json();
    
    if (data.disponibles && data.total > 0) {
        // ✅ ¡Encontrados! Mostrar botón
        mostrarBotonDescarga(data.total);
    } else {
        // ⏳ No están listos, reintentar en 5 segundos
        intentosVerificacion++;
        if (intentosVerificacion < MAX_INTENTOS) {
            setTimeout(verificarDocumentosDisponibles, 5000);
        } else {
            mostrarMensajeEsperaDocumentos(); // Timeout
        }
    }
}
```

#### Mensaje de Espera (si tarda más de 30 seg)
```javascript
function mostrarMensajeEsperaDocumentos() {
    // Muestra mensaje amarillo con botón "Verificar ahora"
    // El usuario puede hacer clic para reintentar manualmente
}
```

#### Activación Automática
- Se ejecuta 2 segundos después de cargar la página
- Se ejecuta automáticamente cuando el progreso llega a 100%
- Reintentos cada 5 segundos hasta encontrar documentos

**Ubicación**: `resources/views/solicitante/carga_masiva.blade.php`

---

## 🔄 Flujo de Funcionamiento ACTUALIZADO

### Durante la Carga:

```
1. Usuario sube archivo CSV
   └─> CargaMasivaController limpia registros anteriores
   
2. Se procesan los citados (SYNC - inmediato)
   └─> ProcessCitadoRowJob × N filas
       └─> CreateSolicitudFromCitadoService::create()
           ├─> Crea Solicitud
           ├─> Crea Audiencia
           └─> registrarSolicitudAudiencia($solicitud_id, $audiencia_id) ⭐
               └─> Guarda IDs en array estático
   
3. Generación de PDFs (ASÍNCRONO - evento en cola)
   └─> generarPaqueteDocumentos()
       ├─> event(GenerateDocumentResolution) → Citatorio
       ├─> event(GenerateDocumentResolution) → Acuse
       └─> event(GenerateDocumentResolution) → Convenio
           └─> Listener SaveResolution (ShouldQueue)
               └─> Crea documento en BD (PUEDE TARDAR)
```

### Después de la Carga (CON ESPERA INTELIGENTE):

```
4. Frontend monitorea progreso (cada 5 segundos)
   
5. Al completar (100%):
   └─> Llama a verificarDocumentosDisponibles()
       └─> GET /carga-masiva/verificar-documentos
           └─> buscarTodosLosDocumentos(0) // Sin espera, solo verificar
           
6. Si NO hay documentos:
   └─> Espera 5 segundos
   └─> Reintenta verificación (máximo 6 intentos = 30 segundos)
   
7. Si hay documentos:
   └─> Muestra botón "Descargar ZIP" ✅
   
8. Usuario hace clic:
   └─> GET /carga-masiva/descargar-zip
       ├─> buscarTodosLosDocumentos(10) // ⏳ ESPERA 10 SEGUNDOS
       │   ├─> Extrae solicitud_ids y audiencia_ids del array
       │   ├─> sleep(10) para dar tiempo a generación
       │   ├─> Busca en tabla documentos:
       │   │   • WHERE documentable_type = 'App\Solicitud'
       │   │   • WHERE documentable_id IN (solicitud_ids)
       │   │   • WHERE documentable_type = 'App\Audiencia'
       │   │   • WHERE documentable_id IN (audiencia_ids)
       │   └─> Retorna array de IDs encontrados
       │
       ├─> Crea ZipArchive
       ├─> Lee cada PDF desde storage
       ├─> Agrega al ZIP con nombre descriptivo
       ├─> Envía descarga
       └─> Elimina ZIP temporal
```

**🔑 CLAVE**: El sistema ahora **rastrea qué solicitudes/audiencias se crearon** y luego **busca sus documentos** en lugar de depender de eventos inmediatos.

---

## 📊 Base de Datos

### Tabla: `documentos`

**Campos usados**:
```sql
- id                          INT PRIMARY KEY
- documentable_type           VARCHAR (ej: 'App\Solicitud', 'App\Audiencia')
- documentable_id             INT
- clasificacion_archivo_id    INT (14=Citatorio, 40=Acuse, 15=Convenio)
- tipo_documento_id           INT (4, 6, 18, etc.)
- ruta                        VARCHAR (ej: 'documentos/2025/11/archivo.pdf')
- created_at                  TIMESTAMP
```

### Consulta para rastreo de documentos:

```php
// Documentos de solicitud (últimos 5 min)
$docs = Documento::where('documentable_type', \App\Solicitud::class)
    ->where('documentable_id', $solicitud->id)
    ->where('created_at', '>=', now()->subMinutes(5))
    ->get();
```

---

## 🎨 Interfaz de Usuario

### Botón de Descarga

**Estado Inicial**: Oculto (`display: none`)

**Estado Activado**:
```html
<div class="bg-gradient-to-r from-blue-50 to-indigo-50 ...">
  <div class="flex items-center justify-between">
    <div class="flex items-center">
      <i class="fas fa-file-archive"></i>
      <h4>Documentos Listos</h4>
      <p>15 documento(s) PDF han sido generados</p>
    </div>
    <a href="/carga-masiva/descargar-zip">
      Descargar ZIP
    </a>
  </div>
</div>
```

**Animación**: `animate-slide-up` (desliza desde abajo)

---

## 🧪 Testing Manual

### Test 1: Subir 3 citados
```bash
# Resultado esperado:
# - 3 Citatorios
# - 3 Acuses
# - 3 Convenios
# Total: 9 documentos en el ZIP
```

### Test 2: Verificar estructura del ZIP
```
documentos_carga_masiva_2025-11-21_143025.zip
├── citatorio_citatorio_de_conciliacion_12345.pdf
├── citatorio_citatorio_de_conciliacion_12346.pdf
├── citatorio_citatorio_de_conciliacion_12347.pdf
├── acuse_acuse_de_ratificacion_12348.pdf
├── acuse_acuse_de_ratificacion_12349.pdf
├── acuse_acuse_de_ratificacion_12350.pdf
├── convenio_convenio_normal_12351.pdf
├── convenio_convenio_normal_12352.pdf
└── convenio_convenio_normal_12353.pdf
```

### Test 3: Verificar logs
```bash
# Ver logs de descarga
Get-Content storage/logs/laravel.log | Select-String "DescargaZip"

# Ver documentos registrados
Get-Content storage/logs/laravel.log | Select-String "Documento registrado"
```

---

## 🐛 Troubleshooting

### Problema: Botón no aparece

**Causas posibles**:
1. Documentos no se generaron (verificar tabla `documentos`)
2. JavaScript no ejecutó `verificarDocumentosDisponibles()`
3. Ventana de 5 minutos expiró

**Solución**:
```sql
-- Verificar documentos recientes
SELECT COUNT(*) FROM documentos 
WHERE created_at >= NOW() - INTERVAL '5 minutes';

-- Si hay documentos pero el botón no aparece:
-- Recargar la página y esperar 2 segundos
```

---

### Problema: ZIP vacío o con errores

**Causas posibles**:
1. Campo `ruta` vacío en tabla `documentos`
2. Archivos PDF no existen en `storage/app/`
3. Permisos de lectura

**Solución**:
```sql
-- Verificar rutas
SELECT id, ruta, clasificacion_archivo_id 
FROM documentos 
WHERE ruta IS NULL OR ruta = '';

-- Verificar existencia de archivos
```

```bash
# Verificar permisos
ls -la storage/app/documentos/
```

---

### Problema: Error "No se pudo crear el archivo ZIP"

**Causas posibles**:
1. Directorio `storage/app/temp/` no existe
2. Sin permisos de escritura
3. Espacio en disco insuficiente

**Solución**:
```bash
# Crear directorio temporal
mkdir storage/app/temp
chmod 755 storage/app/temp

# Verificar espacio en disco
df -h
```

---

## 📈 Logs Generados

### Durante generación de documentos:
```
[INFO] GenerarPaqueteDocumentos: Iniciando generación de PDFs
[INFO] GenerarPaqueteDocumentos: Citatorio generado
[DEBUG] Documento registrado | id: 12345 | tipo: citatorio
[DEBUG] Documento registrado | id: 12346 | tipo: acuse
[DEBUG] Documento registrado | id: 12347 | tipo: convenio
```

### Durante descarga:
```
[INFO] DescargaZip: Iniciando generación de ZIP
       total_documentos: 9
       ids: [12345, 12346, 12347, ...]
       
[DEBUG] DescargaZip: Archivo agregado al ZIP
        id: 12345
        nombre: citatorio_citatorio_de_conciliacion_12345.pdf
        
[INFO] DescargaZip: ZIP generado exitosamente
       archivos_agregados: 9
       ruta: storage/app/temp/documentos_carga_masiva_2025-11-21_143025.zip
```

---

## ⚙️ Configuración

### Tiempo de búsqueda de documentos:
```php
// En registrarDocumentosGeneradosRecientes()
$hace_5_minutos = now()->subMinutes(5); // Ajustar si es necesario
```

### Directorio temporal:
```php
// En DescargaDocumentosController
$zipPath = storage_path("app/temp/{$zipFileName}");
```

### Sanitización de nombres:
```php
// Reemplaza espacios y caracteres especiales
private function sanitizarNombreArchivo(string $nombre): string
{
    $nombre = str_replace(' ', '_', $nombre);
    $nombre = preg_replace('/[^A-Za-z0-9_\-]/', '', $nombre);
    return strtolower($nombre);
}
```

---

## 🚀 Mejoras Futuras (Opcional)

1. **Cache de ZIP**: Guardar ZIP generado por 24 horas
2. **Descarga por lotes**: ZIP por cada 100 solicitudes
3. **Progreso de generación**: Barra de progreso al crear ZIP
4. **Notificación por email**: Enviar link de descarga por correo
5. **Historial de descargas**: Tabla para rastrear descargas
6. **Compresión ajustable**: Nivel de compresión configurable

---

## 📞 API Endpoints

### GET `/carga-masiva/verificar-documentos`

**Respuesta exitosa**:
```json
{
  "disponibles": true,
  "total": 9
}
```

**Respuesta sin documentos**:
```json
{
  "disponibles": false,
  "total": 0
}
```

---

### GET `/carga-masiva/descargar-zip`

**Respuesta exitosa**:
- Content-Type: `application/zip`
- Content-Disposition: `attachment; filename="documentos_carga_masiva_2025-11-21_143025.zip"`
- El archivo se descarga automáticamente

**Respuesta de error**:
```json
{
  "error": "No hay documentos generados para descargar"
}
```

---

## ✅ Checklist de Implementación

- ✅ Servicio rastrea documentos generados
- ✅ Controlador de descarga creado
- ✅ Rutas agregadas a web.php
- ✅ Vista con botón de descarga
- ✅ JavaScript para verificación AJAX
- ✅ Limpieza de documentos al iniciar carga
- ✅ Manejo de errores robusto
- ✅ Logs detallados
- ✅ Sanitización de nombres de archivo
- ✅ Auto-eliminación de ZIP temporal

---

**✅ Estado**: Implementado y listo para testing  
**📅 Última actualización**: 21 de noviembre de 2025  
**🎯 Próximo paso**: Probar con carga masiva real
