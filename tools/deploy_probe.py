#!/usr/bin/env python3
r"""
Descarga / subida de archivos del hosting Ferozo de cvapp.

Por que NO usa ftplib de Python:
el ProFTPD de Ferozo anuncia IPv6 y ftplib se queda colgado abriendo el
canal de datos (timeout en PASV, en modo activo y en EPSV). curl con
--disable-epsv (forzando PASV sobre IPv4) baja bien los mismos archivos.

Por que NO pasa la clave por la linea de comandos:
PowerShell y el shell rompen caracteres como " ` $ & | < > ^ % ' \ al
interpolar, y ademas el argumento queda visible en el listado de procesos.
Acá la clave viaja solo dentro de un archivo de configuracion temporal
con permisos de usuario, que se borra al terminar.

Uso:
    python tools/deploy_probe.py                     # baja todo a _deploy_snapshot/
    python tools/deploy_probe.py --list              # solo lista el remoto
    python tools/deploy_probe.py --put <remoto> <local>
"""

import argparse
import hashlib
import os
import shutil
import subprocess
import sys
import tempfile

RAIZ = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
ENV = os.path.join(RAIZ, ".env")
SNAPSHOT = os.path.join(RAIZ, "_deploy_snapshot")

ARCHIVOS = [
    "admin/forgot_password.html",
    "admin/reset_password.php",
    "admin/backend/auth.php",
    "admin/backend/conexion.php",
    "admin/backend/mail.php",
    "admin/backend/api/forgot_password.php",
    "admin/backend/api/reset_password.php",
    "admin/backend/api/diagnostics.php",
    "admin/login.php",
]


def leer_env():
    datos = {}
    if not os.path.isfile(ENV):
        return datos
    with open(ENV, "r", encoding="utf-8-sig", errors="replace") as fh:
        for linea in fh:
            linea = linea.strip()
            if not linea or linea.startswith("#") or "=" not in linea:
                continue
            k, v = linea.split("=", 1)
            datos[k.strip()] = v.strip().strip('"').strip("'")
    return datos


def escapar_config(valor):
    """Escapa un valor para la sintaxis de archivos de configuracion de curl.

    Dentro de comillas dobles, la barra invertida escapa al caracter
    siguiente y la comilla doble cierra la cadena.
    """
    return valor.replace("\\", "\\\\").replace('"', '\\"')


def config_curl(user, password, extra=None):
    """Arma el archivo de configuracion de curl.

    OJO: 'cwd' y 'quote' NO van aca. curl no los acepta como claves en un
    archivo de configuracion y falla con
    "option --config: error encountered when reading a file".
    La raiz remota se antepone a la URL en vez de usar cwd.
    """
    fd, ruta = tempfile.mkstemp(prefix="ferozo_", suffix=".curlrc")
    with os.fdopen(fd, "w", encoding="utf-8") as fh:
        fh.write('user = "%s:%s"\n' % (escapar_config(user), escapar_config(password)))
        fh.write("ssl-reqd\n")
        fh.write("disable-epsv\n")          # clave: sin esto cuelga el data channel
        fh.write("ftp-pasv\n")
        fh.write("no-progress-meter\n")
        for linea in (extra or []):
            fh.write(linea + "\n")
    return ruta


def correr(args, cfg):
    try:
        proc = subprocess.run(
            args + ["--config", cfg],
            capture_output=True, timeout=120,
        )
        return proc.returncode, proc.stdout, proc.stderr
    except subprocess.TimeoutExpired:
        return 124, b"", b"timeout"


def sha256(ruta):
    h = hashlib.sha256()
    with open(ruta, "rb") as fh:
        for bloque in iter(lambda: fh.read(65536), b""):
            h.update(bloque)
    return h.hexdigest()


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--list", action="store_true", help="solo listar el remoto")
    ap.add_argument("--put", nargs=2, metavar=("REMOTO", "LOCAL"),
                    help="subir LOCAL a REMOTO (ej: admin/backend/api/x.php tools/x.php)")
    args = ap.parse_args()

    env = leer_env()
    host = env.get("FEROZO_FTP_HOST", "")
    user = env.get("FEROZO_FTP_USER", "")
    password = env.get("FEROZO_FTP_PASS", "")
    raiz = env.get("FEROZO_FTP_ROOT", "") or "/"

    faltantes = [n for n, v in (("FEROZO_FTP_HOST", host), ("FEROZO_FTP_USER", user),
                                ("FEROZO_FTP_PASS", password)) if not v]
    if faltantes:
        print("Faltan datos en .env: " + ", ".join(faltantes))
        return 1

    cfg = config_curl(user, password)
    try:
        base = f"ftp://{host}{raiz.rstrip('/')}/"

        if args.put:
            remoto, local = args.put
            if not os.path.isfile(local):
                print(f"No existe el archivo local: {local}")
                return 1
            rc, out, err = correr(
                ["curl", "-sS", "--upload-file", local, base + remoto.lstrip("/")], cfg)
            if rc == 0:
                print(f"Subido: {remoto}  ({os.path.getsize(local)} bytes)")
                return 0
            print(f"FALLO al subir {remoto} (rc={rc})")
            print(err.decode("utf-8", "replace").strip())
            return 1

        if args.list:
            rc, out, err = correr(["curl", "-sS", base], cfg)
            print(out.decode("utf-8", "replace") if rc == 0
                  else err.decode("utf-8", "replace"))
            return rc

        print(f"Bajando desde {base}")
        bajados, fallidos = [], []
        for rel in ARCHIVOS:
            local = os.path.join(SNAPSHOT, rel.replace("/", os.sep))
            os.makedirs(os.path.dirname(local), exist_ok=True)
            rc, out, err = correr(
                ["curl", "-sS", "-o", local, "-w", "%{http_code}", base + rel.lstrip("/")], cfg)
            if rc == 0 and out.strip() == b"226":
                bajados.append((rel, os.path.getsize(local)))
            else:
                fallidos.append((rel, (out + err).decode("utf-8", "replace").strip()))

        print("\n  OK:")
        for rel, peso in bajados:
            local = os.path.join(SNAPSHOT, rel.replace("/", os.sep))
            print(f"    {rel:<38} {peso:>7} bytes  sha256:{sha256(local)[:12]}")
        if fallidos:
            print("\n  FALLARON:")
            for rel, err in fallidos:
                print(f"    {rel:<38} {err}")
        return 0 if bajados else 1
    finally:
        try:
            os.remove(cfg)
        except OSError:
            pass


if __name__ == "__main__":
    sys.exit(main())