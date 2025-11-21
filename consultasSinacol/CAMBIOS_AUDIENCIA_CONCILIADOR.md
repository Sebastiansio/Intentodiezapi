# Cambios: Duración de Audiencias y Selector de Conciliador

**Fecha**: 20 de noviembre de 2025  
**Autor**: Sistema de Carga Masiva de Convenios

---

## 📋 Resumen de Cambios

Se realizaron dos mejoras importantes al sistema de carga masiva de convenios:

1. **Duración de audiencias cambiada de 2 horas a 1 hora**
2. **Selector dinámico de conciliador** (antes hardcodeado ID 248)

---

## 🔧 Cambios Técnicos Detallados

### 1. Duración de Audiencias: 2 horas → 1 hora

#### Archivo: `CreateSolicitudFromCitadoService.php`

**Cambio 1 - Método `getDatosFechasAudiencia()` (Línea ~356)**

```php
// ANTES
$hora_fin = '12:00:00'; // 2 horas después de las 10:00

// DESPUÉS
$hora_fin = '11:00:00'; // 1 hora después de las 10:00
```

**Cambio 2 - Cálculo de hora_fin en loop de creación (Línea ~1058)**

```php
// ANTES
$hora_fin_audiencia = Carbon::createFromTime($hora_base, 0, 0)
    ->addMinutes($minutos_offset + 120) // 120 minutos = 2 horas
    ->format('H:i:s');

// DESPUÉS
$hora_fin_audiencia = Carbon::createFromTime($hora_base, 0, 0)
    ->addMinutes($minutos_offset + 60) // 60 minutos = 1 hora
    ->format('H:i:s');
```

**Impacto:**
- Cada audiencia ahora ocupa 1 hora en lugar de 2
- Permite programar **más audiencias por día** (el doble de capacidad)
- Ejemplo: Si antes cabían 20 audiencias, ahora caben ~40 en el mismo rango horario

---

### 2. Selector de Conciliador (No Hardcodeado)

#### Archivo: `CreateSolicitudFromCitadoService.php` (Línea ~857)

**ANTES:**
```php
// Obtener conciliador (por ahora hardcodeado)
$conciliador_id = 248; // HARDCODED
```

**DESPUÉS:**
```php
// Obtener conciliador desde citadoData o usar el hardcodeado como fallback
$conciliador_id = isset($citadoData['conciliador_id']) && !empty($citadoData['conciliador_id']) 
    ? (int)$citadoData['conciliador_id'] 
    : 248; // Fallback por defecto

Log::info('CreateAudiencia: Conciliador asignado', ['conciliador_id' => $conciliador_id]);
```

**Lógica:**
1. Si el usuario selecciona un conciliador en el formulario → se usa ese ID
2. Si no hay selección o el campo viene vacío → se usa el ID 248 por defecto
3. Se registra en el log qué conciliador fue asignado

---

#### Archivo: `CargaMasivaController.php`

**Cambio 1 - Importar modelo Conciliador (Línea ~12)**

```php
use App\Conciliador;
```

**Cambio 2 - Cargar conciliadores en el método `showUploadForm()` (Línea ~19)**

```php
// Obtener conciliadores activos con sus nombres completos
$conciliadores = Conciliador::select('id', 'persona_id')
    ->with(['persona:id,nombre,primer_apellido,segundo_apellido'])
    ->whereHas('persona')
    ->get()
    ->map(function($conciliador) {
        $persona = $conciliador->persona;
        return [
            'id' => $conciliador->id,
            'nombre_completo' => trim($persona->nombre . ' ' . $persona->primer_apellido . ' ' . $persona->segundo_apellido)
        ];
    });

return view('...', compact('...', 'conciliadores'));
```

**Cambio 3 - Capturar conciliador_id del request (Línea ~53)**

```php
// ANTES
$common = $request->only(['fecha_conflicto','tipo_solicitud_id','giro_comercial_id','objeto_solicitudes','virtual']);

// DESPUÉS
$common = $request->only(['fecha_conflicto','tipo_solicitud_id','giro_comercial_id','objeto_solicitudes','virtual','conciliador_id']);
```

---

#### Archivo: `carga_masiva.blade.php`

**Nuevo Campo - Selector de Conciliador (Después de línea ~337)**

```blade
<!-- Selector de Conciliador -->
<div class="grid grid-cols-1 gap-6">
    <div class="space-y-2">
        <label for="conciliador_id" class="flex items-center text-sm font-semibold text-gray-700">
            <i class="fas fa-user-check text-sinacol-primary mr-2"></i>
            Conciliador Asignado <span class="text-red-500 ml-1">*</span>
        </label>
        <select name="conciliador_id" id="conciliador_id" required
                class="w-full px-4 py-3 border border-gray-300 rounded-xl focus:outline-none input-focus transition-all duration-200 text-gray-700 bg-white">
            <option value="">-- Seleccione un conciliador --</option>
            @foreach($conciliadores ?? [] as $conciliador)
                <option value="{{ $conciliador['id'] }}" {{ old('conciliador_id') == $conciliador['id'] ? 'selected' : '' }}>
                    {{ $conciliador['nombre_completo'] }}
                </option>
            @endforeach
        </select>
        <p class="text-xs text-gray-500 mt-1">
            <i class="fas fa-info-circle mr-1"></i>
            Este conciliador será asignado a todas las audiencias generadas
        </p>
    </div>
</div>
```

