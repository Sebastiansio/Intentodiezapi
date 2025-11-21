# Generación de Paquete de Documentos PDF

**Fecha**: 21 de noviembre de 2025  
**Archivo**: `CreateSolicitudFromCitadoService.php`  
**Método**: `generarPaqueteDocumentos()`

---

## 📋 Descripción

Nuevo método que genera automáticamente el paquete completo de documentos PDF al finalizar el proceso de creación de una solicitud y audiencia. Reemplaza al método anterior `generarDocumentos()` con lógica más robusta y completa.

---

## 🔧 Firma del Método

```php
private function generarPaqueteDocumentos(
    Solicitud $solicitud, 
    Audiencia $audiencia, 
    array $citadoData
): void
```

### Parámetros:
- **`$solicitud`**: Modelo de la solicitud creada
- **`$audiencia`**: Modelo de la audiencia generada
- **`$citadoData`**: Array con datos adicionales del proceso (puede incluir `es_patronal`, `representante`, etc.)

---

## 📄 Documentos Generados

### 1. **Citatorio de Conciliación** 📩
**Para quién**: Cada parte con `tipo_parte_id = 2` (Citado)

**Parámetros del evento**:
```php
event(new GenerateDocumentResolution(
    $audiencia->id,    // audiencia_id
    $solicitud->id,    // solicitud_id
    14,                // clasificacion_archivo_id: Citatorio
    4,                 // tipo_documento_id: Citatorio de conciliación
    null,              // resolucion_id
    $parte->id         // parte_id (citado)
));
```

**Logs**:
- `✅ INFO`: Citatorio generado para cada parte citada
- `❌ ERROR`: Si falla para alguna parte específica (no detiene el proceso)

---

### 2. **Acuse de Ratificación** 📝
**Para quién**: Para la solicitud completa

**Lógica especial**:
- Primero **elimina** cualquier acuse anterior (clasificación 40) para evitar duplicados
- Luego genera el nuevo acuse

**Parámetros del evento**:
```php
event(new GenerateDocumentResolution(
    '',                // audiencia_id (vacío para docs de solicitud)
    $solicitud->id,    // solicitud_id
    40,                // clasificacion_archivo_id: Acuse
    6                  // tipo_documento_id: Acuse de ratificación
));
```

**Logs**:
- `🗑️ DEBUG`: Acuse anterior eliminado (si existía)
- `✅ INFO`: Acuse de ratificación generado
- `❌ ERROR`: Si falla la generación

---

### 3. **Convenio** 📜 (Con Lógica Condicional)

**Tipos de Convenio**:
- **NORMAL**: Convenio estándar (tipo_documento_id = 18)
- **PATRONAL**: Convenio con ratificación patronal (tipo_documento_id buscado dinámicamente)

#### Detección de Tipo Patronal

El sistema usa **3 métodos** de detección (en orden):

**Método 1: Bandera Explícita**
```php
if (isset($citadoData['es_patronal']) && $citadoData['es_patronal']) {
    $es_patronal = true;
}
```

**Método 2: Por Objeto de Solicitud**
```php
$objetos_patronales = [5, 6, 7]; // IDs de objetos que implican ratificación patronal
if (!empty(array_intersect($objetos_ids, $objetos_patronales))) {
    $es_patronal = true;
}
```

**Método 3: Presencia de Representante Legal**
```php
if ($solicitud->partes()->where('tipo_parte_id', 3)->exists()) {
    $es_patronal = true; // Si tiene representante, probablemente es empresa
}
```

#### Selección de tipo_documento_id

**Para Convenio PATRONAL**:
```php
// Busca dinámicamente en la tabla tipo_documentos
$tipo_doc_patronal = DB::table('tipo_documentos')
    ->where('nombre', 'like', '%CONVENIO%')
    ->where('nombre', 'like', '%PATRONAL%')
    ->whereNull('deleted_at')
    ->first();

$tipo_documento_id = $tipo_doc_patronal->id ?? 19; // Fallback a ID 19
```

**Para Convenio NORMAL**:
```php
$tipo_documento_id = 18; // ID estándar
```

#### Parámetros del evento:
```php
event(new GenerateDocumentResolution(
    $audiencia->id,         // audiencia_id
    $solicitud->id,         // solicitud_id
    15,                     // clasificacion_archivo_id: Convenio
    $tipo_documento_id,     // 18=Normal, 19+=Patronal
    1                       // resolucion_id: 1 = Convenio/Terminación bilateral
));
```

