# Sistema de Especialidades con Múltiples Niveles

## 🎯 Cambio Implementado

Se ha modificado el sistema de especialidades para permitir la **selección múltiple de niveles** dentro de cada especialidad. Esto es ideal para casos como:

### Ejemplo Real: Área Administrativa - Microsoft Office

**Antes (Select único):**
```
Especialidad: Microsoft Office
Nivel: [ Seleccionar: Básico, Intermedio, Avanzado ]
```

**Ahora (Checkboxes múltiples):**
```
Especialidad: Microsoft Office
  ☑ Excel - Avanzado
  ☑ Word - Intermedio
  ☐ PowerPoint - Básico
  ☑ PowerBI - Experto
  ☑ Access - Intermedio
```

## 📋 Configuración en el Backend

### Paso 1: Configurar Especialidad

En `admin/areas.php`:

1. **Área:** Administrativa
2. **Especialidad:** Microsoft Office
3. **Niveles configurados:**
   - Excel
   - Word
   - PowerPoint
   - PowerBI
   - Access
   - Outlook

### Paso 2: Ejemplo Alternativo - Desarrollo

**Área:** Desarrollo
**Especialidad:** Frontend Technologies

**Niveles (cada uno representa una tecnología):**
- HTML/CSS
- JavaScript
- React
- Vue.js
- Angular
- TypeScript
- Bootstrap
- Tailwind CSS

### Paso 3: Ejemplo - Diseño

**Área:** Diseño
**Especialidad:** Herramientas de Diseño

**Niveles:**
- Adobe Photoshop
- Adobe Illustrator
- Figma
- Sketch
- Adobe XD
- InVision

## 🎨 Interfaz de Usuario

### Formulario del Candidato

Al seleccionar un área, se muestran las especialidades con checkboxes:

```
┌─────────────────────────────────────────────────┐
│ 📄 Habilidades en Administrativa                │
├─────────────────────────────────────────────────┤
│                                                 │
│ ⚡ Microsoft Office                             │
│    ☑ Excel                                      │
│    ☑ Word                                       │
│    ☐ PowerPoint                                 │
│    ☑ PowerBI                                    │
│    ☑ Access                                     │
│    ☐ Outlook                                    │
│                                                 │
│ ⚡ Gestión de Proyectos                         │
│    ☑ Asana                                      │
│    ☑ Trello                                     │
│    ☐ Monday.com                                 │
│                                                 │
└─────────────────────────────────────────────────┘
```

### Vista en el Dashboard

```
Administrativa
  ┗━ Microsoft Office
     • Excel
     • Word
     • PowerBI
     • Access
  
  ┗━ Gestión de Proyectos
     • Asana
     • Trello
```

## 💾 Estructura de Datos

### Tabla: `candidato_especialidades`

Ahora almacena **múltiples registros** por candidato y especialidad:

```sql
candidato_id | especialidad_id | nivel_id | Significado
-------------|-----------------|----------|---------------------------
1            | 5               | 10       | Candidato 1, Office, Excel
1            | 5               | 11       | Candidato 1, Office, Word
1            | 5               | 14       | Candidato 1, Office, PowerBI
1            | 5               | 15       | Candidato 1, Office, Access
```

### Consulta de Ejemplo

```sql
SELECT 
    c.nombre as candidato,
    ea.nombre as especialidad,
    GROUP_CONCAT(ne.nombre SEPARATOR ', ') as herramientas
FROM candidato_especialidades ce
INNER JOIN candidatos c ON ce.candidato_id = c.id
INNER JOIN especialidades_areas ea ON ce.especialidad_id = ea.id
INNER JOIN niveles_especialidades ne ON ce.nivel_id = ne.id
WHERE c.id = 1
GROUP BY c.id, ea.id;
```

**Resultado:**
```
candidato    | especialidad      | herramientas
-------------|-------------------|-------------------------
Juan Pérez   | Microsoft Office  | Excel, Word, PowerBI, Access
Juan Pérez   | Gestión Proyectos | Asana, Trello
```

## 🔧 Implementación Técnica

### Frontend (index.php)

**HTML generado:**
```html
<input 
    type="checkbox" 
    name="especialidades[5][niveles][]"
    value="10"
    id="nivel_5_10"
/>
<label for="nivel_5_10">Excel</label>
```

**Array enviado:**
```php
$_POST['especialidades'] = [
    5 => [ // ID de especialidad "Microsoft Office"
        'niveles' => [10, 11, 14, 15] // IDs de Excel, Word, PowerBI, Access
    ]
];
```

### Backend (upload.php)

```php
foreach ($especialidades as $especialidad_id => $esp_data) {
    if (isset($esp_data['niveles']) && is_array($esp_data['niveles'])) {
        foreach ($esp_data['niveles'] as $nivel_id) {
            // Insertar cada nivel seleccionado
            INSERT INTO candidato_especialidades 
            (candidato_id, especialidad_id, nivel_id)
            VALUES (?, ?, ?)
        }
    }
}
```

