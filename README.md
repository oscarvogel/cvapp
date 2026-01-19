# CVApp — Despliegue y notas rápidas

Resumen breve:

- Este proyecto ahora carga configuración desde un archivo `.env` (se usa `vlucas/phpdotenv` cuando está disponible y hay un parser manual como fallback).
- No se debe subir el archivo `.env` al repositorio; hay un `.env.example` con las variables necesarias.

Instalar dependencias localmente y subir `vendor/` al hosting compartido

1. En tu máquina local (usa PHP similar al del servidor, p.ej. PHP 8.3):

```bash
cd /ruta/al/proyecto
rm -rf vendor
composer install --no-dev --optimize-autoloader
```

2. Empaquetar `vendor/` y subirlo al servidor (FTP/Panel o SCP):

```bash
# Zip en Linux/macOS
zip -r vendor.zip vendor
# o en PowerShell
Compress-Archive -Path vendor -DestinationPath vendor.zip
```

3. En el servidor: subir y descomprimir (si tienes SSH)

```bash
cd /home/usuario/public_html/cvapp
rm -rf vendor
unzip vendor.zip
find vendor -type d -exec chmod 755 {} \;
find vendor -type f -exec chmod 644 {} \;
```

4. Reiniciar PHP-FPM / limpiar OPcache

- Si no tienes acceso, solicita al proveedor un reinicio del pool PHP o limpieza de OPcache para que los cambios surtan efecto.

Validaciones

- Abrir `debug_info.php` (temporal) y comprobar que ya no aparece el error de require en `vendor/...`.
- Ejecutar `db_test.php` o acceder a la app para verificar la conexión a BD.

Seguridad y limpieza post-deploy

- Elimina los archivos de diagnóstico (`debug_info.php`, `env_raw.php`, `db_test.php`) del servidor una vez resuelto el problema.
- Confirma que `.env` no está en el repositorio y que `.gitignore` lo excluye.

Notas específicas

- Si tu proveedor limpia variables de entorno o `getenv()` devuelve vacío en FPM, `config.php` ya implementa un parser manual de `.env` y prioriza esos valores internamente.
- Asegúrate de subir el `vendor/` que corresponde a la versión de PHP del servidor (extensiones y polyfills).

Contacto

Si quieres, puedo generar `vendor.zip` en este entorno y darte el archivo listo para descargar — dime si lo intento.