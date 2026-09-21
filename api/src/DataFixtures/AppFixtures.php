<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Chantier;
use App\Entity\Client;
use App\Entity\Fournisseur;
use App\Entity\User;
use App\Enum\TypologieClient;
use App\Service\NumberGenerator;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Jeu de donnees de developpement : un compte artisan, un panel clients
 * representatif (particuliers et professionnels), leurs chantiers et les
 * principaux fournisseurs de materiaux.
 */
final class AppFixtures extends Fixture
{
    public const EMAIL_ARTISAN = 'artisan@cja.test';
    public const MOT_DE_PASSE_ARTISAN = 'MotDePasse123';

    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly NumberGenerator $numberGenerator,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $artisan = (new User())
            ->setEmail(self::EMAIL_ARTISAN)
            ->setNomComplet('CJA Batiment')
            ->setRoles(['ROLE_ADMIN']);
        $artisan->setPassword($this->passwordHasher->hashPassword($artisan, self::MOT_DE_PASSE_ARTISAN));
        $manager->persist($artisan);

        foreach ($this->fournisseurs() as $donnees) {
            $fournisseur = (new Fournisseur())
                ->setNom($donnees['nom'])
                ->setTelephone($donnees['telephone'])
                ->setSiteWeb($donnees['siteWeb']);
            $fournisseur->getAdresse()
                ->setLigne1($donnees['adresse'])
                ->setCodePostal($donnees['codePostal'])
                ->setVille($donnees['ville']);

            $manager->persist($fournisseur);
        }

        // Le generateur exige une transaction : les numeros et les fiches sont
        // ainsi commites ensemble, comme en production.
        $manager->getConnection()->beginTransaction();

