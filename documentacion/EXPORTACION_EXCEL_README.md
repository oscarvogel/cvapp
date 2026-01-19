# Funcionalidad de Exportación a Excel

## Descripción
Se ha implementado una funcionalidad completa para exportar todos los datos filtrados del dashboard a un archivo Excel (.xls).

## Archivos Creados/Modificados

### 1. `/admin/exportar_excel.php` (NUEVO)
Archivo que genera el archivo Excel con todos los datos filtrados.

**Características:**
- Respeta todos los filtros aplicados en el dashboard (búsqueda, área, estado, experiencia, fechas)
- Exporta TODOS los registros que coincidan (sin límite de paginación)
- Incluye columnas completas con toda la información del candidato
- Formato Excel (.xls) compatible con Microsoft Excel y LibreOffice
- Codificación UTF-8 con BOM para correcta visualización de caracteres especiales
- Nombre de archivo con timestamp: `candidatos_YYYY-MM-DD_HHmmss.xls`

**Columnas exportadas:**
1. ID
2. Nombre
3. Email
4. Teléfono
5. DNI
6. Edad
7. Estado Civil
8. Nacionalidad
9. Lugar de Residencia
10. Experiencia (años)
11. Áreas Profesionales
12. Estado
13. Antecedentes Penales
14. Licencia de Conducir
15. Disponibilidad
16. Especialidades (con niveles)
17. Experiencia Laboral (detallada)
18. Fecha de Registro
19. Observaciones

### 2. `/admin/dashboard.php` (MODIFICADO)
Se agregó el botón de exportación y la funcionalidad JavaScript.

**Cambios realizados:**
- ✅ Botón "Exportar a Excel" agregado en la sección de filtros
- ✅ Diseño consistente con el estilo del dashboard (verde/esmeralda)
- ✅ Icono de descarga SVG
- ✅ Función JavaScript `exportarExcel()` que:
  - Captura los filtros actuales de la URL
  - Muestra diálogo de confirmación con SweetAlert2
  - Lista la información que se exportará
  - Abre el archivo de exportación en una nueva ventana
  - Muestra notificación toast de éxito

## Uso

1. **Aplicar filtros** (opcional):
   - Búsqueda por texto
   - Filtrar por área profesional
   - Filtrar por estado
   - Filtrar por experiencia (mín/máx)
   - Filtrar por rango de fechas

2. **Hacer clic en "Exportar a Excel"**:
   - Se mostrará un diálogo de confirmación
   - Se detalla qué información se exportará
   - Se puede cancelar o confirmar la exportación

3. **Descarga automática**:
   - El archivo se descargará automáticamente
   - Nombre del archivo incluye fecha y hora
   - Formato compatible con Excel

## Características Técnicas

### Consulta SQL Optimizada
```sql
- Usa LEFT JOIN para incluir datos relacionados
- GROUP_CONCAT para agrupar múltiples registros relacionados
- Sin límite LIMIT/OFFSET para exportar todos los datos
- Mismos filtros que el dashboard para consistencia
```

### Formato del Archivo
```
- Content-Type: application/vnd.ms-excel
- Codificación: UTF-8 con BOM (ï»¿)
- Estructura HTML con tabla
- Estilos CSS embebidos para formato
- Encabezados con color azul (#4472C4)
- Filas alternas con fondo gris (#F2F2F2)
```

### Seguridad
- ✅ Requiere autenticación (require_admin())
- ✅ Usa funciones safe_trim() y safe_int()
- ✅ Validación de fechas con valid_date()
- ✅ htmlspecialchars() para prevenir XSS
- ✅ Prepared statements para prevenir SQL injection

## Ventajas

1. **Exportación Completa**: No está limitada por la paginación del dashboard
2. **Filtros Consistentes**: Respeta exactamente los mismos filtros aplicados
3. **Información Detallada**: Incluye todos los datos del candidato en un solo archivo
4. **Formato Universal**: Compatible con Excel, LibreOffice, Google Sheets
5. **UTF-8 Compatible**: Soporte completo para caracteres especiales (ñ, acentos, etc.)
6. **UX Mejorada**: Diálogo de confirmación informativo y notificaciones visuales

## Ejemplo de Uso

### Caso 1: Exportar todos los candidatos
```
1. Ir al dashboard sin filtros
2. Click en "Exportar a Excel"
3. Confirmar
4. Se descarga archivo con TODOS los candidatos
```

### Caso 2: Exportar candidatos filtrados
```
1. Aplicar filtros (ej: Área = "Ventas", Estado = "Seleccionado")
2. Click en "Exportar a Excel"
3. Confirmar
4. Se descarga archivo solo con candidatos de Ventas seleccionados
```

### Caso 3: Exportar búsqueda específica
```
1. Buscar "Juan" en el campo de búsqueda
2. Click en "Exportar a Excel"
3. Confirmar
4. Se descarga archivo con todos los candidatos llamados Juan
```

## Notas Importantes

- La exportación puede tardar unos segundos si hay muchos registros
- El archivo se abre automáticamente o se guarda según configuración del navegador
- Si no hay resultados con los filtros aplicados, se exporta una tabla vacía con mensaje
- El timestamp en el nombre del archivo evita sobreescritura accidental

## Fecha de Implementación
31 de octubre de 2025
