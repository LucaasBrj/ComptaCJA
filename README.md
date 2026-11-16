# CJA — Devis, facturation et suivi de chantier

Application de gestion pour artisan du bâtiment : base clientèle, chantiers, fournisseurs,
devis et factures. Ce dépôt contient le **socle technique**, le **Lot 1 (base clientèle)**, le **Lot 2 (devis, prestations, PDF)**, le **Lot 3 (acomptes, solde, débours)**, le **Lot 4 (recherche, duplication, suivi)** et le **Lot 5 (envoi par e-mail)**.

- `api/` — Symfony 7.4 LTS + API Platform 4.4 (PHP 8.5)
- `frontend/` — Angular 22 + Angular Material
- `docker-compose.yml` — PostgreSQL 17, Gotenberg 8 (PDF), Mailpit (e-mails locaux), Adminer

## Prérequis

PHP 8.4+, Composer, Node 20+, et un runtime Docker. Sur macOS sans Docker Desktop :

```bash
brew install composer symfony node colima docker docker-compose
colima start
```

## Démarrage sous Windows (Docker Desktop)

Seul Docker Desktop (avec WSL2) est nécessaire : PHP, Composer et Node tournent dans des
conteneurs, via le profil Compose `app`.

```powershell
winget install Docker.DockerDesktop        # puis lancer Docker Desktop
docker compose --profile app up -d --build
docker compose exec api php bin/console doctrine:fixtures:load --no-interaction
```

Le premier démarrage installe les dépendances (`composer install`, `npm ci`), génère les clés
JWT et applique les migrations : suivre l'avancement avec `docker compose logs -f api frontend`.
L'application est ensuite sur http://localhost:4200 (API sur http://127.0.0.1:8000/api).

```powershell
docker compose exec api php bin/console doctrine:database:create --env=test --if-not-exists   # une seule fois
docker compose exec api php bin/phpunit                                   # tests
docker compose exec api php bin/console app:user:create prenom@domaine.fr --admin
docker compose --profile app down                                         # arrêt
```

Conseil : placer le projet hors de OneDrive (par exemple `C:\dev\CJA`) pour éviter les
verrous de fichiers et la synchronisation de `vendor/` et `node_modules/`.

## Démarrage (macOS / Linux, natif)

Copier d'abord `api/.env.local.dist` vers `api/.env.local` : il aligne la base et la boîte
e-mail sur les services de `docker-compose.yml`.

```bash
# 1. Services d'infrastructure
docker compose up -d

# 2. Backend
cd api
composer install
php bin/console lexik:jwt:generate-keypair --skip-if-exists
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console doctrine:fixtures:load --no-interaction
symfony serve -d                      # http://127.0.0.1:8000

# 3. Frontend
cd ../frontend
npm install
npm start                             # http://localhost:4200
```

Le serveur de développement Angular relaie `/api` vers `http://127.0.0.1:8000`
(`frontend/proxy.conf.json`) : aucun souci de CORS en local.

| Service | Adresse |
| --- | --- |
| Application | http://localhost:4200 |
| Documentation de l'API | http://127.0.0.1:8000/api |
| Adminer (base) | http://localhost:8081 — serveur `database`, user/pass/base `cja` |
| Mailpit (e-mails) | http://127.0.0.1:8025 |

### Compte de démonstration

Les fixtures créent `artisan@cja.test` / `MotDePasse123`, cinq clients (`CLI-0001` à
`CLI-0005`), huit chantiers et quatre fournisseurs. Pour un autre compte :

```bash
php bin/console app:user:create prenom@domaine.fr --admin
```

## Ce que couvre le Lot 1

- **Clients** : particuliers et professionnels, SIRET obligatoire et validé (14 chiffres,
  clé de Luhn) pour les professionnels, adresse de facturation, recherche globale
  insensible à la casse sur numéro / nom / raison sociale / email.
- **Chantiers** : plusieurs adresses par client, activables ou désactivables, gérées
  depuis la fiche client.