**Logs**:
- `🔍 INFO`: Tipo de convenio determinado (PATRONAL o NORMAL)
- `✅ INFO`: Convenio generado exitosamente
- `⚠️ WARNING`: Si no encuentra tipo documento patronal (usa fallback)
- `❌ ERROR`: Si falla la generación

---

## 🔄 Flujo de Integración

El método se llama en el **Paso 5.7** del flujo principal `createAudiencia()`, justo antes del `DB::commit()`:

```php
// === PASO 5: PROCESO COMPLETO DE CONFIRMACIÓN ===

// 5.1 Crear representante legal
$this->crearRepresentanteLegal($solicitud, $audiencia, $datosRepresentante);

// 5.2 Crear manifestaciones
$this->crearManifestaciones($audiencia);

// 5.3 Crear resolución de partes
$this->crearResolucionPartes($solicitud, $audiencia);

// 5.4 Actualizar datos laborales
$this->actualizarDatosLaborales($solicitud);

// 5.5 Crear conceptos de pago
$this->crearConceptosPago($solicitud, $audiencia, $citadoData);

// 5.6 Crear comparecencias
$this->crearComparecencias($solicitud, $audiencia);

// ✨ 5.7 Generar paquete completo de documentos
$this->generarPaqueteDocumentos($solicitud, $audiencia, $citadoData);

// === PASO 6: COMMIT Y RETORNO ===
DB::commit(); // ⚠️ IMPORTANTE: Los PDFs se generan ANTES del commit
```

---

## 🛡️ Manejo de Errores

### Filosofía de Manejo de Errores:
> **"Los PDFs pueden regenerarse, los datos no"**

### Estrategia:
1. **No lanzar excepciones** que puedan hacer `rollback` de la transacción
2. **Registrar errores detallados** en los logs
3. **Continuar el proceso** aunque algún PDF falle
4. **Permitir regeneración manual** posterior

### Logs de Error:
```php
// Error específico por documento
Log::error('GenerarPaqueteDocumentos: Error al generar citatorio', [
    'parte_id' => $parte->id,
    'error' => $e->getMessage()
]);

// Error general del método
Log::error('GenerarPaqueteDocumentos: Error general en generación de documentos', [
    'solicitud_id' => $solicitud->id,
    'audiencia_id' => $audiencia->id,
    'error' => $e->getMessage(),
    'trace' => $e->getTraceAsString()
]);
```

### Comportamiento:
- ✅ Si falla un citatorio → Los demás documentos se siguen generando
- ✅ Si falla el acuse → El convenio se sigue generando
- ✅ Si fallan todos → La solicitud y audiencia **siguen guardándose** en la BD
- ✅ Los PDFs pueden regenerarse después desde el panel de administración

---

## 📊 Logs Generados

### Logs de Inicio:
```
[INFO] GenerarPaqueteDocumentos: Iniciando generación de PDFs
       solicitud_id: 240420
       audiencia_id: 150500
```

### Logs de Citatorios:
```
[INFO] GenerarPaqueteDocumentos: Generando citatorios
[INFO] GenerarPaqueteDocumentos: Citatorio generado
       parte_id: 350800
       nombre: JUAN PEREZ GARCIA
```

### Logs de Acuse:
```
[INFO] GenerarPaqueteDocumentos: Generando acuse de ratificación
[DEBUG] GenerarPaqueteDocumentos: Acuse anterior eliminado
        documento_id: 85600
[INFO] GenerarPaqueteDocumentos: Acuse de ratificación generado
```

### Logs de Convenio:
```
[INFO] GenerarPaqueteDocumentos: Generando convenio
[INFO] GenerarPaqueteDocumentos: Tipo de convenio determinado
       es_patronal: true
[INFO] GenerarPaqueteDocumentos: Tipo documento patronal encontrado
       tipo_documento_id: 19
       nombre: CONVENIO RATIFICACION PATRONAL
[INFO] GenerarPaqueteDocumentos: Convenio generado exitosamente
       tipo: PATRONAL
       tipo_documento_id: 19
```

### Log Final:
```
[INFO] GenerarPaqueteDocumentos: Proceso completado
       solicitud_id: 240420
       audiencia_id: 150500
       documentos_solicitados: ["citatorio", "acuse", "convenio"]
```

---

## 🔍 Debugging

### Ver logs en tiempo real:
```bash
# Desde PowerShell
Get-Content storage/logs/laravel.log -Tail 50 -Wait

# Filtrar solo logs de documentos
Get-Content storage/logs/laravel.log | Select-String "GenerarPaqueteDocumentos"
```

