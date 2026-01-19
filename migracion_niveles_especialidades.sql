-- Migración para gestionar niveles de especialidades de forma flexible
-- Permite asignar diferentes niveles a cada especialidad

-- Crear tabla para niveles de especialidades
CREATE TABLE IF NOT EXISTS niveles_especialidades (
  id INT AUTO_INCREMENT PRIMARY KEY,
  especialidad_id INT NOT NULL,
  nombre VARCHAR(50) NOT NULL,
  descripcion TEXT NULL,
  orden TINYINT UNSIGNED NOT NULL DEFAULT 0,
  activo BOOLEAN NOT NULL DEFAULT TRUE,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (especialidad_id) REFERENCES especialidades_areas(id) ON DELETE CASCADE ON UPDATE CASCADE,
  INDEX idx_especialidad (especialidad_id),
  INDEX idx_activo (activo),
  INDEX idx_orden (orden),
  UNIQUE KEY unique_especialidad_nivel (especialidad_id, nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Actualizar tabla candidato_especialidades
-- Primero respaldamos los datos existentes con el nivel ENUM
CREATE TABLE IF NOT EXISTS candidato_especialidades_backup AS 
SELECT * FROM candidato_especialidades;

-- Eliminar la columna nivel ENUM si existe
ALTER TABLE candidato_especialidades 
DROP COLUMN IF EXISTS nivel;

-- Agregar columna para FK a niveles_especialidades
ALTER TABLE candidato_especialidades 
ADD COLUMN nivel_id INT NULL AFTER especialidad_id,
ADD FOREIGN KEY (nivel_id) REFERENCES niveles_especialidades(id) ON DELETE SET NULL ON UPDATE CASCADE,
ADD INDEX idx_nivel (nivel_id);

-- Insertar niveles por defecto para todas las especialidades existentes
-- Estos son niveles genéricos que luego se pueden personalizar por especialidad
INSERT INTO niveles_especialidades (especialidad_id, nombre, descripcion, orden)
SELECT 
  id as especialidad_id,
  'Básico' as nombre,
  'Conocimientos fundamentales de la especialidad' as descripcion,
  1 as orden
FROM especialidades_areas
WHERE activa = 1;

INSERT INTO niveles_especialidades (especialidad_id, nombre, descripcion, orden)
SELECT 
  id as especialidad_id,
  'Intermedio' as nombre,
  'Conocimientos y experiencia práctica en la especialidad' as descripcion,
  2 as orden
FROM especialidades_areas
WHERE activa = 1;

INSERT INTO niveles_especialidades (especialidad_id, nombre, descripcion, orden)
SELECT 
  id as especialidad_id,
  'Avanzado' as nombre,
  'Amplio dominio y experiencia en la especialidad' as descripcion,
  3 as orden
FROM especialidades_areas
WHERE activa = 1;

INSERT INTO niveles_especialidades (especialidad_id, nombre, descripcion, orden)
SELECT 
  id as especialidad_id,
  'Experto' as nombre,
  'Maestría y reconocimiento en la especialidad' as descripcion,
  4 as orden
FROM especialidades_areas
WHERE activa = 1;

-- Nota: Para migrar los datos del backup, se debe ejecutar manualmente
-- un script que relacione los niveles ENUM antiguos con los nuevos IDs de niveles
-- Ejemplo:
-- UPDATE candidato_especialidades ce
-- INNER JOIN candidato_especialidades_backup ceb ON ce.id = ceb.id
-- INNER JOIN niveles_especialidades ne ON ne.especialidad_id = ce.especialidad_id 
--   AND ne.nombre = ceb.nivel
-- SET ce.nivel_id = ne.id
-- WHERE ceb.nivel IS NOT NULL;
