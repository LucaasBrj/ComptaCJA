<?php

declare(strict_types=1);

namespace App\Tests;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase as BaseApiTestCase;
use ApiPlatform\Symfony\Bundle\Test\Client;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Socle des tests d'API : repart d'un schema vierge et fournit un client
 * porteur d'un JWT valide.
 */
abstract class ApiTestCase extends BaseApiTestCase
{
    protected const EMAIL_UTILISATEUR = 'artisan@cja.test';
    protected const MOT_DE_PASSE_UTILISATEUR = 'MotDePasse123';

    /**
     * setUp() amorce deja le noyau et prepare le schema : createClient() doit le
     * reutiliser plutot que d'en amorcer un second (comportement par defaut en 5.0).
     */
    protected static ?bool $alwaysBootKernel = false;

    protected EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        parent::setUp();

        self::bootKernel();
        $conteneur = static::getContainer();
        $this->entityManager = $conteneur->get(EntityManagerInterface::class);

        $outilSchema = new SchemaTool($this->entityManager);
        $metadonnees = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $outilSchema->dropSchema($metadonnees);
        $outilSchema->createSchema($metadonnees);

        $utilisateur = (new User())->setEmail(self::EMAIL_UTILISATEUR)->setNomComplet('Artisan CJA');
        $utilisateur->setPassword(
            $conteneur->get(UserPasswordHasherInterface::class)->hashPassword($utilisateur, self::MOT_DE_PASSE_UTILISATEUR),
        );

        $this->entityManager->persist($utilisateur);
        $this->entityManager->flush();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // Evite que l'EntityManager d'un test ne fuite sur le suivant.
        unset($this->entityManager);
    }

    protected function clientAuthentifie(): Client
    {
        $client = static::createClient();

        $reponse = $client->request('POST', '/api/login_check', [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => ['email' => self::EMAIL_UTILISATEUR, 'password' => self::MOT_DE_PASSE_UTILISATEUR],
        ]);

        self::assertResponseIsSuccessful();

        $client->setDefaultOptions([
            'headers' => ['Authorization' => 'Bearer '.$reponse->toArray()['token']],
        ]);

        return $client;
    }
}
