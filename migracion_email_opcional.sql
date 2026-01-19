-- Migración: Hacer el email opcional en la tabla candidatos
-- El campo email ahora puede ser NULL y no es único
-- La validación de duplicados se hará únicamente por DNI

-- 1. Eliminar el índice de email si existe
DROP INDEX IF EXISTS idx_email ON candidatos;

-- 2. Modificar la columna email para permitir NULL
ALTER TABLE candidatos 
MODIFY COLUMN email VARCHAR(150) NULL;

-- 3. Crear índice único en DNI para garantizar que no haya duplicados
-- Primero verificamos si existe y lo eliminamos
DROP INDEX IF EXISTS idx_dni ON candidatos;

-- Ahora creamos el índice único
CREATE UNIQUE INDEX idx_dni ON candidatos(dni);

-- Nota: El campo DNI ya tiene un índice regular creado anteriormente,
-- ahora lo convertimos en UNIQUE para forzar la unicidad a nivel de base de datos
