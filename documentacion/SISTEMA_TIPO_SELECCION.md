# Sistema de Tipo de Selección de Niveles

## Descripción General

Este sistema permite configurar si cada especialidad permite selección **única** o **múltiple** de niveles, proporcionando flexibilidad para diferentes casos de uso:

- **Selección Única (◉)**: El usuario puede seleccionar solo un nivel (ej: Básico, Intermedio, Avanzado, Experto)
- **Selección Múltiple (☑)**: El usuario puede seleccionar varios niveles (ej: Excel, Word, PowerBI, Outlook)

## Cambios en la Base de Datos

### 1. Migración SQL

**Archivo**: `migracion_tipo_seleccion_especialidades.sql`

```sql
-- Agregar columna tipo_seleccion a especialidades_areas
ALTER TABLE especialidades_areas 
ADD COLUMN tipo_seleccion ENUM('unica', 'multiple') NOT NULL DEFAULT 'multiple'
AFTER orden;
```

**Campo agregado:**
- **Nombre**: `tipo_seleccion`
- **Tipo**: `ENUM('unica', 'multiple')`
- **Por defecto**: `'multiple'`
- **Ubicación**: Después del campo `orden`

### 2. Ejecutar la Migración

```bash
# Desde la línea de comandos MySQL
mysql -u root -p nombre_base_datos < migracion_tipo_seleccion_especialidades.sql

# O desde phpMyAdmin, importar el archivo SQL
```

## Modificaciones en el Backend

### 1. Admin Interface (`admin/areas.php`)

#### a) Consulta SQL actualizada:
```php
$esp_stmt = $pdo->query("SELECT id, area_profesional_id, nombre, orden, activa, tipo_seleccion 
                         FROM especialidades_areas 
                         ORDER BY area_profesional_id, orden ASC, nombre ASC");
```

#### b) Handler para agregar especialidad:
```php
case 'add_especialidad':
    $tipo_seleccion = safe_trim($_POST['tipo_seleccion'] ?? 'multiple');
    if (!in_array($tipo_seleccion, ['unica', 'multiple'])) {
        $tipo_seleccion = 'multiple';
    }
    
    $stmt = $pdo->prepare('INSERT INTO especialidades_areas 
                          (area_profesional_id, nombre, orden, tipo_seleccion) 
                          VALUES (?, ?, ?, ?)');
    $stmt->execute([$area_id, $nombre, $orden, $tipo_seleccion]);
```

#### c) Handler para editar especialidad:
```php
case 'edit_especialidad':
    $tipo_seleccion = safe_trim($_POST['tipo_seleccion'] ?? 'multiple');
    if (!in_array($tipo_seleccion, ['unica', 'multiple'])) {
        $tipo_seleccion = 'multiple';
    }
    
    $stmt = $pdo->prepare('UPDATE especialidades_areas 
                          SET nombre = ?, orden = ?, tipo_seleccion = ? 
                          WHERE id = ? AND area_profesional_id = ?');
    $stmt->execute([$nombre, $orden, $tipo_seleccion, $id, $area_id]);
```

#### d) Formulario de agregar:
```html
<div class="grid grid-cols-1 md:grid-cols-4 gap-4">
    <div class="md:col-span-2">
        <label for="nueva_especialidad_nombre">Nombre</label>
        <input type="text" name="nombre" required maxlength="100">
    </div>
    
    <div>
        <label for="nueva_especialidad_tipo_seleccion">Tipo de selección</label>
        <select name="tipo_seleccion">
            <option value="multiple">☑ Múltiple</option>
            <option value="unica">◉ Única</option>
        </select>
        <p class="text-xs text-gray-500 mt-1">
            <strong>Múltiple:</strong> Permite seleccionar varios niveles (ej: Excel, Word, PowerBI).<br>
            <strong>Única:</strong> Permite seleccionar solo un nivel (ej: Básico, Intermedio, Avanzado).
        </p>
    </div>
    
    <div>
        <label for="nueva_especialidad_orden">Orden</label>
        <input type="number" name="orden" min="0" max="255" value="0">
    </div>
</div>
```

