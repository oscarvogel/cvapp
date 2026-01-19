# Sistema de Niveles de Especialidades

## Descripción
Este sistema permite asignar diferentes niveles a cada especialidad de forma flexible e interactiva. Cada especialidad puede tener sus propios niveles personalizados, lo que permite una mayor granularidad en la clasificación de habilidades de los candidatos.

## Estructura de Tablas

### `niveles_especialidades`
Almacena los niveles definidos para cada especialidad.

**Columnas:**
- `id`: Identificador único del nivel
- `especialidad_id`: FK a `especialidades_areas`
- `nombre`: Nombre del nivel (ej: "Junior", "Semi-Senior", "Senior")
- `descripcion`: Descripción opcional del nivel
- `orden`: Orden de visualización (numérico)
- `activo`: Estado del nivel (activo/inactivo)
- `creado_en`: Fecha de creación

### `candidato_especialidades` (modificada)
Ahora usa una FK a `niveles_especialidades` en lugar de un ENUM fijo.

**Columnas modificadas:**
- `nivel_id`: FK a `niveles_especialidades` (reemplaza la columna `nivel` ENUM)

## Migración

### Paso 1: Ejecutar el Script SQL
Ejecuta el archivo `migracion_niveles_especialidades.sql` en tu base de datos:

```bash
mysql -u usuario -p nombre_base_datos < migracion_niveles_especialidades.sql
```

O desde phpMyAdmin/MySQL Workbench importando el archivo.

### Paso 2: Verificar Niveles por Defecto
El script crea automáticamente 4 niveles para cada especialidad activa:
- Básico
- Intermedio
- Avanzado
- Experto

### Paso 3: Migrar Datos Existentes (Opcional)
Si tienes datos en la tabla `candidato_especialidades_backup` con niveles ENUM antiguos, ejecuta:

```sql
UPDATE candidato_especialidades ce
INNER JOIN candidato_especialidades_backup ceb ON ce.id = ceb.id
INNER JOIN niveles_especialidades ne ON ne.especialidad_id = ce.especialidad_id 
  AND ne.nombre = ceb.nivel
SET ce.nivel_id = ne.id
WHERE ceb.nivel IS NOT NULL;
```

## Uso en el Dashboard

### Gestión de Niveles en `areas.php`

1. **Ver Niveles de una Especialidad:**
   - En la lista de especialidades, haz clic en el botón **"Niveles"**
   - Se desplegará la sección con todos los niveles de esa especialidad

2. **Agregar un Nuevo Nivel:**
   - En la sección de niveles desplegada, completa el formulario:
     - **Nombre**: Nombre del nivel (requerido)
     - **Descripción**: Descripción opcional
     - **Orden**: Número para ordenar los niveles
   - Haz clic en **"+ Agregar Nivel"**

3. **Editar un Nivel:**
   - Haz clic en el botón **"Editar"** del nivel
   - Modifica los datos en el modal
   - Haz clic en **"Actualizar"**

4. **Activar/Desactivar un Nivel:**
   - Haz clic en el botón **"On"** o **"Off"** según el estado actual
   - Los niveles inactivos no aparecerán en formularios de selección

5. **Eliminar un Nivel:**
   - Haz clic en el botón **"×"** (eliminar)
   - Confirma la eliminación
   - **Nota:** No se puede eliminar un nivel que esté asignado a candidatos

6. **Cambiar Orden:**
   - Cambia el número en el campo **"Orden"**
   - El formulario se enviará automáticamente

## Uso en Formularios de Candidatos

### Endpoint AJAX: `obtener_niveles.php`
Obtiene los niveles disponibles para una especialidad específica.

**Parámetros:**
- `especialidad_id` (int): ID de la especialidad

**Respuesta JSON:**
```json
[
  {
    "id": 1,
    "nombre": "Junior",
    "descripcion": "1-2 años de experiencia",
    "orden": 1
  },
  {
    "id": 2,
    "nombre": "Semi-Senior",
    "descripcion": "3-5 años de experiencia",
    "orden": 2
  }
]
```

### Ejemplo de Uso en JavaScript
```javascript
// Cuando se selecciona una especialidad
document.getElementById('especialidad').addEventListener('change', function() {
    const especialidadId = this.value;
    
    if (especialidadId) {
        fetch(`obtener_niveles.php?especialidad_id=${especialidadId}`)
            .then(response => response.json())
            .then(niveles => {
                const selectNivel = document.getElementById('nivel');
                selectNivel.innerHTML = '<option value="">Seleccione un nivel</option>';
                
                niveles.forEach(nivel => {
                    const option = document.createElement('option');
                    option.value = nivel.id;
                    option.textContent = nivel.nombre;
                    if (nivel.descripcion) {
                        option.title = nivel.descripcion;
                    }
                    selectNivel.appendChild(option);
                });
                
                selectNivel.disabled = false;
            })
            .catch(error => {
                console.error('Error al cargar niveles:', error);
            });
    }
});
```

## Personalización de Niveles

### Ejemplo: Área de Desarrollo

**Especialidad: Frontend Developer**
- Niveles personalizados:
  - Junior Frontend (0-2 años)
  - Mid-Level Frontend (2-4 años)
  - Senior Frontend (4-7 años)
  - Lead Frontend (7+ años)

**Especialidad: Backend Developer**
- Niveles personalizados:
  - Junior Backend
  - Mid-Level Backend
  - Senior Backend
  - Architect

### Ejemplo: Área de Diseño

**Especialidad: Diseño UX/UI**
- Niveles personalizados:
  - Diseñador Junior
  - Diseñador
  - Diseñador Senior
  - Lead Designer

**Especialidad: Diseño Gráfico**
- Niveles personalizados:
  - Principiante
  - Intermedio
  - Avanzado
  - Director de Arte

## Ventajas del Sistema

1. **Flexibilidad:** Cada especialidad puede tener sus propios niveles específicos
2. **Escalabilidad:** Fácil agregar, modificar o eliminar niveles sin cambiar código
3. **Mantenibilidad:** Gestión completa desde la interfaz administrativa
4. **Precisión:** Mayor granularidad en la clasificación de habilidades
5. **Contextual:** Niveles que tienen sentido específico para cada especialidad

## Consideraciones

- Los niveles inactivos no aparecen en formularios pero se mantienen en la base de datos
- No se pueden eliminar niveles que estén asignados a candidatos
- El campo `orden` determina el orden de visualización en los formularios
- Las descripciones son opcionales pero recomendadas para claridad

## Soporte

Para más información o reportar problemas, contacta al equipo de desarrollo.