        try {
            foreach ($this->clients() as $donnees) {
                $client = (new Client())
                    ->setNumeroClient($this->numberGenerator->numeroClient())
                    ->setTypologie($donnees['typologie'])
                    ->setCivilite($donnees['civilite'])
                    ->setNom($donnees['nom'])
                    ->setPrenom($donnees['prenom'])
                    ->setRaisonSociale($donnees['raisonSociale'])
                    ->setTelephone($donnees['telephone'])
                    ->setEmail($donnees['email'])
                    ->setSiret($donnees['siret'])
                    ->setNumeroTvaIntracom($donnees['tva'])
                    ->setNotes($donnees['notes']);

                $client->getAdresseFacturation()
                    ->setLigne1($donnees['adresse'])
                    ->setCodePostal($donnees['codePostal'])
                    ->setVille($donnees['ville']);

                foreach ($donnees['chantiers'] as $donneesChantier) {
                    $chantier = new Chantier();
                    $chantier->setLibelle($donneesChantier['libelle']);
                    $chantier->getAdresse()
                        ->setLigne1($donneesChantier['adresse'])
                        ->setCodePostal($donneesChantier['codePostal'])
                        ->setVille($donneesChantier['ville']);

                    $client->addChantier($chantier);
                    $manager->persist($chantier);
                }

                $manager->persist($client);
            }

            $manager->flush();
            $manager->getConnection()->commit();
        } catch (\Throwable $exception) {
            $manager->getConnection()->rollBack();

            throw $exception;
        }
    }

    /**
     * @return list<array{nom: string, telephone: string, siteWeb: string, adresse: string, codePostal: string, ville: string}>
     */
    private function fournisseurs(): array
    {
        return [
            ['nom' => 'BigMat Toulouse', 'telephone' => '0561501020', 'siteWeb' => 'https://www.bigmat.fr', 'adresse' => '120 route de Bayonne', 'codePostal' => '31300', 'ville' => 'Toulouse'],
            ['nom' => 'Point.P Toulouse Nord', 'telephone' => '0561471122', 'siteWeb' => 'https://www.pointp.fr', 'adresse' => '45 avenue des Etats-Unis', 'codePostal' => '31200', 'ville' => 'Toulouse'],
            ['nom' => 'Leroy Merlin Portet', 'telephone' => '0562203040', 'siteWeb' => 'https://www.leroymerlin.fr', 'adresse' => "Zone de l'Oncopole", 'codePostal' => '31120', 'ville' => 'Portet-sur-Garonne'],
            ['nom' => 'Gedimat Blagnac', 'telephone' => '0561714050', 'siteWeb' => 'https://www.gedimat.fr', 'adresse' => '8 rue du Pont Neuf', 'codePostal' => '31700', 'ville' => 'Blagnac'],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function clients(): array
    {
        return [
            [
                'typologie' => TypologieClient::PARTICULIER,
                'civilite' => 'M.',
                'nom' => 'Dubois',
                'prenom' => 'Marc',
                'raisonSociale' => null,
                'telephone' => '0611223344',
                'email' => 'marc.dubois@example.com',
                'siret' => null,
                'tva' => null,
                'notes' => 'Client fidele, chantiers recurrents.',
                'adresse' => '14 rue des Peupliers',
                'codePostal' => '31400',
                'ville' => 'Toulouse',
                'chantiers' => [
                    ['libelle' => 'Renovation salle de bain', 'adresse' => '14 rue des Peupliers', 'codePostal' => '31400', 'ville' => 'Toulouse'],
                    ['libelle' => 'Appartement locatif', 'adresse' => '3 rue Saint-Rome', 'codePostal' => '31000', 'ville' => 'Toulouse'],
                ],
            ],
            [
                'typologie' => TypologieClient::PARTICULIER,
                'civilite' => 'Mme',
                'nom' => 'Lefevre',
                'prenom' => 'Sophie',
                'raisonSociale' => null,
                'telephone' => '0622334455',
                'email' => 'sophie.lefevre@example.com',
                'siret' => null,
                'tva' => null,
                'notes' => null,
                'adresse' => '8 impasse du Moulin',
                'codePostal' => '31170',
                'ville' => 'Tournefeuille',
                'chantiers' => [
                    ['libelle' => 'Isolation des combles', 'adresse' => '8 impasse du Moulin', 'codePostal' => '31170', 'ville' => 'Tournefeuille'],
                ],
            ],
            [
                'typologie' => TypologieClient::PROFESSIONNEL,
                'civilite' => null,
                'nom' => null,
                'prenom' => null,
                'raisonSociale' => 'SCI Les Tilleuls',
                'telephone' => '0561223344',
                'email' => 'contact@scitilleuls.test',
                'siret' => '73282932000074',
                'tva' => 'FR40303265045',
                'notes' => 'Reglement a 30 jours fin de mois.',
                'adresse' => '25 avenue Jean Jaures',
                'codePostal' => '31000',
                'ville' => 'Toulouse',
                'chantiers' => [
                    ['libelle' => 'Ravalement facade immeuble A', 'adresse' => '25 avenue Jean Jaures', 'codePostal' => '31000', 'ville' => 'Toulouse'],
                    ['libelle' => 'Cage escalier immeuble B', 'adresse' => '27 avenue Jean Jaures', 'codePostal' => '31000', 'ville' => 'Toulouse'],
                    ['libelle' => 'Parking souterrain', 'adresse' => '25 avenue Jean Jaures', 'codePostal' => '31000', 'ville' => 'Toulouse'],
                ],
            ],
            [
                'typologie' => TypologieClient::PROFESSIONNEL,
                'civilite' => null,
                'nom' => null,
                'prenom' => null,
                'raisonSociale' => 'Boulangerie Martin SARL',
                'telephone' => '0561998877',
                'email' => 'gerant@boulangerie-martin.test',
                'siret' => '44306184100047',
                'tva' => null,
                'notes' => null,
                'adresse' => '3 place du Capitole',
                'codePostal' => '31000',
                'ville' => 'Toulouse',
                'chantiers' => [
                    ['libelle' => 'Amenagement du fournil', 'adresse' => '3 place du Capitole', 'codePostal' => '31000', 'ville' => 'Toulouse'],
                ],
            ],
            [
                'typologie' => TypologieClient::PARTICULIER,
                'civilite' => 'M.',
                'nom' => 'Garnier',
                'prenom' => 'Philippe',
                'raisonSociale' => null,
                'telephone' => '0644556677',
                'email' => 'p.garnier@example.com',
                'siret' => null,
                'tva' => null,
                'notes' => 'Devis terrasse a relancer.',
                'adresse' => '42 chemin de la Garonne',
                'codePostal' => '31120',
                'ville' => 'Portet-sur-Garonne',
                'chantiers' => [
                    ['libelle' => 'Terrasse bois 35 m2', 'adresse' => '42 chemin de la Garonne', 'codePostal' => '31120', 'ville' => 'Portet-sur-Garonne'],
                ],
            ],
        ];
    }
}