#### e) Modal de editar:
```html
<div id="editEspecialidadModal">
    <form method="post">
        <input type="hidden" name="id" id="editEspecialidadId">
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label for="editEspecialidadNombre">Nombre</label>
                <input type="text" id="editEspecialidadNombre" name="nombre">
            </div>
            
            <div>
                <label for="editEspecialidadTipoSeleccion">Tipo de selección</label>
                <select id="editEspecialidadTipoSeleccion" name="tipo_seleccion">
                    <option value="multiple">☑ Múltiple</option>
                    <option value="unica">◉ Única</option>
                </select>
            </div>
            
            <div>
                <label for="editEspecialidadOrden">Orden</label>
                <input type="number" id="editEspecialidadOrden" name="orden">
            </div>
        </div>
    </form>
</div>
```

#### f) Función JavaScript para abrir modal:
```javascript
function openEditEspecialidadModal(id, nombre, orden, areaId, tipoSeleccion = 'multiple') {
  document.getElementById('editEspecialidadId').value = id;
  document.getElementById('editEspecialidadNombre').value = nombre;
  document.getElementById('editEspecialidadOrden').value = orden;
  document.getElementById('editEspecialidadArea').value = areaId;
  document.getElementById('editEspecialidadTipoSeleccion').value = tipoSeleccion;
  // ... resto del código
}
```

#### g) Visualización en la lista:
```html
<div class="text-sm font-medium text-gray-900">
    <?php if (($esp['tipo_seleccion'] ?? 'multiple') === 'multiple'): ?>
        <span class="text-blue-600" title="Selección múltiple">☑</span>
    <?php else: ?>
        <span class="text-purple-600" title="Selección única">◉</span>
    <?php endif; ?>
    <?= e($esp['nombre']) ?>
</div>
<div class="text-xs text-gray-500">
    Orden: <?= (int)$esp['orden'] ?> | 
    ID: <?= (int)$esp['id'] ?> | 
    <?= $esp['activa'] ? 'Activa' : 'Inactiva' ?> | 
    Tipo: <?= ($esp['tipo_seleccion'] ?? 'multiple') === 'multiple' ? 'Múltiple' : 'Única' ?>
</div>
```

### 2. Endpoint de especialidades (`obtener_especialidades.php`)

```php
$stmt = $pdo->prepare('SELECT id, nombre, tipo_seleccion 
                       FROM especialidades_areas 
                       WHERE area_profesional_id = ? AND activa = 1 
                       ORDER BY orden, nombre');
$stmt->execute([$area_id]);
$especialidades = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($especialidades);
```

**Respuesta JSON:**
```json
[
    {
        "id": "1",
        "nombre": "Herramientas de Office",
        "tipo_seleccion": "multiple"
    },
    {
        "id": "2",
        "nombre": "Nivel de Experiencia",
        "tipo_seleccion": "unica"
    }
]
```

## Modificaciones en el Frontend

### 1. Función JavaScript actualizada (`index.php`)

