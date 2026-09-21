<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Tests\ApiTestCase;

final class AuthentificationTest extends ApiTestCase
{
    public function testDesIdentifiantsValidesRetournentUnJeton(): void
    {
        $reponse = static::createClient()->request('POST', '/api/login_check', [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => ['email' => self::EMAIL_UTILISATEUR, 'password' => self::MOT_DE_PASSE_UTILISATEUR],
        ]);

        self::assertResponseIsSuccessful();
        self::assertNotEmpty($reponse->toArray()['token']);
    }

    public function testUnMotDePasseErroneEstRefuse(): void
    {
        static::createClient()->request('POST', '/api/login_check', [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => ['email' => self::EMAIL_UTILISATEUR, 'password' => 'mauvais'],
        ]);

        self::assertResponseStatusCodeSame(401);
    }

    /**
     * Le frontend appelle /api/me au demarrage pour afficher le nom de l'artisan :
     * l'endpoint doit resoudre l'utilisateur porte par le jeton, pas renvoyer null.
     */
    public function testMeDecritLUtilisateurPorteParLeJeton(): void
    {
        $this->clientAuthentifie()->request('GET', '/api/me');

        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            'email' => self::EMAIL_UTILISATEUR,
            'nomComplet' => 'Artisan CJA',
            'roles' => ['ROLE_USER'],
        ]);
    }

    public function testMeEstFermeAuxRequetesAnonymes(): void
    {
        static::createClient()->request('GET', '/api/me');

        self::assertResponseStatusCodeSame(401);
    }
}
