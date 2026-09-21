<?php

declare(strict_types=1);

namespace App\Import;

use App\Entity\Chantier;
use App\Entity\Client;
use App\Enum\TypeImport;
use App\Enum\TypologieClient;
use App\Repository\ClientRepository;
use App\Service\NumberGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Reprise du panel clients existant. Une ligne peut aussi porter une premiere
 * adresse de chantier, cas frequent dans les fichiers de suivi des artisans.
 */
final class ClientsImportHandler implements ImportHandlerInterface
{
    private const CORRESPONDANCES_TYPOLOGIE = [
        'particulier' => TypologieClient::PARTICULIER->value,
        'part' => TypologieClient::PARTICULIER->value,
        'prive' => TypologieClient::PARTICULIER->value,
        'professionnel' => TypologieClient::PROFESSIONNEL->value,
        'pro' => TypologieClient::PROFESSIONNEL->value,
        'entreprise' => TypologieClient::PROFESSIONNEL->value,
        'societe' => TypologieClient::PROFESSIONNEL->value,
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ClientRepository $clientRepository,
        private readonly ValidatorInterface $validator,
        private readonly NumberGenerator $numberGenerator,
    ) {
    }

    public function type(): TypeImport
    {
        return TypeImport::CLIENTS;
    }

    public function champs(): array
    {
        return [
            new ChampImport('numeroClient', 'Numero client', alias: ['n client', 'no client', 'code client', 'ref client', 'reference'], aide: 'Conserve tel quel si fourni, sinon genere automatiquement.'),
            new ChampImport('typologie', 'Typologie', alias: ['type', 'categorie', 'type de client'], aide: 'Particulier ou Professionnel. Particulier par defaut.'),
            new ChampImport('civilite', 'Civilite', alias: ['titre', 'm mme']),
            new ChampImport('nom', 'Nom', alias: ['nom de famille', 'nom client', 'lastname']),
            new ChampImport('prenom', 'Prenom', alias: ['firstname']),
            new ChampImport('raisonSociale', 'Raison sociale', alias: ['societe', 'entreprise', 'denomination']),
            new ChampImport('telephone', 'Telephone', alias: ['tel', 'portable', 'mobile', 'tel portable']),
            new ChampImport('email', 'Email', alias: ['mail', 'courriel', 'adresse mail']),
            new ChampImport('adresse', 'Adresse de facturation', alias: ['adresse', 'rue', 'adresse 1', 'adresse facturation']),
            new ChampImport('adresseComplement', 'Complement d\'adresse', alias: ['adresse 2', 'complement']),
            new ChampImport('codePostal', 'Code postal', alias: ['cp', 'code post']),
            new ChampImport('ville', 'Ville', alias: ['commune', 'localite']),
            new ChampImport('siret', 'SIRET'),
            new ChampImport('numeroTvaIntracom', 'N TVA intracommunautaire', alias: ['tva', 'n tva', 'no tva', 'numero tva', 'tva intracom']),
            new ChampImport('notes', 'Notes', alias: ['commentaire', 'observations', 'remarques']),
            new ChampImport('chantierLibelle', 'Chantier - libelle', alias: ['chantier', 'nom du chantier']),
            new ChampImport('chantierAdresse', 'Chantier - adresse', alias: ['adresse chantier']),
            new ChampImport('chantierCodePostal', 'Chantier - code postal', alias: ['cp chantier']),
            new ChampImport('chantierVille', 'Chantier - ville', alias: ['ville chantier', 'commune chantier']),
        ];
    }

