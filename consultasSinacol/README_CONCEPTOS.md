# 📋 GUÍA: IMPORTACIÓN CSV CON CONCEPTOS DE PAGO

## ✅ RESUMEN EJECUTIVO

Ya está funcionando correctamente. Puedes importar citados con sus conceptos de pago directamente desde un CSV.

**Resultado de prueba exitosa:**
- ✅ 3 solicitudes importadas (100% exitosas)
- ✅ Conceptos de pago guardados correctamente
- ✅ Deducciones calculadas correctamente
- ✅ Pagos diferidos creados automáticamente

---

## 📁 ESTRUCTURA DEL CSV

### CSV Mínimo Requerido:

```csv
nombre,primer_apellido,segundo_apellido,curp,fecha_conflicto,fecha_ingreso,salario,concepto_1,concepto_2,concepto_3,concepto_4,concepto_5,concepto_13
JUAN,PEREZ,LOPEZ,PERJ850101HDFXXX01,2024-01-15,2020-01-01,8000.00,15000.00,8000.00,5000.00,2000.00,1500.00,3000.00
```

### Columnas Obligatorias:
- `nombre`, `primer_apellido`, `segundo_apellido`
- `curp` (formato: PERJ850101HDFXXX01)
- `fecha_conflicto` (formato: YYYY-MM-DD)
- `fecha_ingreso` (formato: YYYY-MM-DD) ⚠️ **OBLIGATORIO**
- `salario` (decimal, ej: 8000.00)

### Columnas de Conceptos:
- `concepto_1` = Días de sueldo
- `concepto_2` = Días de vacaciones  
- `concepto_3` = Prima vacacional
- `concepto_4` = Días de aguinaldo
- `concepto_5` = Gratificación 'A' (con base en el salario integrado)
- `concepto_6` = Gratificación 'B' (20 días por año cumplido)
- `concepto_7` = Gratificación 'C' (Prima de antigüedad topada)
- `concepto_8` = Gratificación General 'D' (Incluye cualquier otra prestación)
- `concepto_9` = Gratificación General 'E' (Pago en especie)
- `concepto_10` = Salarios vencidos
- `concepto_11` = Gratificación General 'F' (Reconocimiento de derechos)
- `concepto_12` = Otro concepto de pago
- `concepto_13` = Deducción ⚠️ **SE RESTA DEL TOTAL**

**IMPORTANTE:** 
- Si un concepto está vacío o es 0, NO se crea
- Puedes omitir las columnas de conceptos que no uses
- El concepto_13 (Deducción) se resta automáticamente del total

---

## 🚀 CÓMO USAR

### 1. Preparar tu CSV

Crea un archivo CSV con los datos de los citados. Ejemplo: `mis_citados.csv`

```csv
nombre,primer_apellido,segundo_apellido,curp,fecha_conflicto,fecha_ingreso,salario,concepto_1,concepto_2,concepto_13
MARIA,LOPEZ,GARCIA,LOGM900515MDFXXX01,2024-06-10,2021-03-15,9500.00,25000.00,12000.00,5000.00
PEDRO,SANCHEZ,MORALES,SAMP850820HDFXXX02,2024-07-20,2020-01-10,7200.00,18000.00,9000.00,3000.00
```

### 2. Listar Conceptos Disponibles

Para saber qué IDs de conceptos puedes usar:

```powershell
php list_conceptos.php
```

Salida:
```
═══════════════════════════════════════════════════════════════
  CONCEPTOS DE PAGO DISPONIBLES EN EL SISTEMA
═══════════════════════════════════════════════════════════════

ID    | NOMBRE DEL CONCEPTO
----------------------------------------------------------------------
1     | Días de sueldo
2     | Días de vacaciones
3     | Prima vacacional
...
13    | Deducción  ⚠️ SE RESTA
```

### 3. Importar el CSV

```powershell
php import_conceptos_csv.php mis_citados.csv
```

