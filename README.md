# CJA — Devis, facturation et suivi de chantier

Application de gestion pour artisan du bâtiment : base clientèle, chantiers, fournisseurs,
devis et factures. Ce dépôt contient le **socle technique**, le **Lot 1 (base clientèle)**, le **Lot 2 (devis, prestations, PDF)** et le **Lot 3 (acomptes, solde, débours)**.

- `api/` — Symfony 7.4 LTS + API Platform 4.4 (PHP 8.5)
- `frontend/` — Angular 22 + Angular Material
- `docker-compose.yml` — PostgreSQL 17, Gotenberg 8 (PDF, branché au Lot 2), Adminer

## Prérequis

PHP 8.4+, Composer, Node 20+, et un runtime Docker. Sur macOS sans Docker Desktop :

```bash
brew install composer symfony node colima docker docker-compose
colima start
```

## Ce que couvre le Lot 2

- **Devis et factures** de prestation, avec lignes de texte (titres, descriptifs) et lignes chiffrées.
- **Bibliothèque** `PREST-ML`, `PREST-M2`, `PREST-U`, `PREST-FORFAIT`, prix seulement indicatifs.
- **TVA** par les codes `0` (0 %), `1` (5,5 %), `2` (10 %) et `3` (20 %). Les totaux sont recalculés côté serveur.
- **Verrou** : passer un brouillon à « Envoyé » fige les lignes. Ensuite, seul le statut peut changer.
- **PDF** via Gotenberg (`GOTENBERG_URL`, défaut `http://127.0.0.1:3000/`) : en-tête, décennale, IBAN, pénalités, indemnité de 40 €, mention « TVA non applicable, art. 293 B du CGI » si le régime est la franchise et qu'une ligne est à 0 %.
- **Réglages** : fiche entreprise unique, valeurs d'exemple à remplacer dans l'application.

La duplication, la recherche globale et les tableaux de bord restent au lot 4.

## Ce que couvre le Lot 3

- **Acompte** sur le devis, 30 % par défaut, modifiable tant que le devis est en brouillon. Le PDF indique le montant à verser à la signature. Le calcul suit le HT de chaque taux de TVA.
- **Facture d'acompte** : une fois le devis accepté, un clic crée la pièce `FA…`. Une seconde facture d'acompte est refusée.
- **Facture de solde** : reprend les lignes du devis et déduit les acomptes déjà envoyés. Numéro `FC…`.
- **Annexe de débours** : rattachée à un devis ou à une facture, lignes par fournisseur, mention « Les matériaux seront à régler directement auprès de chaque fournisseur selon leur modalité de paiement. » Ses totaux ne s'ajoutent pas au devis.

Parcours : **Documents → Nouveau devis**, saisir l'acompte et les lignes, **Enregistrer**, **Marquer comme envoyé**, **Marquer comme accepté**, puis **Facture d'acompte**. Après envoi de cet acompte, **Facture de solde**. **Annexe de débours** se crée depuis le devis ou la facture, puis se complète avec les fournisseurs.

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
jeton, la numérotation séquentielle, la validation du SIRET, les filtres de recherche,
l'assistant d'importation, le calcul de TVA, le PDF et les acomptes.

## Hors périmètre à ce stade

La duplication, la recherche globale, les tableaux de bord et l'export comptable restent
au lot 4.
