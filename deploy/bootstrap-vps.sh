#!/usr/bin/env bash
# Preparation d'un VPS Debian 13 (trixie) vierge pour heberger CJA.
# A lancer UNE SEULE FOIS, en root, depuis la console du fournisseur ou en SSH :
#
#   DEPLOY_SSH_PUBKEY="ssh-ed25519 AAAA... lucas@pc" bash bootstrap-vps.sh
#
# Ce que fait le script : mises a jour de securite automatiques, utilisateur "deploy",
# SSH par cle uniquement (root interdit), pare-feu ufw (22, 80, 443), fail2ban,
# Docker Engine officiel, swap si peu de RAM, sauvegarde quotidienne de la base.
#
# Le script est relancable sans danger.
set -euo pipefail

DEPLOY_USER="${DEPLOY_USER:-deploy}"
REPO_DIR="${REPO_DIR:-/home/${DEPLOY_USER}/cja}"
BACKUP_DIR="${BACKUP_DIR:-/var/backups/cja}"

info() { printf '\n\033[1;34m==> %s\033[0m\n' "$*"; }
fail() { printf '\033[1;31mErreur : %s\033[0m\n' "$*" >&2; exit 1; }

[[ $EUID -eq 0 ]] || fail "a lancer en root."
[[ -r /etc/os-release ]] && . /etc/os-release
[[ "${ID:-}" == "debian" && "${VERSION_ID:-}" == "13" ]] || fail "ce script cible Debian 13 (detecte : ${PRETTY_NAME:-inconnu})."
[[ -n "${DEPLOY_SSH_PUBKEY:-}" ]] || fail 'definir DEPLOY_SSH_PUBKEY avec votre cle PUBLIQUE (contenu de id_ed25519.pub).'

# Validation de la cle avant toute modification : on ne coupe l'acces par mot de passe
# que si la cle est exploitable, pour ne pas se retrouver enferme dehors.
cle_tmp="$(mktemp)"
printf '%s\n' "$DEPLOY_SSH_PUBKEY" > "$cle_tmp"
ssh-keygen -l -f "$cle_tmp" >/dev/null 2>&1 || { rm -f "$cle_tmp"; fail "DEPLOY_SSH_PUBKEY n'est pas une cle publique SSH valide."; }
rm -f "$cle_tmp"

export DEBIAN_FRONTEND=noninteractive

info "Mise a jour du systeme et paquets de base"
apt-get update
apt-get -y full-upgrade
apt-get -y install ca-certificates curl gnupg sudo git openssl ufw fail2ban nftables \
    python3-systemd unattended-upgrades apt-listchanges

info "Mises a jour de securite automatiques"
cat > /etc/apt/apt.conf.d/20auto-upgrades <<'EOF'
APT::Periodic::Update-Package-Lists "1";
APT::Periodic::Unattended-Upgrade "1";
APT::Periodic::AutocleanInterval "7";
EOF
cat > /etc/apt/apt.conf.d/52cja-unattended <<'EOF'
// Redemarre la nuit uniquement si une mise a jour l'exige (noyau). Les conteneurs repartent seuls.
Unattended-Upgrade::Automatic-Reboot "true";
Unattended-Upgrade::Automatic-Reboot-Time "04:30";
Unattended-Upgrade::Remove-Unused-Dependencies "true";
EOF
systemctl enable --now unattended-upgrades

info "Utilisateur ${DEPLOY_USER}"
if ! id "$DEPLOY_USER" >/dev/null 2>&1; then
    adduser --disabled-password --gecos "" "$DEPLOY_USER"
    echo "Choisissez un mot de passe pour ${DEPLOY_USER} (demande par sudo ; la connexion SSH se fait par cle)."
    passwd "$DEPLOY_USER"
fi
usermod -aG sudo "$DEPLOY_USER"

install -d -m 700 -o "$DEPLOY_USER" -g "$DEPLOY_USER" "/home/${DEPLOY_USER}/.ssh"
touch "/home/${DEPLOY_USER}/.ssh/authorized_keys"
if ! grep -qxF "$DEPLOY_SSH_PUBKEY" "/home/${DEPLOY_USER}/.ssh/authorized_keys"; then
    printf '%s\n' "$DEPLOY_SSH_PUBKEY" >> "/home/${DEPLOY_USER}/.ssh/authorized_keys"
fi
chown "$DEPLOY_USER:$DEPLOY_USER" "/home/${DEPLOY_USER}/.ssh/authorized_keys"
chmod 600 "/home/${DEPLOY_USER}/.ssh/authorized_keys"

