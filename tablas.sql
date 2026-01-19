CREATE TABLE IF NOT EXISTS estados_cv (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(50) NOT NULL UNIQUE,
  color VARCHAR(7) NOT NULL DEFAULT '#6b7280', -- Color hex para UI
  descripcion VARCHAR(200) NULL,
  activo BOOLEAN NOT NULL DEFAULT TRUE,
  orden TINYINT UNSIGNED NOT NULL DEFAULT 0,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_activo (activo),
  INDEX idx_orden (orden)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS candidatos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL,
  telefono VARCHAR(30) NOT NULL,
  area_profesional VARCHAR(100) NOT NULL,
  experiencia TINYINT UNSIGNED NOT NULL,
  cv_nombre_original VARCHAR(255) NOT NULL,
  cv_ruta VARCHAR(255) NOT NULL, -- nombre del archivo guardado en /uploads
  foto_nombre_original VARCHAR(255) NOT NULL,
  foto_ruta VARCHAR(255) NOT NULL, -- nombre de la foto guardada en /uploads
  estado_id INT DEFAULT 1, -- FK a estados_cv
  fecha_carga DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_estado DATETIME DEFAULT CURRENT_TIMESTAMP,
  observaciones TEXT NULL,
  INDEX idx_area (area_profesional),
  INDEX idx_exp (experiencia),
  INDEX idx_fecha (fecha_carga),
  INDEX idx_email (email),
  INDEX idx_estado (estado_id),
  FOREIGN KEY (estado_id) REFERENCES estados_cv(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS areas_profesionales (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL UNIQUE,
  activa BOOLEAN NOT NULL DEFAULT TRUE,
  orden TINYINT UNSIGNED NOT NULL DEFAULT 0,
  creada_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_activa (activa),
  INDEX idx_orden (orden)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS usuarios_admin (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario VARCHAR(50) NOT NULL UNIQUE,
  contrasena_hash VARCHAR(255) NOT NULL,
  nombre_completo VARCHAR(100) NOT NULL DEFAULT '',
  is_admin BOOLEAN NOT NULL DEFAULT FALSE,
  activo BOOLEAN NOT NULL DEFAULT TRUE,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ultimo_acceso DATETIME NULL,
  creado_por INT NULL,
  INDEX idx_usuario (usuario),
  INDEX idx_admin (is_admin),
  INDEX idx_activo (activo),
  FOREIGN KEY (creado_por) REFERENCES usuarios_admin(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabla de relación many-to-many entre candidatos y áreas profesionales
-- Permite que un candidato tenga hasta 2 áreas profesionales
CREATE TABLE IF NOT EXISTS candidato_areas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  candidato_id INT NOT NULL,
  area_profesional_id INT NOT NULL,
  creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_candidato (candidato_id),
  INDEX idx_area (area_profesional_id),
  UNIQUE KEY unique_candidato_area (candidato_id, area_profesional_id),
  FOREIGN KEY (candidato_id) REFERENCES candidatos(id) ON DELETE CASCADE,
  FOREIGN KEY (area_profesional_id) REFERENCES areas_profesionales(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insertar estados de CV por defecto
INSERT IGNORE INTO estados_cv (id, nombre, color, descripcion, orden) VALUES
(1, 'Pendiente', '#f59e0b', 'CV recibido, pendiente de revisión', 1),
(2, 'En Revisión', '#3b82f6', 'CV siendo evaluado por el equipo', 2),
(3, 'Validado', '#10b981', 'CV aprobado para siguiente etapa', 3),
(4, 'Entrevista', '#8b5cf6', 'Candidato citado para entrevista', 4),
(5, 'Contratado', '#059669', 'Candidato seleccionado y contratado', 5),
(6, 'Rechazado', '#ef4444', 'CV no cumple con los requisitos', 6),
(7, 'Archivado', '#6b7280', 'CV archivado para futuras oportunidades', 7);

-- Insertar áreas profesionales por defecto
INSERT IGNORE INTO areas_profesionales (nombre, orden) VALUES
('Desarrollo', 1),
('Diseño', 2),
('Marketing', 3),
('Ventas', 4),
('Recursos Humanos', 5),
('Finanzas', 6),
('Operaciones', 7),
('Soporte TI', 8),
('Administración', 9),
('Dirección', 10);

-- Actualizar candidatos existentes sin estado para asignarles el estado "Pendiente" por defecto
UPDATE candidatos SET estado_id = 1 WHERE estado_id IS NULL;

-- Actualizar usuarios existentes para ser administradores por defecto
ALTER TABLE usuarios_admin 
ADD COLUMN IF NOT EXISTS nombre_completo VARCHAR(100) NOT NULL DEFAULT '',
ADD COLUMN IF NOT EXISTS is_admin BOOLEAN NOT NULL DEFAULT FALSE,
ADD COLUMN IF NOT EXISTS activo BOOLEAN NOT NULL DEFAULT TRUE,
ADD COLUMN IF NOT EXISTS ultimo_acceso DATETIME NULL,
ADD COLUMN IF NOT EXISTS creado_por INT NULL,
ADD INDEX IF NOT EXISTS idx_usuario (usuario),
ADD INDEX IF NOT EXISTS idx_admin (is_admin),
ADD INDEX IF NOT EXISTS idx_activo (activo);

-- Hacer que los usuarios existentes sean administradores
UPDATE usuarios_admin SET is_admin = TRUE WHERE is_admin IS NULL OR is_admin = FALSE;

-- Crear usuario administrador por defecto si no existe
INSERT IGNORE INTO usuarios_admin (usuario, contrasena_hash, nombre_completo, is_admin, activo) 
VALUES ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrador Principal', TRUE, TRUE);

-- MIGRACIÓN: Mover datos existentes de area_profesional a la nueva tabla candidato_areas
-- Solo ejecutar si existen candidatos con area_profesional y la columna aún existe
INSERT IGNORE INTO candidato_areas (candidato_id, area_profesional_id)
SELECT 
    c.id as candidato_id,
    ap.id as area_profesional_id
FROM candidatos c
INNER JOIN areas_profesionales ap ON c.area_profesional = ap.nombre
WHERE c.area_profesional IS NOT NULL 
  AND c.area_profesional != ''
  AND ap.activa = 1;

-- NOTA: Una vez confirmada la migración exitosa, se puede eliminar la columna area_profesional 
-- de la tabla candidatos ejecutando:
-- ALTER TABLE candidatos DROP COLUMN area_profesional;
-- Por ahora se deja como comentario para mantener los datos originales hasta confirmar

-- Trigger para limitar a máximo 2 áreas profesionales por candidato
DELIMITER $$
CREATE TRIGGER IF NOT EXISTS check_max_areas_per_candidate
BEFORE INSERT ON candidato_areas
FOR EACH ROW
BEGIN
    DECLARE area_count INT;
    
    SELECT COUNT(*) INTO area_count 
    FROM candidato_areas 
    WHERE candidato_id = NEW.candidato_id;
    
    IF area_count >= 2 THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'Un candidato no puede tener más de 2 áreas profesionales';
    END IF;
END$$
DELIMITER ;


-- Añadir nuevas columnas a la tabla candidatos
-- para almacenar información demográfica y personal adicional
ALTER TABLE candidatos
ADD COLUMN dni VARCHAR(20) NOT NULL,
ADD COLUMN edad TINYINT UNSIGNED NOT NULL,
ADD COLUMN estado_civil ENUM('Soltero', 'Casado', 'Divorciado', 'Viudo', 'Unión libre') NOT NULL,
ADD COLUMN hijos TINYINT UNSIGNED NOT NULL DEFAULT 0,
ADD COLUMN edad_hijos TEXT NULL,
ADD COLUMN nacionalidad VARCHAR(255) NOT NULL,
ADD COLUMN lugar_residencia VARCHAR(255) NOT NULL,
ADD COLUMN ocupacion_actual VARCHAR(100) NOT NULL,
ADD COLUMN ocupacion_padre VARCHAR(100) NOT NULL,
ADD COLUMN ocupacion_madre VARCHAR(100) NOT NULL,
ADD COLUMN fecha_actualizacion DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- Agregar columna para pretensión salarial


ALTER TABLE candidatos
ADD COLUMN pretension_salarial DECIMAL(10, 2) NULL;

-- Añadir índices opcionales para mejor rendimiento (opcional)
CREATE INDEX idx_dni ON candidatos(dni);
CREATE INDEX idx_edad ON candidatos(edad);
CREATE INDEX idx_estado_civil ON candidatos(estado_civil);
CREATE INDEX idx_lugar_residencia ON candidatos(lugar_residencia);


-- Nueva tabla para formación profesional de los candidatos
CREATE TABLE IF NOT EXISTS formacion_profesional (
  id INT AUTO_INCREMENT PRIMARY KEY,
  candidato_id INT NOT NULL,
  nivel_educativo ENUM('Primaria', 'Secundaria', 'Universitaria') NOT NULL,
  carreras_titulos TEXT NULL,
  cursos_capacitaciones TEXT NULL,
  fecha_registro DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (candidato_id) REFERENCES candidatos(id) ON DELETE CASCADE,
  INDEX idx_candidato (candidato_id),
  INDEX idx_nivel (nivel_educativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- Nueva tabla para experiencia laboral de los candidatos
CREATE TABLE IF NOT EXISTS experiencia_laboral (
  id INT AUTO_INCREMENT PRIMARY KEY,
  candidato_id INT NOT NULL,
  empresa VARCHAR(255) NOT NULL,
  puesto VARCHAR(100) NOT NULL,
  fecha_desde DATE NOT NULL,
  fecha_hasta DATE NULL, -- NULL = actual
  tareas TEXT NOT NULL,
  empleador VARCHAR(255) NOT NULL, -- nombre del empleador o contacto
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (candidato_id) REFERENCES candidatos(id) ON DELETE CASCADE,
  INDEX idx_candidato (candidato_id),
  INDEX idx_fecha_desde (fecha_desde),
  INDEX idx_fecha_hasta (fecha_hasta)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Nueva tabla para habilidades y disponibilidad de los candidatos
CREATE TABLE IF NOT EXISTS habilidades_disponibilidad (
  id INT AUTO_INCREMENT PRIMARY KEY,
  candidato_id INT NOT NULL UNIQUE,
  antecedentes_penales ENUM('Si', 'No') NOT NULL,
  certificado_ruta VARCHAR(255) NULL, -- ruta del archivo subido (ej: /uploads/antecedentes.pdf)
  certificado_nombre_original VARCHAR(255) NULL, -- nombre original del archivo
  licencia_conducir VARCHAR(100) NULL, -- ej: "B,E,Profesional"
  otras_licencias TEXT NULL,
  disponibilidad ENUM('Inmediata', '15 días', '30 días') NOT NULL,
  fecha_registro DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (candidato_id) REFERENCES candidatos(id) ON DELETE CASCADE,
  INDEX idx_candidato (candidato_id),
  INDEX idx_licencia (licencia_conducir)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- Nueva tabla para especialidades de áreas profesionales
CREATE TABLE IF NOT EXISTS especialidades_areas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  area_profesional_id INT NOT NULL,
  nombre VARCHAR(100) NOT NULL,
  activa BOOLEAN NOT NULL DEFAULT TRUE,
  orden TINYINT UNSIGNED NOT NULL DEFAULT 0,
  creada_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (area_profesional_id) REFERENCES areas_profesionales(id) ON DELETE CASCADE ON UPDATE CASCADE,
  INDEX idx_area (area_profesional_id),
  INDEX idx_activa (activa),
  INDEX idx_orden (orden),
  UNIQUE KEY unique_area_especialidad (area_profesional_id, nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabla para relacionar especialidades con candidatos
CREATE TABLE IF NOT EXISTS candidato_especialidades (
  id INT AUTO_INCREMENT PRIMARY KEY,
  candidato_id INT NOT NULL,
  especialidad_id INT NOT NULL,
  nivel ENUM('Básico', 'Intermedio', 'Avanzado', 'Experto') NULL,
  fecha_seleccion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (candidato_id) REFERENCES candidatos(id) ON DELETE CASCADE ON UPDATE CASCADE,
  FOREIGN KEY (especialidad_id) REFERENCES especialidades_areas(id) ON DELETE RESTRICT ON UPDATE CASCADE,
  UNIQUE KEY unique_candidato_especialidad (candidato_id, especialidad_id),
  INDEX idx_candidato (candidato_id),
  INDEX idx_especialidad (especialidad_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;