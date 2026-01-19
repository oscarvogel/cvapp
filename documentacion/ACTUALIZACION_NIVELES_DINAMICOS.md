# Actualización: Sistema de Niveles Dinámicos

## ✅ Cambios Implementados

Se ha actualizado el sistema de especialidades para usar niveles dinámicos configurables desde el backend en lugar de niveles fijos.

### Archivos Modificados:

1. **`index.php`** - Formulario de carga de CV
   - ✅ Función `cargarNiveles()` agregada para obtener niveles por especialidad
   - ✅ Función `cargarEspecialidades()` actualizada para cargar niveles dinámicamente
   - ✅ Cambio de `especialidades[id][nivel]` a `especialidades[id][nivel_id]`
   - ✅ Los selects de nivel ahora muestran opciones personalizadas por especialidad

2. **`upload.php`** - Procesamiento del formulario
   - ✅ Actualizado para usar `nivel_id` (FK) en lugar de `nivel` (ENUM)
   - ✅ Validación de niveles activos y asociados a la especialidad correcta
   - ✅ Query modificado: `INSERT INTO candidato_especialidades (candidato_id, especialidad_id, nivel_id)`

3. **`admin/candidato-detalle.php`** - Vista de detalles del candidato
   - ✅ Query actualizado para obtener `nivel_nombre` y `nivel_descripcion` desde `niveles_especialidades`
   - ✅ Visualización actualizada para mostrar los nombres personalizados de niveles
   - ✅ Tooltip con descripción del nivel (si está disponible)

## 🎯 Funcionalidad

### En el Formulario de Candidatos (index.php)

Cuando un candidato selecciona un área profesional:
1. Se cargan las especialidades de esa área
2. Para cada especialidad, se cargan sus niveles configurados dinámicamente
3. El candidato puede seleccionar el nivel apropiado de una lista personalizada

**Ejemplo práctico:**

Si en el backend configuraste:
- **Especialidad: Frontend Developer**
  - Junior Frontend (1-2 años)
  - Mid-Level Frontend (2-4 años)
  - Senior Frontend (4-7 años)
  - Lead Frontend (7+ años)

El formulario mostrará exactamente esos niveles para esa especialidad.

### En el Dashboard (candidato-detalle.php)

Al ver los detalles de un candidato:
- Se muestran las especialidades con sus niveles personalizados
- El tooltip muestra la descripción del nivel (si fue configurada)
- Colores distintivos para mejor visualización

## 🔄 Migración de Datos Existentes

Si tienes candidatos con el sistema antiguo (niveles ENUM), ejecuta:

```sql
-- 1. Primero, ejecuta la migración principal
SOURCE migracion_niveles_especialidades.sql;

-- 2. Luego, migra los datos existentes (si los hay)
UPDATE candidato_especialidades ce
INNER JOIN candidato_especialidades_backup ceb ON ce.id = ceb.id
INNER JOIN niveles_especialidades ne ON ne.especialidad_id = ce.especialidad_id 
  AND ne.nombre = ceb.nivel
SET ce.nivel_id = ne.id
WHERE ceb.nivel IS NOT NULL;

-- 3. Verifica la migración
SELECT 
    c.nombre as candidato,
    ea.nombre as especialidad,
    ne.nombre as nivel
FROM candidato_especialidades ce
INNER JOIN candidatos c ON ce.candidato_id = c.id
INNER JOIN especialidades_areas ea ON ce.especialidad_id = ea.id
LEFT JOIN niveles_especialidades ne ON ce.nivel_id = ne.id
LIMIT 10;
```

## 📝 Ejemplo de Flujo Completo

### 1. Configurar Niveles en el Backend

1. Ir a `admin/areas.php`
2. Expandir un área profesional
3. Clic en "Niveles" junto a una especialidad
4. Agregar niveles personalizados:
   - **Nombre:** Junior
   - **Descripción:** 1-2 años de experiencia
   - **Orden:** 1

### 2. Candidato Completa el Formulario

1. Selecciona área "Desarrollo"
2. Se cargan las especialidades automáticamente
3. Para "Frontend Developer" ve los niveles:
   - Junior (1-2 años)
   - Mid-Level (2-4 años)
   - Senior (4-7 años)
   - Lead (7+ años)
4. Selecciona "Mid-Level"

### 3. Visualización en el Dashboard

El administrador verá:
```
Desarrollo
  ➤ Frontend Developer [Mid-Level]
     (Tooltip: 2-4 años de experiencia)
```

## 🚀 Ventajas del Nuevo Sistema

1. **Flexibilidad Total:** Cada especialidad tiene sus propios niveles
2. **Sin Código Duro:** Todo configurable desde la interfaz
3. **Descriptivo:** Tooltips y descripciones claras
4. **Escalable:** Agrega o modifica niveles sin tocar código
5. **Consistente:** Mismo nivel de especialidad en todo el sistema

## ⚠️ Notas Importantes

- Los niveles inactivos no aparecen en el formulario
- No se pueden eliminar niveles asignados a candidatos
- El campo `nivel_id` puede ser NULL si no se seleccionó nivel
- Las descripciones son opcionales pero recomendadas

## 🔍 Verificación

Para verificar que todo funciona correctamente:

1. **Backend:** Accede a `admin/areas.php` y verifica que puedes gestionar niveles
2. **Frontend:** Accede al formulario y verifica que los niveles se cargan dinámicamente
3. **Base de datos:** Verifica que la tabla `niveles_especialidades` tiene registros
4. **Integración:** Crea un candidato de prueba y verifica que se guarda correctamente

## 📞 Soporte

Si encuentras algún problema:
1. Verifica que ejecutaste `migracion_niveles_especialidades.sql`
2. Revisa los logs del navegador (F12 → Console)
3. Verifica los logs de PHP en el servidor
4. Asegúrate de que las tablas existen y tienen datos

---

**Fecha de implementación:** 17 de octubre de 2025
**Versión:** 2.0