```javascript
async function cargarEspecialidades(areaId, areaNombre) {
    const response = await fetch(`obtener_especialidades.php?area_id=${areaId}`);
    const especialidades = await response.json();
    
    for (const esp of especialidades) {
        const niveles = await cargarNiveles(esp.id);
        const tipoSeleccion = esp.tipo_seleccion || 'multiple';
        
        if (tipoSeleccion === 'unica') {
            // Renderizar RADIO BUTTONS
            html += `<div class="space-y-2 ml-6">`;
            niveles.forEach(nivel => {
                html += `
                    <label class="flex items-center gap-2 p-2 rounded hover:bg-purple-50 cursor-pointer">
                        <input 
                            type="radio" 
                            name="especialidades[${esp.id}][niveles][]"
                            value="${nivel.id}"
                            class="w-4 h-4 text-purple-600 border-gray-300 focus:ring-purple-500"
                        />
                        <span class="text-sm">${nivel.nombre}</span>
                    </label>
                `;
            });
            html += `</div>`;
        } else {
            // Renderizar CHECKBOXES
            html += `<div class="grid grid-cols-1 sm:grid-cols-2 gap-2 ml-6">`;
            niveles.forEach(nivel => {
                html += `
                    <label class="flex items-center gap-2 p-2 rounded hover:bg-blue-50 cursor-pointer">
                        <input 
                            type="checkbox" 
                            name="especialidades[${esp.id}][niveles][]"
                            value="${nivel.id}"
                            class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                        />
                        <span class="text-sm">${nivel.nombre}</span>
                    </label>
                `;
            });
            html += `</div>`;
        }
    }
}
```

### 2. Diferencias visuales:

#### Selección Única (Radio buttons):
- Color **morado** (`purple-600`)
- Hover: `bg-purple-50`
- Texto: "(Selecciona uno)"
- Layout: Lista vertical (1 columna)

#### Selección Múltiple (Checkboxes):
- Color **azul** (`blue-600`)
- Hover: `bg-blue-50`
- Texto: "(Selecciona uno o más)"
- Layout: Grid 2 columnas en pantallas grandes

## Casos de Uso

### Ejemplo 1: Herramientas de Office (Múltiple)

**Configuración:**
- Nombre: "Herramientas de Office"
- Tipo: `multiple` (☑)
- Niveles: Excel, Word, PowerBI, Outlook, Access

**Resultado en formulario:**
```
☑ Herramientas de Office (Selecciona uno o más)
  ☐ Excel
  ☐ Word
  ☐ PowerBI
  ☐ Outlook
  ☐ Access
```

**Datos enviados:**
```php
$_POST['especialidades'][1]['niveles'] = [2, 3, 4]; // Excel, Word, PowerBI
```

### Ejemplo 2: Nivel de Experiencia (Única)

**Configuración:**
- Nombre: "Nivel de Experiencia en Java"
- Tipo: `unica` (◉)
- Niveles: Básico, Intermedio, Avanzado, Experto

**Resultado en formulario:**
```
◉ Nivel de Experiencia en Java (Selecciona uno)
  ○ Básico
  ○ Intermedio
  ● Avanzado  ← seleccionado
  ○ Experto
```

**Datos enviados:**
```php
$_POST['especialidades'][2]['niveles'] = [3]; // Avanzado
```

## Procesamiento en Backend

El archivo `upload.php` ya maneja ambos casos correctamente:

```php
foreach ($especialidades_data as $esp_id => $esp_data) {
    $especialidad_id = safe_int($esp_id);
    
    if (!empty($esp_data['niveles']) && is_array($esp_data['niveles'])) {
        foreach ($esp_data['niveles'] as $nivel_id) {
            $nivel_id = safe_int($nivel_id);
            
            // Verificar que el nivel pertenece a la especialidad
            $check = $pdo->prepare('SELECT id FROM niveles_especialidades 
                                   WHERE id = ? AND especialidad_id = ?');
            $check->execute([$nivel_id, $especialidad_id]);
            
            if ($check->fetch()) {
                $stmt->execute([$candidato_id, $especialidad_id, $nivel_id]);
            }
        }
    }
}
```

- Para **selección única**: `$esp_data['niveles']` contendrá un array con 1 elemento
- Para **selección múltiple**: `$esp_data['niveles']` contendrá un array con N elementos

## Visualización en Detalle del Candidato

El archivo `admin/candidato-detalle.php` ya agrupa correctamente:

