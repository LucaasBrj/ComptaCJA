<?php

declare(strict_types=1);

namespace App\Import;

use App\Entity\Chantier;
use App\Entity\Client;
use App\Entity\Document;
use App\Enum\StatutDocument;
use App\Enum\TypeDocument;
use App\Enum\TypeImport;
use App\Repository\ClientRepository;
use App\Repository\DocumentRepository;
use App\Service\NumberGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Reprise de l'historique des devis et factures emis avant l'application.
 *
 * Les pieces sont marquees "legacy" et verrouillees : elles alimentent la vue
 * client 360 et, surtout, elles reservent leur numero dans la sequence pour que
 * la numerotation reprenne sans rupture ni doublon.
 */
final class HistoriqueImportHandler implements ImportHandlerInterface
{
    private const CORRESPONDANCES_TYPE = [
        'devis' => TypeDocument::DEVIS->value,
        'dv' => TypeDocument::DEVIS->value,
        'facture' => TypeDocument::FACTURE->value,
        'fc' => TypeDocument::FACTURE->value,
        'facturedeprestation' => TypeDocument::FACTURE->value,
        'acompte' => TypeDocument::FACTURE_ACOMPTE->value,
        'factureacompte' => TypeDocument::FACTURE_ACOMPTE->value,
        'facturedacompte' => TypeDocument::FACTURE_ACOMPTE->value,
        'fa' => TypeDocument::FACTURE_ACOMPTE->value,
        'debours' => TypeDocument::ANNEXE_DEBOURS->value,
        'annexedebours' => TypeDocument::ANNEXE_DEBOURS->value,
    ];

    private const CORRESPONDANCES_STATUT = [
        'brouillon' => StatutDocument::BROUILLON->value,
        'envoye' => StatutDocument::ENVOYE->value,
        'enattente' => StatutDocument::ENVOYE->value,
        'accepte' => StatutDocument::ACCEPTE->value,
        'signe' => StatutDocument::ACCEPTE->value,
        'refuse' => StatutDocument::REFUSE->value,
        'perdu' => StatutDocument::REFUSE->value,
        'paye' => StatutDocument::PAYE->value,
        'regle' => StatutDocument::PAYE->value,
        'enretard' => StatutDocument::EN_RETARD->value,
        'impaye' => StatutDocument::EN_RETARD->value,
        'annule' => StatutDocument::ANNULE->value,
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ClientRepository $clientRepository,
        private readonly DocumentRepository $documentRepository,
        private readonly ValidatorInterface $validator,
        private readonly NumberGenerator $numberGenerator,
    ) {
    }

    public function type(): TypeImport
    {
        return TypeImport::HISTORIQUE;
    }

    public function champs(): array
    {
        return [
            new ChampImport('numero', 'Numero de piece', obligatoire: true, alias: ['numero', 'n piece', 'no facture', 'numero facture', 'numero devis', 'reference']),
            new ChampImport('type', 'Type de piece', obligatoire: true, alias: ['type', 'nature'], aide: 'Devis, Facture, Acompte ou Debours.'),
            new ChampImport('statut', 'Statut', alias: ['etat', 'situation'], aide: 'Paye par defaut pour les factures, Accepte pour les devis.'),
            new ChampImport('dateEmission', 'Date d\'emission', obligatoire: true, alias: ['date', 'date facture', 'date devis']),
            new ChampImport('dateEcheance', 'Date d\'echeance', alias: ['echeance', 'date limite']),
            new ChampImport('objet', 'Objet', alias: ['libelle', 'description', 'designation']),
            new ChampImport('montantHt', 'Montant HT', obligatoire: true, alias: ['ht', 'total ht', 'montant hors taxe']),
            new ChampImport('montantTva', 'Montant TVA', alias: ['tva', 'total tva']),
            new ChampImport('montantTtc', 'Montant TTC', alias: ['ttc', 'total ttc', 'total']),
            new ChampImport('clientNumero', 'Client - numero', alias: ['n client', 'code client', 'numero client']),
            new ChampImport('clientNom', 'Client - nom ou raison sociale', alias: ['client', 'nom client', 'societe']),
            new ChampImport('chantierLibelle', 'Chantier', alias: ['chantier', 'nom du chantier']),
        ];
    }

