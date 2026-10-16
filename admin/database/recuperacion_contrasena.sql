-- ============================================================================
--  Recuperación de contraseña - Panel de CVs
--  Migración aplicada el 2026-10-16 sobre c2650268_cv.
--
--  La tabla usuarios_admin no tenía columna de email, así que la recuperación
--  por correo era imposible aunque el endpoint anduviera: no había forma de
--  asociar un correo con una cuenta. Tampoco existían las columnas donde
--  dejar el token de un solo uso.
--
--  Idempotente: se puede correr las veces que haga falta.
-- ============================================================================

-- columnas de recuperacion ---------------------------------------------------
SET @db := DATABASE();

SELECT COUNT(*) INTO @existe_email
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'usuarios_admin' AND COLUMN_NAME = 'email';

SET @sql := IF(@existe_email = 0,
  'ALTER TABLE `usuarios_admin` ADD COLUMN `email` VARCHAR(150) NULL',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @existe_token
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'usuarios_admin' AND COLUMN_NAME = 'token_recuperacion';

SET @sql := IF(@existe_token = 0,
  'ALTER TABLE `usuarios_admin` ADD COLUMN `token_recuperacion` VARCHAR(100) NULL',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @existe_expira
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'usuarios_admin' AND COLUMN_NAME = 'token_expira';

SET @sql := IF(@existe_expira = 0,
  'ALTER TABLE `usuarios_admin` ADD COLUMN `token_expira` DATETIME NULL',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- indice unico: dos cuentas no pueden tener el mismo correo ------------------
SET @sql := IF(@existe_email = 0,
  'ALTER TABLE `usuarios_admin` ADD UNIQUE KEY `uq_usuarios_admin_email` (`email`)',
  'DO 0');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- alta del correo del administrador ------------------------------------------
-- Los demas usuarios quedan con email NULL: hasta que se lo carguen no pueden
-- recuperar clave, porque no hay forma de encontrarlos. Se los agrega con:
--   UPDATE usuarios_admin SET email = 'nombre@dominio.com' WHERE usuario = 'flor';

UPDATE `usuarios_admin`
SET `email` = 'oscar@forestalgaruhape.com.ar'
WHERE `usuario` = 'admin'
  AND (`email` IS NULL OR `email` = '');

-- ============================================================================
--  VARIABLES QUE FALTA CONFIGURAR EN EL .env DEL SERVIDOR
--  (sin esto el correo se genera pero no sale: mail.php no encuentra SMTP_HOST)
--
--  SMTP_HOST=c2650268.ferozo.com
--  SMTP_PORT=465
--  SMTP_USER=sistemas@servinlgsm.com.ar
--  SMTP_PASS=<clave>
--  SMTP_SECURE=ssl
--  MAIL_FROM=sistemas@servinlgsm.com.ar
--  MAIL_FROM_NAME=Panel de CVs
--  APP_ENV=production
--
--  PENDIENTE: el dominio firma con SPF "-all" que no autoriza al servidor de
--  correo, y PHPMailer no firma DKIM. La entrega funciona (el SMTP acepta y
--  encola) pero los mensajes no llegan a la bandeja. Hay que agregar la clave
--  privada del selector "mail" a mail.php y ampliar el SPF.
-- ============================================================================