### Visualización (candidato-detalle.php)

**Agrupación de datos:**
```php
// Agrupa múltiples niveles de la misma especialidad
$especialidades_por_area[$area_id]['especialidades'][$especialidad_id] = [
    'especialidad_nombre' => 'Microsoft Office',
    'niveles' => [
        ['nivel_nombre' => 'Excel', ...],
        ['nivel_nombre' => 'Word', ...],
        ['nivel_nombre' => 'PowerBI', ...],
        ['nivel_nombre' => 'Access', ...]
    ]
];
```

## 📊 Casos de Uso Prácticos

### 1. Área Administrativa

**Especialidad: Microsoft Office**
- Niveles: Excel, Word, PowerPoint, Outlook, Access, PowerBI, Teams, OneDrive

**Especialidad: Gestión Empresarial**
- Niveles: SAP, Oracle, Contabilidad, Nómina, Facturación

### 2. Área Desarrollo

**Especialidad: Lenguajes Backend**
- Niveles: PHP, Python, Java, Node.js, Ruby, Go, C#

**Especialidad: Frameworks Backend**
- Niveles: Laravel, Django, Express, Spring, Rails

**Especialidad: Bases de Datos**
- Niveles: MySQL, PostgreSQL, MongoDB, Redis, SQL Server

### 3. Área Marketing

**Especialidad: Redes Sociales**
- Niveles: Facebook Ads, Instagram, LinkedIn, TikTok, Twitter, YouTube

**Especialidad: Analytics**
- Niveles: Google Analytics, Facebook Insights, SEMrush, Ahrefs

### 4. Área Diseño

**Especialidad: Software 2D**
- Niveles: Photoshop, Illustrator, Figma, Sketch, Canva

**Especialidad: Software 3D**
- Niveles: Blender, Maya, 3ds Max, Cinema 4D

## 🎓 Configuración Recomendada

### Opción 1: Herramientas Específicas
- **Especialidad:** Nombre genérico (ej: "Ofimática")
- **Niveles:** Herramientas específicas (Excel, Word, etc.)
- **Ventaja:** Muy específico y detallado

### Opción 2: Tecnologías con Niveles de Experiencia
- **Especialidad:** Tecnología específica (ej: "Excel")
- **Niveles:** Nivel de dominio (Básico, Intermedio, Avanzado, Experto)
- **Ventaja:** Tradicional, mide nivel de conocimiento

### Opción 3: Híbrido (Recomendado)
- **Especialidad:** Categoría amplia (ej: "Microsoft Office")
- **Niveles:** Herramientas específicas con descripción del nivel
  - "Excel (Avanzado)"
  - "Word (Intermedio)"
  - "PowerBI (Experto)"

## 🔍 Búsqueda y Filtrado

Para buscar candidatos por habilidades específicas:

```sql
-- Buscar candidatos que dominan Excel y PowerBI
SELECT DISTINCT c.*
FROM candidatos c
INNER JOIN candidato_especialidades ce ON c.id = ce.candidato_id
INNER JOIN niveles_especialidades ne ON ce.nivel_id = ne.id
WHERE ne.nombre IN ('Excel', 'PowerBI')
GROUP BY c.id
HAVING COUNT(DISTINCT ne.nombre) = 2;
```

## ✅ Ventajas del Sistema

1. **Flexibilidad Total:** Cada área/especialidad se configura según necesidades
2. **Granularidad:** Detalle preciso de habilidades específicas
3. **Escalabilidad:** Fácil agregar nuevas herramientas/tecnologías
4. **Multi-selección:** Candidatos indican todas sus habilidades
5. **Sin Límites:** No hay límite en la cantidad de habilidades por especialidad

## 🚀 Migración desde Sistema Anterior

Si ya tienes datos con el sistema antiguo (1 especialidad = 1 nivel):

```sql
-- Los datos existentes siguen funcionando
-- Cada registro antiguo representa una especialidad con un nivel
-- El nuevo sistema simplemente permite múltiples registros para la misma especialidad
```

No se requiere migración especial, ambos sistemas son compatibles a nivel de base de datos.

## 📝 Notas Importantes

1. Los candidatos pueden seleccionar 0 o más niveles por especialidad
2. Si no seleccionan ninguno, esa especialidad no se guarda
3. El orden de los niveles depende del campo `orden` en `niveles_especialidades`
4. Los tooltips muestran las descripciones configuradas para cada nivel
5. La visualización agrupa automáticamente múltiples niveles de la misma especialidad

---

**Fecha de implementación:** 20 de octubre de 2025
**Versión:** 3.0