- **Fournisseurs** : annuaire simple, nom unique, qui alimentera les annexes de débours.
- **Numérotation continue** : `numeroClient` est attribué par `App\Service\NumberGenerator`
  sous verrou pessimiste. Aucun trou de séquence, même si une transaction échoue.
- **Authentification JWT** : `POST /api/login_check`, puis `Authorization: Bearer <jeton>`.
  Toutes les ressources métier sont fermées aux requêtes anonymes.
- **Importation CSV / Excel** en trois temps, pour reprendre l'antériorité sans risque.

### L'assistant d'importation

Disponible dans l'application sous **Importation**, et exposé par l'API :

1. `POST /api/imports` (multipart `fichier` + `type=CLIENTS|HISTORIQUE`) — stocke le
   fichier, détecte le séparateur et l'encodage, renvoie les colonnes et un aperçu de
   10 lignes avec un pré-mapping par similarité de libellé.
2. `POST /api/imports/{id}/mapping` — associe chaque colonne du fichier à un champ métier.
3. `POST /api/imports/{id}/execution?aBlanc=true|false` — valide ligne par ligne et
   renvoie un rapport. En `aBlanc=true` tout est annulé à la fin ; en réel, l'import est
   **atomique** : une seule ligne invalide et rien n'est écrit.

Deux fichiers d'exemple permettent de tester le parcours de bout en bout :
`api/fixtures/clients-exemple.csv` puis `api/fixtures/historique-exemple.csv`
(importer les clients d'abord, chaque pièce devant être rattachée à une fiche).

L'import d'historique réserve aussi les séquences de numérotation : après la reprise d'une
facture `FC2026-02-001`, la prochaine facture émise par l'application sera `FC2026-02-002`.

## Ce que couvre le Lot 2

- **Devis et factures** de prestation, avec lignes de texte (titres, descriptifs) et lignes chiffrées.
- **Bibliothèque** `PREST-ML`, `PREST-M2`, `PREST-U`, `PREST-FORFAIT`, prix seulement indicatifs.
- **TVA** par les codes `0` (0 %), `1` (5,5 %), `2` (10 %) et `3` (20 %). Les totaux sont recalculés côté serveur.
- **Verrou** : passer un brouillon à « Envoyé » fige les lignes. Ensuite, seul le statut peut changer.
- **PDF** via Gotenberg (`GOTENBERG_URL`, défaut `http://127.0.0.1:3000/`) : en-tête, décennale, IBAN, pénalités, indemnité de 40 €, mention « TVA non applicable, art. 293 B du CGI » si le régime est la franchise et qu'une ligne est à 0 %.
- **Réglages** : fiche entreprise unique, valeurs d'exemple à remplacer dans l'application.

## Ce que couvre le Lot 3

- **Acompte** sur le devis, 30 % par défaut, facultatif : 0 ou un champ vide signifie pas d'acompte. Modifiable tant que le devis est en brouillon. S'il y en a un, le PDF indique le montant à verser à la signature. Le calcul suit le HT de chaque taux de TVA. Sans acompte, seul le solde se génère.
- **Facture d'acompte** : une fois le devis accepté, **Générer une facture d'acompte** crée la pièce `FA…`. Au retour sur le devis, le bouton devient **Voir facture d'acompte**.
- **Facture de solde** : **Générer une facture de solde** reprend les lignes du devis et déduit les acomptes déjà envoyés. Numéro `FC…`. Au retour, **Voir facture de solde**.
- **Annexe de débours** : **Générer une annexe de débours** ouvre un brouillon rattaché au devis ou à la facture, lignes par fournisseur, mention « Les matériaux seront à régler directement auprès de chaque fournisseur selon leur modalité de paiement. » Ses totaux ne s'ajoutent pas au devis. Les annexes enregistrées se rouvrent depuis la pièce d'origine.

Parcours : **Documents → Nouveau devis**, saisir l'acompte et les lignes, **Enregistrer**, **Marquer comme envoyé**, **Marquer comme accepté**, puis **Générer une facture d'acompte**. Après envoi de cet acompte, **Générer une facture de solde**. De retour sur le devis, ces boutons ouvrent la pièce déjà créée. **Générer une annexe de débours** ouvre un brouillon prérempli ; la pièce n'existe qu'après **Enregistrer** ou **Marquer comme envoyé**, puis se rouvre depuis le devis ou la facture.

## Ce que couvre le Lot 4

- **Recherche** : le champ de la barre interroge `GET /api/recherche?q=` (au moins deux caractères) sur le nom du client, le numéro de pièce, le libellé d'une ligne et la commune du chantier. Dans la liste des documents, le champ filtre aussi le numéro, l'objet et le nom du client.
- **Duplication** : sur un devis, **Dupliquer** appelle `POST /api/documents/{id}/dupliquer` et ouvre un nouveau brouillon `DV…` aux mêmes lignes de texte et de prestation. Une facture ne se duplique pas.
- **Tableau de bord** : page d'accueil, compteurs en nombre et en TTC pour les pièces envoyées, acceptées, payées, et celles dont l'échéance est dépassée. Le retard se filtre avec `enRetard=1`, sans changer le statut enregistré.
- **Inaltérabilité** : le tableau signale une pièce déjà émise dont le verrou est absent.
- **Export comptable** : `GET /api/exports/comptable?du=YYYY-MM-DD&au=YYYY-MM-DD` renvoie les factures de prestation et d'acompte déjà envoyées, avec une colonne de TVA par taux.

## Ce que couvre le Lot 5

- **E-mail** : **Envoyer** sur un devis, une facture, une facture d'acompte ou une annexe de débours ouvre un message déjà rempli (réglages de l'entreprise, jetons `{{client}}`, `{{numero}}`, `{{objet}}`, `{{montant}}`, `{{echeance}}`, `{{entreprise}}`, `{{debours}}`), encore modifiable, avec le PDF en pièce jointe. Les annexes de débours liées sont jointes par défaut et se décochent une à une. L'expéditeur est l'e-mail de l'entreprise. Un envoi réussi passe la pièce de brouillon à « Envoyé » et la verrouille. En local, Mailpit reçoit les messages (`MAILER_DSN=smtp://127.0.0.1:1025` dans `api/.env.local`, boîte sur http://127.0.0.1:8025). `api/.env` reste sur `null://null` ; une vraie boîte (OVH, Gmail avec mot de passe d'application) se met dans `.env.local` au moment de l'envoi réel.

