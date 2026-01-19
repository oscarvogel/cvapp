# Cambios: Email Opcional y Validación por DNI

## Fecha de implementación
22 de Octubre de 2025

## Resumen de cambios

Se realizaron modificaciones al sistema de CV para hacer el campo **email opcional** y validar la unicidad de candidatos únicamente por **DNI**.

## Cambios implementados

### 1. Base de Datos

**Archivo de migración:** `migracion_email_opcional.sql`

- ✅ Columna `email` en tabla `candidatos` ahora permite valores `NULL`
- ✅ Eliminado índice único en columna `email` 
- ✅ Creado índice `UNIQUE` en columna `dni` para garantizar unicidad a nivel de base de datos
- ✅ DNIs duplicados o vacíos fueron limpiados con el script `limpiar_dnis_duplicados.php`

**Estado actual:**
```sql
-- Email: VARCHAR(150) NULL (permite valores vacíos)
-- DNI: VARCHAR(20) NOT NULL UNIQUE (garantiza unicidad)
```

### 2. Formulario Frontend (`index.php`)

**Cambios realizados:**

- ✅ Campo email ya no tiene el atributo `required`
- ✅ Etiqueta del campo email cambiada de "Email *" a "Email (opcional)"
- ✅ Eliminada validación en tiempo real de email duplicado
- ✅ Removido código JavaScript que verificaba email contra `check_email.php`
- ✅ Eliminada variable `emailExisteEnBD` que bloqueaba el submit
- ✅ Removidas referencias a `emailValidation` en el código JavaScript

**Líneas modificadas:**
- Línea ~185-200: Label y atributos del input email
- Línea ~1500-1700: Eliminación de código de validación de email

### 3. Procesamiento Backend (`upload.php`)

**Cambios realizados:**

- ✅ Validación de email modificada: solo valida formato si se proporciona un valor
- ✅ Eliminada verificación de email duplicado en base de datos
- ✅ Mantenida validación de DNI duplicado (ahora es la única validación de unicidad)

**Código modificado:**
```php
// ANTES:
if (!valid_email($email) || mb_strlen($email) > 150) {
    $errores['email'] = 'Email inválido.';
}

// AHORA:
if ($email !== '' && (!valid_email($email) || mb_strlen($email) > 150)) {
    $errores['email'] = 'Email inválido.';
}
```

**Validación de duplicados:**
```php
// ELIMINADO - Ya no se valida email duplicado
// if (email exists) { error... }

// MANTENIDO - Solo se valida DNI duplicado
if (dni exists) {
    $errores['dni'] = 'Ya existe un candidato con este DNI.';
}
```

### 4. Archivo check_email.php

**Estado:** Archivo mantenido pero ya no se utiliza
- El endpoint `check_email.php` ya no es llamado desde el frontend
- Puede ser eliminado en futuras actualizaciones o mantenido para compatibilidad

## Scripts de migración ejecutados

### Paso 1: Limpieza de DNIs duplicados
```bash
php limpiar_dnis_duplicados.php
```

**Resultado:**
- 2 candidatos con DNI vacío fueron actualizados con DNIs temporales
- Se asignaron valores `TEMP_1_[timestamp]` y `TEMP_2_[timestamp]`

### Paso 2: Migración de base de datos
```bash
php ejecutar_migracion_email_v2.php
```

**Resultado:**
- ✅ Columna email permite NULL
- ✅ Índice único creado en DNI
- ✅ Índice de email eliminado

## Validaciones actuales

### Campos obligatorios:
- ✅ Nombre
- ✅ Teléfono
- ✅ DNI (único)
- ✅ Edad
- ✅ Estado civil
- ✅ Hijos
- ✅ Nacionalidad
- ✅ Lugar de residencia
- ✅ Ocupación actual
- ✅ Ocupación padre/madre
- ✅ Nivel educativo
- ✅ Foto
- ✅ Al menos 1 área profesional (máx 2)
- ✅ Antecedentes penales
- ✅ Disponibilidad

### Campos opcionales:
- ✅ Email (validación de formato solo si se proporciona)
- ✅ Años de experiencia
- ✅ Edad de hijos (solo si tiene hijos)
- ✅ Carreras y títulos
- ✅ Cursos y capacitaciones
- ✅ Experiencia laboral (puede estar vacío)
- ✅ Licencias de conducir
- ✅ Otras licencias

## Regla de validación de duplicados

**ANTES:**
- No se permitían 2 CVs con el mismo email

**AHORA:**
- ✅ No se permiten 2 CVs con el mismo DNI
- ✅ El email puede estar vacío o repetido (no se valida unicidad)

## Notas importantes

1. **DNIs temporales:** Los candidatos con IDs 1 y 2 tienen DNIs temporales (`TEMP_*`) que deben ser actualizados manualmente desde el panel de administración.

2. **Email opcional:** Los candidatos pueden registrarse sin proporcionar email. Si lo proporcionan, debe tener formato válido pero puede estar duplicado.

3. **Índice único:** El índice UNIQUE en DNI garantiza a nivel de base de datos que no habrá duplicados, incluso si la validación de aplicación falla.

4. **Retrocompatibilidad:** Los CVs existentes en la base de datos mantienen sus emails. Los nuevos CVs pueden o no tener email.

## Archivos creados

1. `migracion_email_opcional.sql` - Script SQL de migración
2. `ejecutar_migracion_email.php` - Script inicial de migración (deprecated)
3. `ejecutar_migracion_email_v2.php` - Script mejorado de migración
4. `limpiar_dnis_duplicados.php` - Script para limpiar DNIs antes de crear índice único
5. `EMAIL_OPCIONAL_README.md` - Este archivo de documentación

## Pruebas recomendadas

1. ✅ Intentar enviar formulario con email vacío → Debe funcionar
2. ✅ Intentar enviar formulario con DNI duplicado → Debe mostrar error
3. ✅ Intentar enviar formulario con email duplicado → Debe funcionar
4. ✅ Intentar enviar formulario con email inválido → Debe mostrar error de formato
5. ✅ Verificar que todos los campos obligatorios funcionen correctamente

## Contacto

Para consultas sobre estos cambios, contactar al equipo de desarrollo.
