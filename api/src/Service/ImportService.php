<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Import;
use App\Enum\StatutImport;
use App\Enum\TypeImport;
use App\Import\ChampImport;
use App\Import\ConvertisseurValeur;
use App\Import\ImportHandlerInterface;
use App\Import\LecteurFichier;
use App\Import\ResultatLigne;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Uid\Uuid;

/**
 * Orchestre l'assistant d'importation en trois temps : televersement et detection
 * des colonnes, mapping, puis execution a blanc ou reelle.
 */
final class ImportService
{
    private const LIGNES_APERCU = 10;
    private const TAILLE_LOT = 50;

    /**
     * @param iterable<ImportHandlerInterface> $handlers
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ManagerRegistry $doctrine,
        private readonly LecteurFichier $lecteurFichier,
        #[AutowireIterator(ImportHandlerInterface::class)]
        private readonly iterable $handlers,
        #[Autowire('%kernel.project_dir%/var/imports')]
        private readonly string $repertoireImports,
    ) {
    }

    /**
     * Etape 1 : stocke le fichier, detecte ses colonnes et propose un pre-mapping.
     */
    public function demarrer(UploadedFile $fichier, TypeImport $type): Import
    {
        $extension = strtolower($fichier->getClientOriginalExtension() ?: (string) $fichier->guessExtension());

        if (!$this->lecteurFichier->extensionSupportee($extension)) {
            throw new \InvalidArgumentException(\sprintf('Format "%s" non pris en charge. Formats acceptes : CSV, XLSX, XLS, ODS.', $extension));
        }

        if (!is_dir($this->repertoireImports) && !mkdir($this->repertoireImports, 0o775, true) && !is_dir($this->repertoireImports)) {
            throw new \RuntimeException(\sprintf('Impossible de creer le repertoire "%s".', $this->repertoireImports));
        }

        $nomStocke = Uuid::v7()->toRfc4122().'.'.$extension;
        $nomOriginal = $fichier->getClientOriginalName();
        $fichier->move($this->repertoireImports, $nomStocke);

        $import = new Import($type, $nomOriginal, $nomStocke, $extension);
        $lecteur = $this->lecteurFichier->pour($extension);
        $chemin = $this->cheminComplet($import);

        $entetes = $lecteur->entetes($chemin);
        $import->setColonnesDetectees($entetes);
        $import->setApercu($this->extraireApercu($lecteur->lignes($chemin)));
        $import->setMapping($this->preMapper($entetes, $this->handler($type)->champs()));

        $this->entityManager->persist($import);
        $this->entityManager->flush();

        return $import;
    }

    /**
     * Etape 2 : enregistre l'association champ metier => colonne du fichier.
     *
     * @param array<string, string|null> $mapping
     */
    public function definirMapping(Import $import, array $mapping): Import
    {
        $codesConnus = array_map(
            static fn (ChampImport $champ): string => $champ->code,
            $this->handler($import->getType())->champs(),
        );
        $colonnes = $import->getColonnesDetectees();
        $retenu = [];

        foreach ($mapping as $code => $colonne) {
            if (null === $colonne || '' === $colonne) {
                continue;
            }

            if (!\in_array($code, $codesConnus, true)) {
                throw new \InvalidArgumentException(\sprintf('Champ inconnu : "%s".', $code));
            }

            if (!\in_array($colonne, $colonnes, true)) {
                throw new \InvalidArgumentException(\sprintf('La colonne "%s" est absente du fichier.', $colonne));
            }

            $retenu[$code] = $colonne;
        }

        $manquants = [];

        foreach ($this->handler($import->getType())->champs() as $champ) {
            if ($champ->obligatoire && !isset($retenu[$champ->code])) {
                $manquants[] = $champ->libelle;
            }
        }

        if ([] !== $manquants) {
            throw new \InvalidArgumentException(\sprintf('Champs obligatoires non associes : %s.', implode(', ', $manquants)));
        }

        $import->setMapping($retenu)->setStatut(StatutImport::MAPPE);
        $this->entityManager->flush();

        return $import;
    }

