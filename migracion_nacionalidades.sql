-- Migración: Permitir múltiples nacionalidades (hasta 2)
-- La columna 'nacionalidad' en la tabla 'candidatos' ahora almacena
-- múltiples nacionalidades separadas por " / "
-- Ejemplo: "ARGENTINA / PERUANA"

-- Agregar comentario a la columna para documentar el cambio
ALTER TABLE candidatos 
MODIFY COLUMN nacionalidad VARCHAR(255) NOT NULL 
COMMENT 'Nacionalidades del candidato, separadas por " / " (máximo 2)';

-- Verificar que la columna existe y tiene el tipo correcto
SELECT 
    COLUMN_NAME,
    DATA_TYPE,
    CHARACTER_MAXIMUM_LENGTH,
    COLUMN_COMMENT
FROM 
    INFORMATION_SCHEMA.COLUMNS
WHERE 
    TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'candidatos'
    AND COLUMN_NAME = 'nacionalidad';
