#!/usr/bin/env bash
# Deploiement / mise a jour de CJA sur le VPS. A lancer en utilisateur "deploy" :
#
#   ./deploy/deploy.sh
#
# Etapes : git pull, build des images, sauvegarde de la base, cles JWT, migrations,
# redemarrage des services. Les fixtures ne sont JAMAIS chargees (elles purgent la base).
#
# Variables facultatives : SKIP_PULL=1 (ne pas faire git pull), SKIP_BACKUP=1.
set -euo pipefail

cd "$(dirname "${BASH_SOURCE[0]}")/.."

ENV_FILE=".env.prod"
COMPOSE=(docker compose -f docker-compose.prod.yml --env-file "$ENV_FILE")

info() { printf '\n\033[1;34m==> %s\033[0m\n' "$*"; }
fail() { printf '\033[1;31mErreur : %s\033[0m\n' "$*" >&2; exit 1; }

[[ -f "$ENV_FILE" ]] || fail "$ENV_FILE introuvable. Copier .env.prod.example vers $ENV_FILE et le remplir."

if [[ "$(stat -c %a "$ENV_FILE")" != "600" ]]; then
    echo "Permissions de $ENV_FILE corrigees en 600."
    chmod 600 "$ENV_FILE"
fi

if grep -q 'remplacer-par' "$ENV_FILE"; then
    fail "$ENV_FILE contient encore des valeurs d'exemple (remplacer-par...). Generer de vrais secrets."
fi

if [[ "${SKIP_PULL:-0}" != "1" ]]; then
    info "Mise a jour du code"
    git pull --ff-only
fi

info "Construction des images"
"${COMPOSE[@]}" build --pull

# Filet de securite : sauvegarde avant de toucher au schema (sauf premier deploiement).
services_actifs="$("${COMPOSE[@]}" ps --status running --services 2>/dev/null || true)"
if [[ "${SKIP_BACKUP:-0}" != "1" ]] && grep -qx database <<<"$services_actifs"; then
    info "Sauvegarde avant migration"
    ./deploy/backup.sh
fi

info "Demarrage de la base et de Gotenberg"
"${COMPOSE[@]}" up -d --wait database gotenberg

info "Cles JWT (creees une seule fois, conservees dans un volume)"
"${COMPOSE[@]}" run --rm php php bin/console lexik:jwt:generate-keypair --skip-if-exists

info "Migrations de la base"
"${COMPOSE[@]}" run --rm php php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

info "Demarrage de l'application"
"${COMPOSE[@]}" up -d --remove-orphans --wait

info "Nettoyage des anciennes images"
docker image prune -f >/dev/null

info "Etat des services"
"${COMPOSE[@]}" ps

echo
echo "Deploiement termine."