Salida esperada:
```
═══════════════════════════════════════════════════════════════
  IMPORTACIÓN DE CONVENIOS CON CONCEPTOS DESDE CSV
═══════════════════════════════════════════════════════════════

Conceptos detectados: 1, 2, 13

----------------------------------------------------------------------
✓ [1] MARIA LOPEZ - Folio: 61722/2025 - Conceptos: 3 - Total: $32,000.00
✓ [2] PEDRO SANCHEZ - Folio: 61723/2025 - Conceptos: 3 - Total: $24,000.00

══════════════════════════════════════════════════════════════════════
RESUMEN DE IMPORTACIÓN
══════════════════════════════════════════════════════════════════════
Total procesadas:  2
Exitosas:          2 (100.0%)
Con errores:       0 (0.0%)
══════════════════════════════════════════════════════════════════════
```

### 4. Verificar Importación

Para confirmar que los conceptos se guardaron:

```powershell
php verificar_conceptos.php
```

Salida:
```
Solicitud: 61722/2025 (ID: 240380)
----------------------------------------------------------------------
  ✓ Conceptos de pago encontrados: 3

    + ID 1: Días de sueldo           $25,000.00
    + ID 2: Días de vacaciones       $12,000.00
    - ID 13: Deducción               $5,000.00

  --------------------------------------------------------------------
  TOTAL:  $32,000.00
```

---

## 📊 FLUJO COMPLETO CREADO

Cada fila del CSV crea:

1. ✅ **Solicitud** con folio único
2. ✅ **Partes** (Solicitante + Citado)  
3. ✅ **Expediente** con folio secuencial real (AMG/CI/2025/XXXXXX)
4. ✅ **Audiencia** con conciliador, sala virtual, fecha
5. ✅ **Comparecientes** registrados
6. ✅ **Resolución** con convenio confirmado
7. ✅ **Conceptos de Pago** (los que definiste en el CSV)
8. ✅ **Pago Diferido** con el monto total calculado
9. ✅ **Manifestaciones** de las etapas de resolución
10. ✅ **Datos Laborales** actualizados

---

## 🔧 ARCHIVOS CREADOS

| Archivo | Descripción |
|---------|-------------|
| `list_conceptos.php` | Lista todos los IDs de conceptos disponibles |
| `import_conceptos_csv.php` | ⭐ Script principal de importación |
| `verificar_conceptos.php` | Verifica que los conceptos se guardaron |
| `ejemplo_conceptos.csv` | Ejemplo funcional con 3 registros |

---

## 💡 CONFIGURACIÓN ADICIONAL (Opcional)

### Agregar más columnas al CSV:

Puedes agregar estas columnas opcionales al CSV para tener más control:

```csv
...,tipo_solicitud_id,giro_comercial_id,puesto,jornada,horas_sem,rfc,nss,telefono,correo,...
```

El script ya tiene valores por defecto para todo, pero si las incluyes en el CSV se usarán esos valores.

### Datos del Solicitante (Patrón):

Por ahora el script usa un solicitante genérico para todas las filas. Si necesitas diferentes solicitantes por cada citado, agrega estas columnas:

```csv
...,solicitante_nombre,solicitante_rfc,solicitante_telefono,solicitante_email,...
```

---

## ⚠️ NOTAS IMPORTANTES

1. **Fecha de ingreso es OBLIGATORIA** - Si no la tienes, usa una fecha aproximada
2. **CURP debe ser válida** - El formato debe ser correcto (18 caracteres)
3. **Conceptos vacíos** - Si un monto es 0 o está vacío, ese concepto NO se crea
4. **Deducción (concepto_13)** - Se resta automáticamente del total
5. **Folios secuenciales** - El sistema genera folios reales continuando desde el último en la base

---

## 🎯 RESULTADO FINAL

Después de importar, tendrás en el sistema:

- ✅ Solicitudes ratificadas con convenio inmediato
- ✅ Expedientes con folios reales secuenciales  
- ✅ Audiencias finalizadas con resolución
- ✅ Conceptos de pago registrados correctamente
- ✅ Montos totales calculados (suma - deducciones)
- ✅ Todo listo para firma y emisión de documentos

---

## 📞 SOPORTE

Si algo falla, revisa:
1. El log: `storage/logs/laravel.log`
2. Los mensajes en pantalla durante la importación
3. Que el CSV tenga encoding UTF-8
4. Que las fechas estén en formato YYYY-MM-DD

**¡Listo para producción!** 🚀