## Tests

```bash
cd api && php bin/phpunit
```

Les tests repartent d'un schéma vierge à chaque cas et couvrent le refus d'accès sans
jeton, la numérotation séquentielle, la validation du SIRET, les filtres de recherche,
l'assistant d'importation, le calcul de TVA, le PDF, les acomptes, la recherche, la
duplication, l'export comptable, le retard et l'envoi par e-mail.

## Déploiement en production (VPS Debian 13)

La production utilise `docker-compose.prod.yml` (distinct de `docker-compose.yml`, réservé au
développement) : **Caddy** (HTTPS, fichiers Angular, relais `/api`), **PHP-FPM**, **PostgreSQL 17**
et **Gotenberg**. Seuls les ports 80 et 443 sont ouverts ; la base et Gotenberg restent sur un
réseau Docker interne sans accès à Internet. Adminer et Mailpit ne sont pas déployés.

### 1. Depuis ton PC : pousser le code et préparer l'accès SSH

```powershell
git add -A; git commit -m "Environnement de production"; git push

ssh-keygen -t ed25519 -C "cja-vps"        # si tu n'as pas encore de clé ; accepte les valeurs par défaut
Get-Content $HOME\.ssh\id_ed25519.pub      # la clé PUBLIQUE, à copier
scp deploy/bootstrap-vps.sh root@IP_DU_VPS:/root/
```

### 2. Sur le VPS : préparer le serveur (une seule fois)

```bash
ssh root@IP_DU_VPS
DEPLOY_SSH_PUBKEY="ssh-ed25519 AAAA... cja-vps" bash /root/bootstrap-vps.sh
```