info "Durcissement SSH"
# Le prefixe 00- garantit la priorite sur les fichiers du fournisseur (ex. 50-cloud-init.conf).
cat > /etc/ssh/sshd_config.d/00-cja-hardening.conf <<EOF
PermitRootLogin no
PasswordAuthentication no
KbdInteractiveAuthentication no
PubkeyAuthentication yes
AuthenticationMethods publickey
AllowUsers ${DEPLOY_USER}
MaxAuthTries 3
LoginGraceTime 30
X11Forwarding no
AllowAgentForwarding no
EOF
if sshd -t; then
    systemctl reload ssh
else
    rm -f /etc/ssh/sshd_config.d/00-cja-hardening.conf
    fail "configuration SSH invalide, durcissement annule."
fi

info "Pare-feu (ufw)"
ufw default deny incoming
ufw default allow outgoing
ufw limit 22/tcp comment 'SSH'
ufw allow 80/tcp comment 'HTTP (redirection HTTPS)'
ufw allow 443/tcp comment 'HTTPS'
ufw allow 443/udp comment 'HTTP/3'
ufw --force enable

info "fail2ban (SSH)"
cat > /etc/fail2ban/jail.d/cja-sshd.local <<'EOF'
[DEFAULT]
banaction = nftables
banaction_allports = nftables[type=allports]

[sshd]
enabled = true
backend = systemd
maxretry = 5
findtime = 10m
bantime = 1h
EOF
systemctl enable fail2ban
systemctl restart fail2ban

info "Docker Engine (depot officiel)"
if ! command -v docker >/dev/null 2>&1; then
    install -m 0755 -d /etc/apt/keyrings
    curl -fsSL https://download.docker.com/linux/debian/gpg -o /etc/apt/keyrings/docker.asc
    chmod a+r /etc/apt/keyrings/docker.asc
    echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.asc] https://download.docker.com/linux/debian ${VERSION_CODENAME} stable" \
        > /etc/apt/sources.list.d/docker.list
    apt-get update
    apt-get -y install docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
fi
install -d /etc/docker
cat > /etc/docker/daemon.json <<'EOF'
{
  "log-driver": "json-file",
  "log-opts": { "max-size": "10m", "max-file": "5" },
  "live-restore": true,
  "no-new-privileges": true
}
EOF
systemctl enable docker
systemctl restart docker
# Appartenir au groupe docker equivaut a un acces root : seul l'utilisateur de deploiement en fait partie.
usermod -aG docker "$DEPLOY_USER"

info "Swap (si moins de 2 Go de RAM et pas de swap)"
ram_mo="$(awk '/MemTotal/ {print int($2/1024)}' /proc/meminfo)"
if [[ "$ram_mo" -lt 2048 ]] && ! swapon --show | grep -q .; then
    fallocate -l 2G /swapfile
    chmod 600 /swapfile
    mkswap /swapfile
    swapon /swapfile
    grep -q '^/swapfile' /etc/fstab || echo '/swapfile none swap sw 0 0' >> /etc/fstab
    echo 'vm.swappiness=10' > /etc/sysctl.d/99-cja-swap.conf
    sysctl -p /etc/sysctl.d/99-cja-swap.conf
fi

info "Sauvegarde quotidienne (minuteur systemd)"
install -d -m 700 -o "$DEPLOY_USER" -g "$DEPLOY_USER" "$BACKUP_DIR"
cat > /etc/systemd/system/cja-backup.service <<EOF
[Unit]
Description=Sauvegarde de la base CJA
After=docker.service

[Service]
Type=oneshot
User=${DEPLOY_USER}
WorkingDirectory=${REPO_DIR}
Environment=BACKUP_DIR=${BACKUP_DIR}
ExecStart=${REPO_DIR}/deploy/backup.sh
EOF
cat > /etc/systemd/system/cja-backup.timer <<'EOF'
[Unit]
Description=Sauvegarde quotidienne de la base CJA

[Timer]
OnCalendar=*-*-* 03:15:00
RandomizedDelaySec=10min
Persistent=true

[Install]
WantedBy=timers.target
EOF
systemctl daemon-reload
systemctl enable --now cja-backup.timer

info "Termine"
cat <<EOF

Prochaines etapes :
  1. NE FERMEZ PAS cette session. Dans un AUTRE terminal, verifiez que la connexion fonctionne :
       ssh ${DEPLOY_USER}@<IP-du-VPS>
  2. Une fois connecte en ${DEPLOY_USER}, generez une cle de deploiement GitHub (lecture seule) :
       ssh-keygen -t ed25519 -f ~/.ssh/cja_deploy -N "" -C "cja-vps"
       cat ~/.ssh/cja_deploy.pub     # a ajouter dans GitHub : depot > Settings > Deploy keys
  3. Clonez le depot dans ${REPO_DIR}, creez .env.prod, puis lancez deploy/deploy.sh
     (voir la section "Deploiement en production" du README).

Un redemarrage du serveur est conseille pour appliquer les mises a jour du noyau : reboot
EOF
