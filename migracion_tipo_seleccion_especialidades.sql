-- Migración: Agregar campo tipo_seleccion a especialidades_areas
-- Este campo determina si los niveles de una especialidad permiten selección múltiple o única

-- Agregar columna tipo_seleccion
ALTER TABLE especialidades_areas 
ADD COLUMN tipo_seleccion ENUM('unica', 'multiple') NOT NULL DEFAULT 'multiple' 
COMMENT 'Tipo de selección: "unica" = solo un nivel (select/radio), "multiple" = varios niveles (checkboxes)'
AFTER activa;

-- Actualizar especialidades existentes según el caso de uso típico
-- Por defecto se deja en 'multiple' pero puedes ajustar según tus necesidades

-- Ejemplos de configuración recomendada:

-- Para habilidades específicas (Microsoft Office, Herramientas, etc.) -> MULTIPLE
UPDATE especialidades_areas 
SET tipo_seleccion = 'multiple' 
WHERE nombre LIKE '%Office%' 
   OR nombre LIKE '%Herramienta%'
   OR nombre LIKE '%Software%'
   OR nombre LIKE '%Tecnología%'
   OR nombre LIKE '%Lenguaje%';

-- Para certificaciones o niveles de experiencia -> ÚNICA
UPDATE especialidades_areas 
SET tipo_seleccion = 'unica' 
WHERE nombre LIKE '%Certificación%'
   OR nombre LIKE '%Nivel de%'
   OR nombre LIKE '%Experiencia en%';

-- Verificar los cambios
SELECT 
    ea.id,
    ap.nombre as area,
    ea.nombre as especialidad,
    ea.tipo_seleccion,
    COUNT(ne.id) as niveles_count
FROM especialidades_areas ea
INNER JOIN areas_profesionales ap ON ea.area_profesional_id = ap.id
LEFT JOIN niveles_especialidades ne ON ea.id = ne.especialidad_id
WHERE ea.activa = 1
GROUP BY ea.id
ORDER BY ap.nombre, ea.nombre;