    public function traiter(array $valeurs): ResultatLigne
    {
        $lire = static fn (string $code): ?string => ConvertisseurValeur::chaine($valeurs[$code] ?? null);

        $numero = $lire('numero');
        $apercu = trim(\sprintf('%s %s', $numero ?? '(sans numero)', $lire('clientNom') ?? ''));

        if (null === $numero) {
            return ResultatLigne::erreur($apercu, ['Le numero de piece est obligatoire pour garantir la continuite de la sequence.']);
        }

        if ($this->documentRepository->numeroExiste($numero)) {
            return ResultatLigne::ignore($apercu, \sprintf('La piece "%s" est deja presente en base.', $numero));
        }

        // Une colonne "Client" unique contient tantot un numero, tantot un nom :
        // on soumet la valeur aux deux criteres.
        $nomClient = $lire('clientNom');
        $client = $this->clientRepository->trouverPourImport(
            $lire('clientNumero') ?? $nomClient,
            null,
            $nomClient,
            $nomClient,
        );

        if (null === $client) {
            return ResultatLigne::erreur($apercu, [
                'Aucun client ne correspond : importez d\'abord le fichier clients, ou renseignez le numero de client.',
            ]);
        }

        $type = ConvertisseurValeur::versEnumeration($lire('type'), self::CORRESPONDANCES_TYPE)
            ?? $this->deduireTypeDepuisNumero($numero);

        if (null === $type) {
            return ResultatLigne::erreur($apercu, ['Type de piece non reconnu (attendu : Devis, Facture, Acompte ou Debours).']);
        }

        $dateEmission = ConvertisseurValeur::date($lire('dateEmission'));

        if (null === $dateEmission) {
            return ResultatLigne::erreur($apercu, ['Date d\'emission absente ou illisible (formats acceptes : 31/12/2026, 2026-12-31).']);
        }

        $montantHt = ConvertisseurValeur::decimal($lire('montantHt'));

        if (null === $montantHt) {
            return ResultatLigne::erreur($apercu, ['Montant HT absent ou illisible.']);
        }

        $typeDocument = TypeDocument::from($type);
        $montantTva = ConvertisseurValeur::decimal($lire('montantTva'));
        $montantTtc = ConvertisseurValeur::decimal($lire('montantTtc'));

        // On reconstitue le montant manquant plutot que de rejeter la ligne : les
        // anciens fichiers ne comportent souvent que deux des trois colonnes.
        if (null === $montantTtc) {
            $montantTtc = number_format((float) $montantHt + (float) ($montantTva ?? '0'), 2, '.', '');
        }

        if (null === $montantTva) {
            $montantTva = number_format((float) $montantTtc - (float) $montantHt, 2, '.', '');
        }

        $document = (new Document())
            ->setNumero($numero)
            ->setType($typeDocument)
            ->setStatut($this->determinerStatut($lire('statut'), $typeDocument))
            ->setDateEmission($dateEmission)
            ->setDateEcheance(ConvertisseurValeur::date($lire('dateEcheance')))
            ->setObjet($lire('objet'))
            ->setMontantHt($montantHt)
            ->setMontantTva($montantTva)
            ->setMontantTtc($montantTtc)
            ->setClient($client)
            ->setChantier($this->trouverChantier($client, $lire('chantierLibelle')))
            ->setLegacy(true)
            ->setVerrouille(true);

        $violations = $this->validator->validate($document);

        if (\count($violations) > 0) {
            $messages = [];

            foreach ($violations as $violation) {
                $messages[] = \sprintf('%s : %s', $violation->getPropertyPath(), (string) $violation->getMessage());
            }

            return ResultatLigne::erreur($apercu, $messages);
        }

        $this->entityManager->persist($document);
        $this->reserverNumero($numero);

        return ResultatLigne::cree($apercu);
    }

    /**
     * Avance le compteur du mois concerne pour que la prochaine piece emise ne
     * reutilise pas un numero deja consomme par l'historique.
     */
    private function reserverNumero(string $numero): void
    {
        $decomposition = $this->numberGenerator->decomposerNumeroDocument($numero);

        if (null !== $decomposition) {
            $this->numberGenerator->reserverJusqua(
                $decomposition['perimetre'],
                $decomposition['periode'],
                $decomposition['rang'],
            );
        }
    }

    private function deduireTypeDepuisNumero(string $numero): ?string
    {
        $decomposition = $this->numberGenerator->decomposerNumeroDocument($numero);

        return $decomposition['perimetre'] ?? null;
    }

    private function determinerStatut(?string $valeur, TypeDocument $type): StatutDocument
    {
        $statut = ConvertisseurValeur::versEnumeration($valeur, self::CORRESPONDANCES_STATUT);

        if (null !== $statut) {
            return StatutDocument::from($statut);
        }

        // Une piece reprise de l'ancien outil est, par defaut, une affaire soldee.
        return TypeDocument::DEVIS === $type ? StatutDocument::ACCEPTE : StatutDocument::PAYE;
    }

    private function trouverChantier(Client $client, ?string $libelle): ?Chantier
    {
        if (null === $libelle) {
            return null;
        }

        foreach ($client->getChantiers() as $chantier) {
            if (0 === strcasecmp((string) $chantier->getLibelle(), $libelle)) {
                return $chantier;
            }
        }

        return null;
    }
}
