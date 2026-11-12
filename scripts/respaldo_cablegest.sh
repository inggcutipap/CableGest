#!/usr/bin/env bash
set -euo pipefail

# Script de respaldo diario de MariaDB para CableGest (RNF09).
# Lee la configuracion de conexion desde el .env del proyecto sin exponer credenciales en logs.

APP_DIR="${APP_DIR:-/var/www/html/CableGest}"
BACKUP_DIR="${BACKUP_DIR:-/var/backups/cablegest}"
RETENCION_DIAS="${RETENCION_DIAS:-14}"
DESTINO_REMOTO="${DESTINO_REMOTO:-}"

ENV_FILE="${APP_DIR}/.env"
if [[ ! -f "${ENV_FILE}" ]]; then
  echo "Error: no se encontro el archivo .env en ${ENV_FILE}" >&2
  exit 1
fi

leer_env() {
  local clave="$1"
  grep -E "^${clave}=" "${ENV_FILE}" | tail -n 1 | cut -d '=' -f 2- | sed -e 's/^"//' -e 's/"$//' -e "s/^'//" -e "s/'$//"
}

DB_HOST="$(leer_env DB_HOST)"
DB_PORT="$(leer_env DB_PORT)"
DB_DATABASE="$(leer_env DB_DATABASE)"
DB_USERNAME="$(leer_env DB_USERNAME)"
DB_PASSWORD="$(leer_env DB_PASSWORD)"

DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"

mkdir -p "${BACKUP_DIR}"
FECHA="$(date +%Y-%m-%d_%H%M%S)"
ARCHIVO="${BACKUP_DIR}/cablegest_${FECHA}.sql.gz"

umask 077
MYSQL_PWD="${DB_PASSWORD}" mysqldump \
  --host="${DB_HOST}" \
  --port="${DB_PORT}" \
  --user="${DB_USERNAME}" \
  --single-transaction \
  --routines \
  --triggers \
  "${DB_DATABASE}" | gzip -9 > "${ARCHIVO}"

echo "[$(date -Iseconds)] Respaldo local creado: ${ARCHIVO}"

if [[ -n "${DESTINO_REMOTO}" ]]; then
  rsync -az --timeout=60 "${ARCHIVO}" "${DESTINO_REMOTO}"
  echo "[$(date -Iseconds)] Respaldo transferido fuera del servidor a ${DESTINO_REMOTO}"
fi

find "${BACKUP_DIR}" -type f -name 'cablegest_*.sql.gz' -mtime +"${RETENCION_DIAS}" -delete
echo "[$(date -Iseconds)] Limpieza completada (retencion: ${RETENCION_DIAS} dias)."