```php
// Consulta con JOIN
$stmt = $pdo->prepare('
    SELECT 
        ce.especialidad_id,
        ce.nivel_id,
        ea.nombre AS especialidad_nombre,
        ne.nombre AS nivel_nombre,
        ap.nombre AS area_nombre
    FROM candidato_especialidades ce
    LEFT JOIN especialidades_areas ea ON ce.especialidad_id = ea.id
    LEFT JOIN niveles_especialidades ne ON ce.nivel_id = ne.id
    LEFT JOIN areas_profesionales ap ON ea.area_profesional_id = ap.id
    WHERE ce.candidato_id = ?
    ORDER BY ap.orden, ea.orden, ne.orden
');

// Agrupación por área → especialidad → niveles[]
foreach ($rows as $row) {
    $areas[$row['area_nombre']]['especialidades'][$row['especialidad_nombre']][] = $row['nivel_nombre'];
}
```

**Visualización:**
```
📂 Administrativo
  └─ Herramientas de Office: Excel, Word, PowerBI

📂 Desarrollo
  └─ Nivel de Java: Avanzado
```

## Iconos y Colores

| Tipo | Icono | Color | Uso |
|------|-------|-------|-----|
| Múltiple | ☑ | Azul (`blue-600`) | Checkboxes |
| Única | ◉ | Morado (`purple-600`) | Radio buttons |

## Testing

### 1. Verificar migración:
```sql
DESCRIBE especialidades_areas;
-- Verificar que existe la columna tipo_seleccion ENUM('unica','multiple')
```

### 2. Probar en admin:
1. Ir a `admin/areas.php`
2. Crear especialidad con tipo "Múltiple"
3. Crear especialidad con tipo "Única"
4. Editar una especialidad y cambiar su tipo
5. Verificar que los iconos ☑ y ◉ aparecen correctamente

### 3. Probar en formulario:
1. Ir a `index.php`
2. Seleccionar un área profesional
3. Verificar que las especialidades "múltiple" muestran checkboxes
4. Verificar que las especialidades "única" muestran radio buttons
5. Enviar formulario con ambos tipos

### 4. Verificar en detalle:
1. Ver candidato en `admin/candidato-detalle.php`
2. Confirmar que se muestran todos los niveles seleccionados
3. Para especialidades únicas: debe mostrar solo 1 nivel
4. Para especialidades múltiples: puede mostrar N niveles

## Troubleshooting

### Problema: Columna tipo_seleccion no existe
```sql
-- Ejecutar migración:
ALTER TABLE especialidades_areas 
ADD COLUMN tipo_seleccion ENUM('unica', 'multiple') NOT NULL DEFAULT 'multiple'
AFTER orden;
```

### Problema: Los radio buttons no funcionan
- Verificar que todos los radio buttons de una especialidad tengan el **mismo** `name`
- El `name` debe ser: `especialidades[{esp.id}][niveles][]`

### Problema: No aparecen los iconos ☑ y ◉
- Verificar que el charset es UTF-8
- Agregar `<meta charset="UTF-8">` en el HTML

### Problema: Especialidades antiguas sin tipo_seleccion
```sql
-- Actualizar especialidades existentes al valor por defecto
UPDATE especialidades_areas 
SET tipo_seleccion = 'multiple' 
WHERE tipo_seleccion IS NULL;
```

## Resumen de Archivos Modificados

1. **migracion_tipo_seleccion_especialidades.sql** - Nueva migración
2. **admin/areas.php** - Formularios, handlers, visualización
3. **obtener_especialidades.php** - Incluir tipo_seleccion en JSON
4. **index.php** - Renderizado condicional (radio vs checkbox)
5. **upload.php** - Sin cambios (ya manejaba arrays)
6. **admin/candidato-detalle.php** - Sin cambios (ya agrupaba correctamente)

## Próximos Pasos

1. ✅ Ejecutar migración SQL
2. ✅ Probar agregar especialidades con ambos tipos
3. ✅ Probar editar especialidades
4. ✅ Verificar formulario frontend
5. ✅ Enviar CVs de prueba
6. ✅ Verificar visualización en detalle

---

**Fecha de implementación**: 2025
**Versión**: 1.0
**Autor**: Sistema CVApp