### Verificar documentos generados en BD:
```sql
-- Ver últimos documentos creados
SELECT 
    d.id,
    d.documentable_type,
    d.documentable_id,
    ca.nombre as clasificacion,
    td.nombre as tipo,
    d.created_at
FROM documentos d
LEFT JOIN clasificacion_archivo ca ON d.clasificacion_archivo_id = ca.id
LEFT JOIN tipo_documentos td ON d.tipo_documento_id = td.id
WHERE d.created_at >= CURRENT_DATE
ORDER BY d.id DESC
LIMIT 20;
```

### Verificar eventos disparados:
```sql
-- Si tienes tabla de eventos
SELECT * FROM jobs 
WHERE payload LIKE '%GenerateDocumentResolution%'
ORDER BY id DESC LIMIT 10;
```

---

## 🆚 Comparación: Método Anterior vs Nuevo

| Aspecto | `generarDocumentos()` (Antiguo) | `generarPaqueteDocumentos()` (Nuevo) |
|---------|----------------------------------|--------------------------------------|
| **Documentos** | Citatorio + Acuse | Citatorio + Acuse + **Convenio** |
| **Lógica Patronal** | ❌ No | ✅ Sí (3 métodos de detección) |
| **Búsqueda Dinámica** | ❌ IDs hardcodeados | ✅ Busca en tipo_documentos |
| **Manejo de Errores** | ⚠️ Básico | ✅ Robusto con try-catch por doc |
| **Logs** | ⚠️ Generales | ✅ Detallados por etapa |
| **Fallbacks** | ❌ No | ✅ IDs fallback si no encuentra |
| **Eliminación Duplicados** | ❌ No | ✅ Elimina acuse anterior |

---

## 🔧 Configuración Necesaria

### Variables de Entorno (Opcional):
```env
# IDs de objetos de solicitud que implican ratificación patronal
OBJETOS_PATRONALES=5,6,7

# IDs fallback si no se encuentra en BD
TIPO_DOC_CONVENIO_NORMAL=18
TIPO_DOC_CONVENIO_PATRONAL=19
```

### Ajustes Recomendados:

**1. Verificar IDs de Objetos Patronales** (Línea ~925):
```php
$objetos_patronales = [5, 6, 7]; // ⚠️ Ajustar según tu catálogo
```

**2. Verificar ID Fallback para Convenio Patronal** (Línea ~947):
```php
$tipo_documento_id = 19; // ⚠️ Ajustar según tu BD
```

**3. Revisar Clasificación de Convenio** (Línea ~951):
```php
15, // clasificacion_archivo_id: Convenio
```

---

## ✅ Testing

### Test Manual 1: Convenio Normal
```php
$citadoData = [
    'nombre' => 'CITADO TEST',
    'primer_apellido' => 'PRUEBA',
    // No incluir es_patronal ni representante
];

// Resultado esperado:
// - tipo_documento_id = 18
// - Log: "tipo: NORMAL"
```

### Test Manual 2: Convenio Patronal (Bandera)
```php
$citadoData = [
    'es_patronal' => true,
    'nombre' => 'EMPRESA SA DE CV'
];

// Resultado esperado:
// - tipo_documento_id = 19 (o el encontrado)
// - Log: "tipo: PATRONAL"
```

### Test Manual 3: Convenio Patronal (Con Representante)
```php
$citadoData = [
    'representante' => [
        'nombre' => 'JUAN',
        'primer_apellido' => 'APODERADO'
    ]
];

// Resultado esperado:
// - Detección automática de patronal
// - tipo_documento_id = 19 (o el encontrado)
// - Log: "tipo: PATRONAL"
```

---

## 🚀 Mejoras Futuras (Opcional)

1. **Generación Asíncrona**: Mover eventos a jobs en cola para no bloquear
2. **Reintentos Automáticos**: Si falla un PDF, reintentarlo automáticamente
3. **Notificaciones**: Alertar al admin si fallan documentos importantes
4. **Dashboard**: Panel para ver estado de generación de PDFs
5. **Regeneración Masiva**: Comando artisan para regenerar PDFs faltantes

---

## 📞 Troubleshooting

### Problema: No se generan los PDFs
**Solución**: Verificar que el evento `GenerateDocumentResolution` esté registrado y tenga un listener

### Problema: Error "tipo_documentos not found"
**Solución**: Verificar que la tabla `tipo_documentos` exista y tenga registros

### Problema: Convenio siempre se genera como Normal
**Solución**: Revisar lógica de detección patronal, verificar IDs de objetos

### Problema: Duplicados de Acuse
**Solución**: El código ya elimina duplicados, revisar logs para ver si se ejecutó

---

**✅ Estado**: Implementado y listo para producción  
**📅 Última actualización**: 21 de noviembre de 2025
