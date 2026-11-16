#!/usr/bin/env bash
# Sauvegarde de la base PostgreSQL et des fichiers importes.
# Lance chaque nuit par le minuteur systemd cja-backup.timer, ou a la main :
#
#   ./deploy/backup.sh
#
# Variables facultatives : BACKUP_DIR (defaut /var/backups/cja), KEEP_DAYS (defaut 14).
#
# IMPORTANT : ces sauvegardes restent sur le VPS. Copiez-les regulierement ailleurs
# (rclone, rsync vers une autre machine...) : si le VPS disparait, elles disparaissent avec lui.
set -euo pipefail

cd "$(dirname "${BASH_SOURCE[0]}")/.."

BACKUP_DIR="${BACKUP_DIR:-/var/backups/cja}"
KEEP_DAYS="${KEEP_DAYS:-14}"
COMPOSE=(docker compose -f docker-compose.prod.yml --env-file .env.prod)

umask 077
mkdir -p "$BACKUP_DIR"
horodatage="$(date +%F_%H%M%S)"
base_sql="$BACKUP_DIR/db_${horodatage}.sql.gz"
archive_imports="$BACKUP_DIR/imports_${horodatage}.tar.gz"

# pg_dump s'execute dans le conteneur ; les identifiants viennent de son environnement.
"${COMPOSE[@]}" exec -T database sh -c 'pg_dump -U "$POSTGRES_USER" -d "$POSTGRES_DB" --no-owner --clean --if-exists' \
    | gzip > "$base_sql"

# Un dump vide ou tronque ne doit jamais passer pour une sauvegarde valide.
if [[ ! -s "$base_sql" ]] || ! gzip -t "$base_sql" || ! gunzip -c "$base_sql" | tail -n 5 | grep -q 'PostgreSQL database dump complete'; then
    rm -f "$base_sql"
    echo "Erreur : la sauvegarde de la base est invalide, elle a ete supprimee." >&2
    exit 1
fi

"${COMPOSE[@]}" exec -T php tar -C /app/var -cz imports > "$archive_imports"

find "$BACKUP_DIR" -type f \( -name 'db_*.sql.gz' -o -name 'imports_*.tar.gz' \) -mtime +"$KEEP_DAYS" -delete

echo "Sauvegarde ecrite : $base_sql ($(du -h "$base_sql" | cut -f1))"
