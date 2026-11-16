#!/usr/bin/env bash
# Restaure la base depuis une sauvegarde produite par backup.sh.
# ATTENTION : remplace le contenu actuel de la base.
#
#   ./deploy/restore.sh /var/backups/cja/db_2026-11-16_031500.sql.gz
set -euo pipefail

cd "$(dirname "${BASH_SOURCE[0]}")/.."

FICHIER="${1:-}"
[[ -n "$FICHIER" && -f "$FICHIER" ]] || { echo "Usage : $0 <sauvegarde.sql.gz>" >&2; exit 1; }
gzip -t "$FICHIER" || { echo "Archive corrompue : $FICHIER" >&2; exit 1; }

COMPOSE=(docker compose -f docker-compose.prod.yml --env-file .env.prod)

echo "La base va etre REMPLACEE par : $FICHIER"
read -r -p "Taper RESTAURER pour continuer : " confirmation
[[ "$confirmation" == "RESTAURER" ]] || { echo "Annule."; exit 1; }

echo "Arret de l'application (la base reste demarree)..."
"${COMPOSE[@]}" stop web php

gunzip -c "$FICHIER" | "${COMPOSE[@]}" exec -T database sh -c 'psql -v ON_ERROR_STOP=1 -U "$POSTGRES_USER" -d "$POSTGRES_DB"'

echo "Redemarrage de l'application..."
"${COMPOSE[@]}" up -d --wait
echo "Restauration terminee."