    public function traiter(array $valeurs): ResultatLigne
    {
        $lire = static fn (string $code): ?string => ConvertisseurValeur::chaine($valeurs[$code] ?? null);

        $numeroClient = $lire('numeroClient');
        $nom = $lire('nom');
        $raisonSociale = $lire('raisonSociale');
        $email = $lire('email');
        $apercu = $this->composerApercu($numeroClient, $raisonSociale, $nom, $lire('prenom'));

        if (null === $nom && null === $raisonSociale) {
            return ResultatLigne::erreur($apercu, ['La ligne ne porte ni nom ni raison sociale : impossible d\'identifier le client.']);
        }

        $client = $this->clientRepository->trouverPourImport($numeroClient, $email, $nom, $raisonSociale);
        $existant = null !== $client;
        $client ??= new Client();

        $typologie = ConvertisseurValeur::versEnumeration($lire('typologie'), self::CORRESPONDANCES_TYPOLOGIE);
        $client
            ->setTypologie(null !== $typologie
                ? TypologieClient::from($typologie)
                : (null !== $raisonSociale ? TypologieClient::PROFESSIONNEL : TypologieClient::PARTICULIER))
            ->setCivilite($this->normaliserCivilite($lire('civilite')))
            ->setNom($nom)
            ->setPrenom($lire('prenom'))
            ->setRaisonSociale($raisonSociale)
            ->setTelephone($this->normaliserTelephone($lire('telephone')))
            ->setEmail($email)
            ->setSiret($lire('siret'))
            ->setNumeroTvaIntracom($lire('numeroTvaIntracom'))
            ->setNotes($lire('notes'))
            ->setImporte(true);

        $client->getAdresseFacturation()
            ->setLigne1($lire('adresse'))
            ->setLigne2($lire('adresseComplement'))
            ->setCodePostal($lire('codePostal'))
            ->setVille($lire('ville'));

        if (!$existant) {
            $client->setNumeroClient($this->attribuerNumero($numeroClient));
        }

        $chantier = $this->construireChantier($client, $valeurs);

        $violations = $this->validator->validate($client);

        if (null !== $chantier) {
            $violations->addAll($this->validator->validate($chantier));
        }

        if (\count($violations) > 0) {
            $messages = [];

            foreach ($violations as $violation) {
                $messages[] = \sprintf('%s : %s', $violation->getPropertyPath(), (string) $violation->getMessage());
            }

            return ResultatLigne::erreur($apercu, $messages);
        }

        $this->entityManager->persist($client);

        if (null !== $chantier) {
            $this->entityManager->persist($chantier);
        }

        return $existant ? ResultatLigne::misAJour($apercu) : ResultatLigne::cree($apercu);
    }

    /**
     * Respecte le numero present dans le fichier et avance le compteur en
     * consequence, pour que les creations suivantes ne le reattribuent pas.
     */
    private function attribuerNumero(?string $numeroFourni): string
    {
        if (null === $numeroFourni) {
            return $this->numberGenerator->numeroClient();
        }

        if (1 === preg_match('/^CLI-(\d+)$/', $numeroFourni, $capture)) {
            $this->numberGenerator->reserverJusqua(
                NumberGenerator::PERIMETRE_CLIENT,
                NumberGenerator::PERIODE_GLOBALE,
                (int) $capture[1],
            );
        }

        return $numeroFourni;
    }

    /**
     * @param array<string, string|null> $valeurs
     */
    private function construireChantier(Client $client, array $valeurs): ?Chantier
    {
        $lire = static fn (string $code): ?string => ConvertisseurValeur::chaine($valeurs[$code] ?? null);

        $libelle = $lire('chantierLibelle');
        $adresse = $lire('chantierAdresse');
        $ville = $lire('chantierVille');

        if (null === $libelle && null === $adresse && null === $ville) {
            return null;
        }

        $chantier = new Chantier();
        $chantier->setLibelle($libelle ?? $ville ?? 'Chantier importe');
        $chantier->getAdresse()
            ->setLigne1($adresse)
            ->setCodePostal($lire('chantierCodePostal'))
            ->setVille($ville);

        // Passe par l'adder pour que les deux cotes de la relation soient coherents.
        $client->addChantier($chantier);

        return $chantier;
    }

    private function normaliserCivilite(?string $valeur): ?string
    {
        if (null === $valeur) {
            return null;
        }

        return match (ConvertisseurValeur::normaliserEntete($valeur)) {
            'm', 'mr', 'monsieur' => 'M.',
            'mme', 'madame', 'mrs' => 'Mme',
            default => null,
        };
    }

    /**
     * Les fichiers contiennent des numeros ecrits "06.12.34.56.78" ou "+33 6 12 34 56 78".
     * On retire les separateurs pour satisfaire la contrainte de format.
     */
    private function normaliserTelephone(?string $valeur): ?string
    {
        if (null === $valeur) {
            return null;
        }

        $compacte = (string) preg_replace('/[^0-9+]/', '', $valeur);

        return '' !== $compacte ? $compacte : null;
    }

    private function composerApercu(?string ...$parties): string
    {
        $apercu = trim(implode(' ', array_filter($parties, static fn (?string $partie): bool => null !== $partie)));

        return '' !== $apercu ? $apercu : 'ligne vide';
    }
}