    /**
     * Etape 3 : parcourt le fichier et produit un rapport ligne par ligne.
     *
     * En mode "a blanc", tout le travail est realise puis annule par un rollback :
     * les contraintes de base de donnees sont donc reellement eprouvees, sans
     * qu'aucune donnee ne subsiste.
     */
    public function executer(Import $import, bool $aBlanc): Import
    {
        if (StatutImport::EN_ATTENTE_MAPPING === $import->getStatut()) {
            throw new \LogicException('Le mapping des colonnes doit etre valide avant l\'execution.');
        }

        $handler = $this->handler($import->getType());
        $lecteur = $this->lecteurFichier->pour($import->getExtension());
        $connexion = $this->entityManager->getConnection();
        $identifiant = $import->getId();

        $rapport = [];
        $compteurs = [ResultatLigne::STATUT_CREE => 0, ResultatLigne::STATUT_MIS_A_JOUR => 0, ResultatLigne::STATUT_IGNORE => 0, ResultatLigne::STATUT_ERREUR => 0];
        $traitees = 0;
        $echecGlobal = null;

        $connexion->beginTransaction();

        try {
            foreach ($lecteur->lignes($this->cheminComplet($import)) as $numeroLigne => $ligne) {
                ++$traitees;
                $resultat = $this->traiterLigne($handler, $import->getMapping(), $ligne);
                ++$compteurs[$resultat->statut];

                $rapport[] = [
                    'ligne' => $numeroLigne,
                    'statut' => $resultat->statut,
                    'messages' => $resultat->messages,
                    'apercu' => $resultat->apercu,
                ];

                if (0 === $traitees % self::TAILLE_LOT) {
                    $this->entityManager->flush();
                }
            }

            $this->entityManager->flush();

            if ($aBlanc || $compteurs[ResultatLigne::STATUT_ERREUR] > 0) {
                // Un fichier partiellement invalide est refuse en bloc : l'artisan
                // corrige son fichier et relance, plutot que de deviner ce qui est passe.
                $connexion->rollBack();
            } else {
                $connexion->commit();
            }
        } catch (\Throwable $exception) {
            if ($connexion->isTransactionActive()) {
                $connexion->rollBack();
            }

            $echecGlobal = $exception->getMessage();
        }

        // Un flush en echec ferme l'EntityManager et un rollback laisse des entites
        // dans un etat incoherent : on repart d'un gestionnaire propre pour pouvoir
        // enregistrer le rapport, qui est justement ce qui interesse l'utilisateur.
        if (!$this->entityManager->isOpen()) {
            $this->doctrine->resetManager();
        }

        $gestionnaire = $this->doctrine->getManager();
        $gestionnaire->clear();
        $import = $gestionnaire->find(Import::class, $identifiant) ?? throw new \RuntimeException('Session d\'import introuvable.');

        if (null !== $echecGlobal) {
            $rapport[] = ['ligne' => 0, 'statut' => ResultatLigne::STATUT_ERREUR, 'messages' => [$echecGlobal], 'apercu' => 'Interruption du traitement'];
            ++$compteurs[ResultatLigne::STATUT_ERREUR];
        }

        $import
            ->setNbLignes($traitees)
            ->setNbSucces($compteurs[ResultatLigne::STATUT_CREE] + $compteurs[ResultatLigne::STATUT_MIS_A_JOUR])
            ->setNbErreurs($compteurs[ResultatLigne::STATUT_ERREUR])
            ->setRapport($rapport);

        if ($aBlanc) {
            $import->setStatut(StatutImport::MAPPE);
        } else {
            $import->setStatut($compteurs[ResultatLigne::STATUT_ERREUR] > 0 ? StatutImport::ECHOUE : StatutImport::TERMINE);
            $import->marquerExecute();
        }

        $gestionnaire->flush();

        return $import;
    }

    /**
     * @return list<ChampImport>
     */
    public function champsDisponibles(TypeImport $type): array
    {
        return $this->handler($type)->champs();
    }

    /**
     * @param array<string, string>      $mapping
     * @param array<string, string|null> $ligne
     */
    private function traiterLigne(ImportHandlerInterface $handler, array $mapping, array $ligne): ResultatLigne
    {
        $valeurs = [];

        foreach ($mapping as $code => $colonne) {
            $valeurs[$code] = $ligne[$colonne] ?? null;
        }

        try {
            return $handler->traiter($valeurs);
        } catch (\Throwable $exception) {
            return ResultatLigne::erreur('ligne rejetee', [$exception->getMessage()]);
        }
    }

    /**
     * Associe automatiquement les colonnes dont l'intitule correspond a un champ
     * connu, apres normalisation (casse, accents, ponctuation).
     *
     * @param list<string>      $entetes
     * @param list<ChampImport> $champs
     *
     * @return array<string, string>
     */
    private function preMapper(array $entetes, array $champs): array
    {
        $parCleNormalisee = [];

        foreach ($entetes as $entete) {
            $parCleNormalisee[ConvertisseurValeur::normaliserEntete($entete)] ??= $entete;
        }

        $mapping = [];

        foreach ($champs as $champ) {
            foreach ($champ->clesReconnues() as $cle) {
                if (isset($parCleNormalisee[$cle]) && !\in_array($parCleNormalisee[$cle], $mapping, true)) {
                    $mapping[$champ->code] = $parCleNormalisee[$cle];
                    break;
                }
            }
        }

        return $mapping;
    }

    /**
     * @param iterable<int, array<string, string|null>> $lignes
     *
     * @return list<array<string, string|null>>
     */
    private function extraireApercu(iterable $lignes): array
    {
        $apercu = [];

        foreach ($lignes as $ligne) {
            $apercu[] = $ligne;

            if (\count($apercu) >= self::LIGNES_APERCU) {
                break;
            }
        }

        return $apercu;
    }

    private function handler(TypeImport $type): ImportHandlerInterface
    {
        foreach ($this->handlers as $handler) {
            if ($handler->type() === $type) {
                return $handler;
            }
        }

        throw new \InvalidArgumentException(\sprintf('Aucun traitement n\'est defini pour le type d\'import "%s".', $type->value));
    }

    private function cheminComplet(Import $import): string
    {
        return $this->repertoireImports.'/'.$import->getCheminFichier();
    }
}
