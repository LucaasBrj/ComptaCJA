# CJA — Devis, facturation et suivi de chantier

Application de gestion pour artisan du bâtiment : base clientèle, chantiers, fournisseurs,
devis et factures. Ce dépôt contient le **socle technique** et le **Lot 1 (base clientèle)**.

- `api/` — Symfony 7.4 LTS + API Platform 4.4 (PHP 8.5)
- `frontend/` — Angular 22 + Angular Material
- `docker-compose.yml` — PostgreSQL 17, Gotenberg 8 (PDF, branché au Lot 2), Adminer

## Prérequis

PHP 8.4+, Composer, Node 20+, et un runtime Docker. Sur macOS sans Docker Desktop :

```bash
brew install composer symfony node colima docker docker-compose
colima start
```

## Démarrage

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

## L'assistant d'importation

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

## Tests

```bash
cd api && php bin/phpunit
```

Les tests repartent d'un schéma vierge à chaque cas et couvrent le refus d'accès sans
jeton, la numérotation séquentielle, la validation du SIRET, les filtres de recherche et
les quatre chemins de l'assistant d'importation.

## Hors périmètre à ce stade

Lignes de prestation et codes TVA, génération des PDF via Gotenberg, factures d'acompte,
annexes de débours et tableaux de bord arrivent aux lots suivants. Le conteneur Gotenberg
est déjà provisionné mais n'est pas encore appelé.
