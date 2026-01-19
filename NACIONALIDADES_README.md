# Múltiples Nacionalidades - Documentación

## 📋 Resumen de Cambios

Se ha implementado la funcionalidad para que los candidatos puedan registrar **hasta 2 nacionalidades**.

## 🗄️ Base de Datos

### Estructura Actual
- **Columna**: `candidatos.nacionalidad` (VARCHAR(255))
- **Formato de almacenamiento**: Las nacionalidades se almacenan separadas por `" / "`
- **Ejemplos**:
  - Una nacionalidad: `"ARGENTINA"`
  - Dos nacionalidades: `"ARGENTINA / PERUANA"`

### Migración Opcional
Si deseas agregar un comentario a la columna en la base de datos para documentar el cambio:

```sql
ALTER TABLE candidatos 
MODIFY COLUMN nacionalidad VARCHAR(255) NOT NULL 
COMMENT 'Nacionalidades del candidato, separadas por " / " (máximo 2)';
```

**Nota**: Este cambio es **opcional** y solo agrega documentación. La funcionalidad ya está operativa sin necesidad de ejecutar este script.

## 📝 Archivos Modificados

### 1. `index.php` (Formulario Público)
**Cambios**:
- Campo único de texto reemplazado por campos dinámicos
- Botón "Agregar otra nacionalidad" (se oculta al llegar a 2)
- Botones de eliminar en cada nacionalidad (excepto cuando hay solo una)
- Validación en el cliente para máximo 2 nacionalidades

**JavaScript agregado**:
- `agregarNacionalidad()`: Agrega un nuevo campo de nacionalidad
- `eliminarNacionalidad()`: Elimina un campo de nacionalidad
- `actualizarBotonesNacionalidad()`: Controla la visibilidad de botones

### 2. `upload.php` (Procesamiento del Formulario)
**Cambios**:
- Procesa el array `$_POST['nacionalidades']` 
- Mantiene retrocompatibilidad con `$_POST['nacionalidad']` (campo antiguo)
- Las nacionalidades se convierten a mayúsculas
- Se unen con `" / "` para almacenar en la BD
- Validación actualizada: mínimo 1, máximo 2 nacionalidades

**Validaciones**:
```php
// Debe especificar al menos una nacionalidad
if (empty($nacionalidades_array)) { ... }

// Solo se permiten hasta 2 nacionalidades
elseif (count($nacionalidades_array) > 2) { ... }

// Las nacionalidades no deben exceder 255 caracteres en total
elseif (mb_strlen($nacionalidad) > 255) { ... }
```

### 3. `migracion_nacionalidades.sql`
Script SQL opcional para agregar comentario a la columna en la base de datos.

## 🎨 Interfaz de Usuario

### Vista del Formulario
```
┌─────────────────────────────────────────────┐
│ Nacionalidad(es) * (Máximo 2)               │
├─────────────────────────────────────────────┤
│ [Argentina                    ] [🗑️]        │
│ [Peruana                      ] [🗑️]        │
│                                              │
│ [+ Agregar otra nacionalidad]               │
└─────────────────────────────────────────────┘
```

### Comportamiento
1. **Al cargar**: Muestra 1 campo sin botón de eliminar
2. **Al agregar**: Aparecen botones de eliminar en ambos campos
3. **Con 2 nacionalidades**: Se oculta el botón "Agregar otra nacionalidad"
4. **Al eliminar**: Si queda 1 campo, se oculta su botón de eliminar

## 🔄 Retrocompatibilidad

El sistema mantiene compatibilidad con registros existentes:

### Registros Antiguos (1 nacionalidad)
```
BD: "ARGENTINA"
Formulario de edición: Mostrará "ARGENTINA" en el primer campo
```

### Registros Nuevos (múltiples nacionalidades)
```
BD: "ARGENTINA / PERUANA"
Formulario de edición: Mostrará ambas nacionalidades correctamente
```

## ✅ Pruebas Recomendadas

### 1. Registro Nuevo
- [ ] Crear candidato con 1 nacionalidad
- [ ] Crear candidato con 2 nacionalidades
- [ ] Intentar agregar más de 2 nacionalidades (debe alertar)
- [ ] Intentar eliminar la última nacionalidad (debe alertar)

### 2. Visualización
- [ ] Verificar que en el dashboard se muestre correctamente
- [ ] Verificar que en el detalle del candidato se muestre correctamente
- [ ] Verificar que en el PDF del CV se muestre correctamente

### 3. Edición
- [ ] Editar candidato existente (con 1 nacionalidad antigua)
- [ ] Editar candidato nuevo (con 2 nacionalidades)
- [ ] Agregar segunda nacionalidad a un candidato con 1
- [ ] Eliminar una nacionalidad de un candidato con 2

## 📊 Ejemplos de Datos

### En la Base de Datos
```sql
-- Un solo país
INSERT INTO candidatos (nombre, nacionalidad, ...) 
VALUES ('Juan Pérez', 'ARGENTINA', ...);

-- Doble nacionalidad
INSERT INTO candidatos (nombre, nacionalidad, ...) 
VALUES ('María García', 'ARGENTINA / ESPAÑOLA', ...);
```

### En PHP (upload.php)
```php
// Entrada del formulario
$_POST['nacionalidades'] = ['Argentina', 'Peruana'];

// Procesamiento
$nacionalidades_array = ['ARGENTINA', 'PERUANA'];
$nacionalidad = 'ARGENTINA / PERUANA';

// Almacenado en BD
// candidatos.nacionalidad = 'ARGENTINA / PERUANA'
```

## 🎯 Próximos Pasos Opcionales

Si en el futuro necesitas expandir esta funcionalidad, considera:

### Opción 1: Tabla Separada (Normalización completa)
```sql
CREATE TABLE candidato_nacionalidades (
    id INT AUTO_INCREMENT PRIMARY KEY,
    candidato_id INT NOT NULL,
    nacionalidad VARCHAR(100) NOT NULL,
    FOREIGN KEY (candidato_id) REFERENCES candidatos(id) ON DELETE CASCADE,
    UNIQUE KEY (candidato_id, nacionalidad)
);
```

### Opción 2: Permitir más de 2 nacionalidades
- Actualizar la validación en `upload.php`
- Actualizar el límite en el JavaScript de `index.php`
- Considerar si VARCHAR(255) es suficiente

### Opción 3: Lista desplegable de países
- Crear tabla `paises` con lista oficial
- Reemplazar input text por select/autocomplete
- Garantiza consistencia de datos

## 📞 Soporte

Si encuentras algún problema:
1. Verifica los logs de PHP en tu servidor
2. Revisa la consola del navegador (F12)
3. Verifica que la base de datos tenga la columna `nacionalidad` VARCHAR(255)

---

**Fecha de implementación**: Octubre 2025  
**Versión**: 1.0
