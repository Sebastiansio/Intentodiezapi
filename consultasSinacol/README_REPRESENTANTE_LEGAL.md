# Representante Legal en Carga Masiva de Convenios

## 📋 Descripción General

Se ha agregado la funcionalidad para capturar y procesar datos del **representante legal del solicitante** directamente desde el formulario de carga masiva. Cuando se marca esta opción, el representante será creado automáticamente como parte de cada solicitud y comparecerá en las audiencias.

---

## ✨ Nuevas Características

### 1. **Sección de Representante Legal en el Formulario**

Se agregó una nueva sección opcional en el blade `carga_masiva.blade.php` que incluye:

- ✅ Checkbox para activar/desactivar la captura de datos del representante
- ✅ Campos del representante:
  - **Nombre completo**: Nombre, Primer Apellido, Segundo Apellido
  - **Identificación**: CURP (obligatorio), RFC (opcional)
  - **Datos personales**: Género, Fecha de Nacimiento
  - **Contacto**: Teléfono, Correo Electrónico

### 2. **Validación Dinámica**

El formulario incluye validación JavaScript que:
- Muestra/oculta los campos según el estado del checkbox
- Convierte automáticamente CURP y RFC a mayúsculas
- Hace obligatorios los campos principales cuando se activa el representante

### 3. **Procesamiento Backend**

El sistema procesa los datos del representante en cada capa:

#### **CargaMasivaController**
```php
// Detecta si se marcó el checkbox de representante
if ($request->has('tiene_representante') && $request->input('tiene_representante') == '1') {
    $representante = $request->input('representante', []);
    // Pasa los datos al importador
}
```

#### **CitadoImport**
```php
// Recibe los datos del representante en el constructor
public function __construct(array $solicitante = [], array $common = [], $representante = null)

// Los agrega al payload de cada fila
$payload['representante'] = $this->representante;
```

#### **CreateSolicitudFromCitadoService**
```php
// Método mejorado para crear representante con datos reales
private function crearRepresentanteLegal(
    Solicitud $solicitud, 
    Audiencia $audiencia, 
    array $datosRepresentante = []
): ?Parte
```

---

## 🔧 Detalles de Implementación

### Estructura de Datos del Representante

Los datos se envían con la siguiente estructura:

```php
$representante = [
    'nombre' => 'JUAN',
    'primer_apellido' => 'PÉREZ',
    'segundo_apellido' => 'GARCÍA',
    'curp' => 'PXGJ850101HDFRXN09',
    'rfc' => 'PXGJ850101ABC',
    'genero_id' => 1, // 1=Masculino, 2=Femenino, 3=Otro
    'fecha_nacimiento' => '1985-01-01',
    'telefono' => '5512345678',
    'correo_electronico' => 'juan.perez@email.com'
];
```

### Creación del Representante

Cuando se proporciona información del representante:

1. **Se crea como Parte** con:
   - `tipo_parte_id = 3` (Representante)
   - `tipo_persona_id = 1` (Persona Física)
   - `representante = true`
   - `parte_representada_id` apuntando al solicitante
   - `detalle_instrumento = "Poder General para Pleitos y Cobranzas"`

2. **Se crean sus contactos**:
   - Teléfono (si se proporciona)
   - Correo electrónico (si se proporciona)

3. **Se crea como compareciente**:
   - Automáticamente aparece como presente en la audiencia
   - `presentado = true`

---

## 📝 Uso del Sistema

### Paso a Paso

1. **Acceder al formulario** de Carga Masiva de Convenios

2. **Completar datos comunes**:
   - Fecha del conflicto
   - Tipo de solicitud
   - Giro comercial
   - Objetos de solicitud

3. **Completar datos del solicitante**:
   - Tipo de persona (Física/Moral)
   - Datos personales
   - Domicilio

4. **Activar representante legal** (OPCIONAL):
   - ☑️ Marcar checkbox "El solicitante será representado por un apoderado legal"
   - Aparecerán los campos del representante
   - Completar los datos requeridos:
     - ✅ Nombre *
     - ✅ Primer Apellido *
     - ✅ CURP * (18 caracteres)
     - ✅ Género *
     - 📋 Segundo Apellido
     - 📋 RFC (12-13 caracteres)
     - 📋 Fecha de Nacimiento
     - 📋 Teléfono
     - 📋 Correo Electrónico

5. **Cargar archivo CSV/Excel** con los datos de los citados

6. **Enviar formulario**

### Resultado

Para cada fila del archivo CSV:
- ✅ Se crea la solicitud con solicitante y citado
- ✅ Se crea el expediente
- ✅ Se programa la audiencia
- ✅ **Se crea el representante legal** (si se activó)
- ✅ Se agregan todos los comparecientes (incluyendo representante)
- ✅ Se generan documentos (citatorios, acuse)
- ✅ Se crean conceptos de pago

---

## 🔍 Validaciones

### Frontend (JavaScript)
- ✅ Campos obligatorios solo cuando el checkbox está marcado
- ✅ CURP: exactamente 18 caracteres alfanuméricos
- ✅ RFC: 12-13 caracteres alfanuméricos
- ✅ Conversión automática a mayúsculas
- ✅ Formato de email válido
- ✅ Formato de teléfono (10 dígitos)

### Backend (Laravel)
- ✅ Solo crea representante si `tiene_representante = 1`
- ✅ Valida que exista el solicitante antes de crear representante
- ✅ Maneja errores sin interrumpir el proceso de otras solicitudes
- ✅ Registra en logs cada creación de representante

