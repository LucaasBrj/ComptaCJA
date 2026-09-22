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

- **Acompte** sur le devis, 30 % par défaut, modifiable tant que le devis est en brouillon. Le PDF indique le montant à verser à la signature. Le calcul suit le HT de chaque taux de TVA.
- **Facture d'acompte** : une fois le devis accepté, un clic crée la pièce `FA…`. Une seconde facture d'acompte est refusée.
- **Facture de solde** : reprend les lignes du devis et déduit les acomptes déjà envoyés. Numéro `FC…`.
- **Annexe de débours** : rattachée à un devis ou à une facture, lignes par fournisseur, mention « Les matériaux seront à régler directement auprès de chaque fournisseur selon leur modalité de paiement. » Ses totaux ne s'ajoutent pas au devis.

Parcours : **Documents → Nouveau devis**, saisir l'acompte et les lignes, **Enregistrer**, **Marquer comme envoyé**, **Marquer comme accepté**, puis **Facture d'acompte**. Après envoi de cet acompte, **Facture de solde**. **Annexe de débours** se crée depuis le devis ou la facture, puis se complète avec les fournisseurs.

## Ce que couvre le Lot 4

- **Recherche** : le champ de la barre interroge `GET /api/recherche?q=` (au moins deux caractères) sur le nom du client, le numéro de pièce, le libellé d'une ligne et la commune du chantier. Dans la liste des documents, le champ filtre aussi le numéro, l'objet et le nom du client.
- **Duplication** : sur un devis, **Dupliquer** appelle `POST /api/documents/{id}/dupliquer` et ouvre un nouveau brouillon `DV…` aux mêmes lignes de texte et de prestation. Une facture ne se duplique pas.
- **Tableau de bord** : page d'accueil, compteurs en nombre et en TTC pour les pièces envoyées, acceptées, payées, et celles dont l'échéance est dépassée. Le retard se filtre avec `enRetard=1`, sans changer le statut enregistré.
- **Inaltérabilité** : le tableau signale une pièce déjà émise dont le verrou est absent.
- **Export comptable** : `GET /api/exports/comptable?du=YYYY-MM-DD&au=YYYY-MM-DD` renvoie les factures de prestation et d'acompte déjà envoyées, avec une colonne de TVA par taux.

## Ce que couvre le Lot 5

- **E-mail** : **Envoyer** sur un devis, une facture, une facture d'acompte ou une annexe de débours ouvre un message déjà rempli (réglages de l'entreprise, jetons `{{client}}`, `{{numero}}`, `{{objet}}`, `{{montant}}`, `{{echeance}}`, `{{entreprise}}`), encore modifiable, avec le PDF en pièce jointe. Les annexes de débours liées sont jointes par défaut et se décochent une à une. L'expéditeur est l'e-mail de l'entreprise. Un envoi réussi passe la pièce de brouillon à « Envoyé » et la verrouille. En local, Mailpit reçoit les messages (`MAILER_DSN=smtp://127.0.0.1:1025` dans `api/.env.local`, boîte sur http://127.0.0.1:8025). `api/.env` reste sur `null://null` ; une vraie boîte (OVH, Gmail avec mot de passe d'application) se met dans `.env.local` au moment de l'envoi réel.

## Tests

```bash
cd api && php bin/phpunit
```

Les tests repartent d'un schéma vierge à chaque cas et couvrent le refus d'accès sans
jeton, la numérotation séquentielle, la validation du SIRET, les filtres de recherche,
l'assistant d'importation, le calcul de TVA, le PDF, les acomptes, la recherche, la
duplication, l'export comptable, le retard et l'envoi par e-mail.