Le script installe les mises à jour de sécurité automatiques, crée l'utilisateur `deploy`,
**interdit la connexion root et par mot de passe** (clé SSH uniquement), configure le pare-feu
`ufw` (22, 80, 443), `fail2ban`, Docker et une sauvegarde quotidienne. Avant de fermer la session
root, vérifie depuis un autre terminal que `ssh deploy@IP_DU_VPS` fonctionne.

### 3. Récupérer le code (en `deploy`)

Le dépôt est cloné avec une clé de déploiement GitHub en lecture seule :

```bash
ssh-keygen -t ed25519 -f ~/.ssh/cja_deploy -N "" -C "cja-vps"
cat ~/.ssh/cja_deploy.pub        # à ajouter sur GitHub : dépôt > Settings > Deploy keys (sans droit d'écriture)

printf 'Host github.com\n  IdentityFile ~/.ssh/cja_deploy\n  IdentitiesOnly yes\n' >> ~/.ssh/config
chmod 600 ~/.ssh/config
git clone git@github.com:LucaasBrj/ComptaCJA.git ~/cja
```

### 4. Configurer les secrets (en `deploy`)

```bash
cd ~/cja
cp .env.prod.example .env.prod && chmod 600 .env.prod
openssl rand -hex 32     # à lancer trois fois : APP_SECRET, JWT_PASSPHRASE, POSTGRES_PASSWORD
nano .env.prod           # SITE_ADDRESS = IP du VPS, secrets, MAILER_DSN (SMTP réel)
```

`.env.prod` n'est jamais versionné. Sans nom de domaine, le certificat HTTPS est auto-signé :
le navigateur affiche un avertissement à accepter une fois. Avec un domaine, un enregistrement DNS
de type A vers l'IP du VPS puis `SITE_ADDRESS=cja.exemple.fr` et `CADDY_TLS=tls vous@exemple.fr`
suffisent pour obtenir un vrai certificat Let's Encrypt.

### 5. Déployer

```bash
./deploy/deploy.sh
docker compose -f docker-compose.prod.yml --env-file .env.prod exec php \
  php bin/console app:user:create ton@mail.fr --admin
```

L'application est ensuite sur `https://IP_DU_VPS`. Les **fixtures ne sont jamais chargées** en
production : elles purgent la base et créent un compte au mot de passe connu.

### Mises à jour, sauvegardes, restauration

| Action | Commande (dans `~/cja`) |
| --- | --- |
| Mettre à jour | `./deploy/deploy.sh` (git pull, build, sauvegarde, migrations, redémarrage) |
| Sauvegarder maintenant | `./deploy/backup.sh` |
| Restaurer | `./deploy/restore.sh /var/backups/cja/db_AAAA-MM-JJ_HHMMSS.sql.gz` |
| Journaux | `docker compose -f docker-compose.prod.yml --env-file .env.prod logs -f web php` |
| État | `docker compose -f docker-compose.prod.yml --env-file .env.prod ps` |

Une sauvegarde de la base et des fichiers importés est faite chaque nuit à 3 h 15 dans
`/var/backups/cja` (14 jours conservés ; `systemctl list-timers cja-backup.timer`). Ces copies
restent sur le VPS : **copie-les régulièrement ailleurs** (rclone, rsync, ou l'option de snapshot de
ton hébergeur). Conserve aussi une copie de `.env.prod` dans un gestionnaire de mots de passe.

### Sécurité : ce qui est en place

- SSH par clé uniquement, root interdit, `fail2ban`, pare-feu `ufw`, mises à jour automatiques
  (redémarrage nocturne à 4 h 30 si le noyau l'exige).
- Conteneurs sans privilèges superflus (`cap_drop: ALL`, `no-new-privileges`), PHP en utilisateur
  non-root, base et Gotenberg sans accès à Internet et sans port publié.
- En-têtes HSTS et Content-Security-Policy, documentation `/api/docs` fermée, aucune requête
  cross-origin acceptée (front et API partagent la même origine).
- Secrets propres à la production (jamais ceux de `api/.env` ou `.env.dev`).