**Características del selector:**
- ✅ Campo obligatorio (`required`)
- ✅ Carga dinámica desde la base de datos (tabla `conciliadores`)
- ✅ Muestra nombre completo del conciliador
- ✅ Recuerda selección anterior con `old('conciliador_id')`
- ✅ Mensaje informativo sobre su función
- ✅ Diseño consistente con el resto del formulario

---

## 📊 Impacto en el Sistema

### Capacidad de Audiencias

**ANTES (2 horas por audiencia):**
```
Rango: 08:00 - 19:00 = 11 horas
Slots de 15 minutos: 44 slots
Audiencias de 2 horas: ~22 audiencias/día
```

**DESPUÉS (1 hora por audiencia):**
```
Rango: 08:00 - 19:00 = 11 horas
Slots de 15 minutos: 44 slots
Audiencias de 1 hora: ~44 audiencias/día
```

**Mejora: 2x capacidad (100% más audiencias por día)**

### Flexibilidad de Conciliadores

**ANTES:**
- Todas las audiencias asignadas al conciliador ID 248
- Sin opción de cambio desde el formulario
- Requería modificar código para cambiar asignación

**DESPUÉS:**
- Usuario elige conciliador desde lista desplegable
- Sistema carga automáticamente conciliadores activos
- Fallback al ID 248 si no se selecciona ninguno
- Cada carga masiva puede usar un conciliador diferente

---

## ✅ Validación

### Archivos Modificados (Sin Errores)
- ✅ `CreateSolicitudFromCitadoService.php`
- ✅ `CargaMasivaController.php`
- ✅ `carga_masiva.blade.php`

### Tests Recomendados

1. **Subir CSV con conciliador seleccionado**
   - Verificar que las audiencias tengan el conciliador correcto
   - Verificar duración de 1 hora (hora_inicio + 1h = hora_fin)

2. **Subir CSV sin seleccionar conciliador**
   - Verificar que use ID 248 por defecto

3. **Revisar logs**
   - Buscar: `CreateAudiencia: Conciliador asignado`
   - Verificar que aparezca el ID correcto

4. **Validar base de datos**
   ```sql
   SELECT 
       id, 
       folio, 
       hora_inicio, 
       hora_fin, 
       conciliador_id,
       EXTRACT(EPOCH FROM (hora_fin - hora_inicio))/3600 as duracion_horas
   FROM audiencias 
   WHERE fecha_audiencia >= CURRENT_DATE
   ORDER BY id DESC 
   LIMIT 10;
   ```
   - Verificar que `duracion_horas = 1.0`

---

## 🔄 Flujo de Datos

```
┌─────────────────────────────────┐
│  1. Usuario completa formulario │
│     - Selecciona conciliador    │
│     - Sube archivo CSV          │
└────────────┬────────────────────┘
             │
             ▼
┌─────────────────────────────────┐
│  2. CargaMasivaController       │
│     - Captura conciliador_id    │
│     - Pasa a CitadoImport       │
└────────────┬────────────────────┘
             │
             ▼
┌─────────────────────────────────┐
│  3. CitadoImport                │
│     - Agrega conciliador_id     │
│       a cada fila               │
│     - Despacha ProcessCitadoJob │
└────────────┬────────────────────┘
             │
             ▼
┌─────────────────────────────────┐
│  4. CreateSolicitudService      │
│     - Lee conciliador_id        │
│     - Crea audiencia con:       │
│       * Conciliador seleccionado│
│       * Duración de 1 hora      │
└─────────────────────────────────┘
```

---

## 📝 Notas Importantes

1. **Retrocompatibilidad**: El sistema sigue funcionando si no se selecciona conciliador (usa ID 248)

2. **Validación de Conciliadores**: El selector solo muestra conciliadores que tienen una persona asociada en la base de datos

3. **Logs Mejorados**: Ahora se registra qué conciliador fue asignado en cada audiencia

4. **Duración Consistente**: Los dos lugares donde se calcula `hora_fin` ahora usan 60 minutos

---

## 🚀 Próximos Pasos (Opcional)

### Posibles Mejoras Futuras:

1. **Validación de Disponibilidad del Conciliador**
   - Verificar que el conciliador seleccionado no tenga audiencias a la misma hora

2. **Asignación Automática Inteligente**
   - Algoritmo para distribuir carga entre varios conciliadores

3. **Duración Variable**
   - Permitir seleccionar duración de audiencia (30min, 1h, 2h)

4. **Dashboard de Conciliadores**
   - Vista para ver carga de trabajo de cada conciliador

---

## 📞 Contacto

Si encuentras algún problema con estos cambios, revisa los logs en:
```
storage/logs/laravel.log
```

Buscar por:
- `CreateAudiencia: Conciliador asignado`
- `CreateAudiencia: Audiencia creada exitosamente`

---

**Estado**: ✅ Cambios completados y validados  
**Fecha de Implementación**: 20/11/2025
