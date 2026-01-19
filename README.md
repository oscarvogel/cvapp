# CVApp — Sistema de gestión de CVs y generación de documentos

Descripción
---------
CVApp es una pequeña aplicación PHP para administrar candidatos, sus datos personales, experiencias y fotos, y para generar impresiones/PDFs y exportar listados a Excel. Incluye un panel de administración (carpeta `admin/`) con herramientas para exportar, generar PDFs y gestionar usuarios.

Funcionalidades clave
- Administración de candidatos y edición de datos.
- Subida y manejo de fotos (con validaciones y generación de thumbnails en `upload.php`).
- Generación de PDF para candidatos (ej.: `generar_pdf.php`, `admin/generar_pdf.php`).
- Exportación a Excel (`admin/exportar_excel.php`).
- Endpoints públicos para obtener datos: `obtener_localidades.php`, `obtener_especialidades.php`, `obtener_niveles.php`.
- Sistema de niveles y selección configurables (documentación en `SISTEMA_NIVELES_MULTIPLES.md`, `SISTEMA_TIPO_SELECCION.md`).

Requisitos
- PHP 7.4+ (recomendado PHP 8.1/8.3 en producción).
- Extensiones: `pdo`, `pdo_mysql`, `gd` o `imagick` si se realizan transformaciones de imágenes.
- Composer para instalar dependencias en desarrollo/local.

Instalación (desarrollo)
------------------------
1. Clona el repositorio:

```bash
git clone https://github.com/oscarvogel/cvapp.git
cd cvapp
```

2. Instala dependencias con Composer (local):

```bash
composer install
```

3. Copia `.env.example` a `.env` y ajusta valores (BD, `BASE_URL`, rutas de uploads):

```bash
cp .env.example .env
# editar .env con tus valores
```

4. Configura la base de datos y ejecuta las migraciones/SQL si hace falta (ficheros en `migracion_*.sql` o `tablas.sql`).

5. Asegura que `uploads/` y `tmp/` (si existe) tengan permisos de escritura por el servidor web.

Configuración (.env)
--------------------
El proyecto carga configuración desde `.env`. Variables importantes:

- `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`, `DB_CHARSET`
- `BASE_URL` — URL base del sitio
- `UPLOADS_DIR` — ruta de uploads dentro del proyecto
- Sesión: `SESSION_NAME`, `SESSION_LIFETIME`, `SESSION_SAME_SITE`, `SESSION_SECURE`, `SESSION_HTTP_ONLY`
- `TIMEZONE` — zona horaria (p.ej. `America/Argentina/Buenos_Aires`)

Nota: `config.php` usa `vlucas/phpdotenv` si está disponible y tiene un parser manual como fallback, por lo que en hosts compartidos donde `getenv()` pueda no contener variables, aún se leerán los valores del `.env`.

Despliegue en hosting compartido (sin composer disponible)
------------------------------------------------------
Si no tienes acceso a Composer en el servidor, genera `vendor/` en tu máquina local (misma versión de PHP) y súbelo al servidor:

```bash
# en local
rm -rf vendor
composer install --no-dev --optimize-autoloader
zip -r vendor.zip vendor

# subir vendor.zip al servidor y descomprimir
```

Ajusta permisos y pide al proveedor reiniciar PHP-FPM o limpiar OPcache.

Debug y diagnóstico
-------------------
Se incluyen scripts temporales para debugging en `debug_info.php`, `env_raw.php`, `db_test.php`. Úsalos para comprobar:
- que `.env` existe y es legible
- que `vendor/autoload.php` está presente y no provoca errors
- que las extensiones `pdo` y `pdo_mysql` están disponibles

Importante: borra esos archivos en producción cuando termines.

Estructura del proyecto (resumen)
- `admin/` — panel de administración y utilidades (export, login, generar PDF)
- `assets/` — assets públicos (CSS, JS, imágenes)
- `uploads/` — fotos de candidatos y archivos subidos
- `vendor/` — dependencias Composer (no incluido en repo para hosts sin composer)
- `config.php` — carga configuración y prepara `$APP_CONFIG`
- `functions.php` — utilidades comunes
- `generar_pdf.php`, `crear_logo.php`, etc. — scripts utilitarios

Prácticas de seguridad
- Nunca subir `.env` al repositorio. Mantener `.env` fuera del control de versiones.
- Restringir permisos de `uploads/` y validar extensiones/size en `upload.php`.
- Elimina scripts de debug de producción.

Cómo contribuir
- Haz un fork, crea una rama con tu cambio, abre un Pull Request describiendo el cambio.

Soporte y contacto
- Si necesitas que genere `vendor.zip` desde este entorno para descargar, dime y lo genero (puede fallar si el entorno actual no tiene composer o tiene errores de instalación). Si prefieres, puedo darte pasos detallados para tu sistema operativo.

Licencia
- Proyecto sin licencia explícita; añade una si quieres distribuirlo públicamente.

Historia de cambios
- Se añadió la carga desde `.env` y un parser fallback para entornos compartidos.

--