---

## 📊 Logs y Depuración

El sistema genera logs detallados en `storage/logs/laravel.log`:

```log
[INFO] CargaMasiva: Datos del representante legal detectados
    - nombre: JUAN
    - primer_apellido: PÉREZ
    - curp: PXGJ850101HDFRXN09

[INFO] CitadoImport: Representante agregado al payload
    - representante_nombre: JUAN

[INFO] CrearRepresentante: Iniciando
    - solicitud_id: 12345
    - tiene_datos: true

[INFO] CrearRepresentante: Representante creado exitosamente
    - representante_id: 67890
    - nombre: JUAN PÉREZ
    - curp: PXGJ850101HDFRXN09
```

---

## 🧪 Casos de Prueba

### Caso 1: Sin Representante
```
✅ No marcar checkbox
✅ Sistema NO crea representante
✅ Solo aparecen solicitante y citado
```

### Caso 2: Con Representante - Datos Completos
```
✅ Marcar checkbox
✅ Llenar todos los campos
✅ Sistema crea representante con todos los datos
✅ Contactos registrados correctamente
```

### Caso 3: Con Representante - Datos Mínimos
```
✅ Marcar checkbox
✅ Llenar solo campos obligatorios (Nombre, Apellido, CURP, Género)
✅ Sistema crea representante con datos básicos
✅ Sin contactos adicionales
```

### Caso 4: Error en Datos de Representante
```
⚠️ CURP inválido o faltante
✅ Sistema registra warning en logs
✅ NO interrumpe proceso de la solicitud
✅ Se crea solicitud sin representante
```

---

## 🔐 Seguridad

- ✅ Validación de formato CURP (18 caracteres)
- ✅ Validación de formato RFC (12-13 caracteres)
- ✅ Sanitización de datos de entrada
- ✅ Conversión automática a mayúsculas para CURP/RFC
- ✅ Validación de email con formato estándar
- ✅ Protección contra inyección SQL (uso de Eloquent)

---

## 📚 Referencias Técnicas

### Archivos Modificados

1. **resources/views/solicitante/carga_masiva.blade.php**
   - Líneas ~567-697: Nueva sección de representante legal
   - Líneas ~1065-1095: JavaScript para toggle y validación

2. **app/Http/Controllers/CargaMasivaController.php**
   - Líneas ~30-44: Captura de datos del representante
   - Línea 60: Paso de representante a CitadoImport

3. **app/Imports/CitadoImport.php**
   - Líneas 13-27: Constructor actualizado con parámetro representante
   - Líneas 43-51: Agregado de representante al payload

4. **app/Services/CreateSolicitudFromCitadoService.php**
   - Líneas 375-489: Método `crearRepresentanteLegal()` mejorado
   - Línea 1173: Llamada con datos del representante

### Base de Datos

**Tabla: partes**
```sql
- tipo_parte_id = 3 (Representante)
- tipo_persona_id = 1 (Persona Física)
- representante = true
- parte_representada_id = [id del solicitante]
- detalle_instrumento = 'Poder General para Pleitos y Cobranzas'
```

**Tabla: comparecientes**
```sql
- parte_id = [id del representante]
- audiencia_id = [id de la audiencia]
- presentado = true
```

**Tabla: contactos**
```sql
- parte_id = [id del representante]
- tipo_contacto_id = [teléfono o email]
- contacto = [valor del contacto]
```

---

## ⚠️ Notas Importantes

1. **Opcional**: El representante es completamente opcional. Si no se marca el checkbox, el sistema funciona como antes.

2. **Una vez por carga**: Los datos del representante se aplican a TODAS las solicitudes del archivo CSV. Es el mismo representante para todos los citados de esa carga.

3. **Poder legal**: El sistema asigna automáticamente "Poder General para Pleitos y Cobranzas" como instrumento.

4. **Comparecencias**: El representante se marca automáticamente como presente en todas las audiencias.

5. **Documentos**: Los documentos generados (citatorios, acuse) incluirán al representante en la información de partes.

---

## 🆘 Solución de Problemas

### Problema: Los campos no aparecen
**Solución**: Verificar que el checkbox esté marcado. El JavaScript muestra/oculta los campos dinámicamente.

### Problema: Error "CURP inválido"
**Solución**: El CURP debe ser exactamente 18 caracteres alfanuméricos. Ejemplo: `PXGJ850101HDFRXN09`

### Problema: Representante no se crea
**Solución**: 
1. Verificar que el checkbox esté marcado
2. Revisar logs en `storage/logs/laravel.log`
3. Confirmar que los campos obligatorios estén completos

### Problema: Error en contactos
**Solución**: 
- Verificar que existan los tipos de contacto en la tabla `tipo_contactos`
- Asegurar que "Teléfono móvil" y "Correo electrónico" existen en catálogo

---

## 🚀 Mejoras Futuras (Sugerencias)

- [ ] Agregar campo para número de poder notarial
- [ ] Permitir múltiples representantes por solicitud
- [ ] Agregar validación de CURP contra estructura oficial
- [ ] Cargar foto o documento del poder legal
- [ ] Permitir diferentes representantes por citado (vía CSV)
- [ ] Integración con firma electrónica

---

**Versión**: 1.0  
**Fecha**: Noviembre 2025  
**Autor**: Sistema SINACOL - Convenios Masivos
