<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Tests\ApiTestCase;

final class FournisseurTest extends ApiTestCase
{
    public function testLaCollectionEstRefuseeSansJeton(): void
    {
        static::createClient()->request('GET', '/api/fournisseurs');

        self::assertResponseStatusCodeSame(401);
    }

    public function testLeCycleDeVieComplet(): void
    {
        $client = $this->clientAuthentifie();

        $creation = $client->request('POST', '/api/fournisseurs', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'nom' => 'BigMat Toulouse',
                'telephone' => '0561501020',
                'siteWeb' => 'https://www.bigmat.fr',
                'adresse' => ['ligne1' => '120 route de Bayonne', 'codePostal' => '31300', 'ville' => 'Toulouse'],
            ],
        ]);

        self::assertResponseStatusCodeSame(201);
        $iri = $creation->toArray()['@id'];

        $client->request('PATCH', $iri, [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json' => ['contactNom' => 'Sylvie Roche'],
        ]);
        self::assertResponseIsSuccessful();
        self::assertJsonContains(['contactNom' => 'Sylvie Roche']);

        $client->request('DELETE', $iri);
        self::assertResponseStatusCodeSame(204);
    }

    public function testDeuxFournisseursNePeuventPasPorterLeMemeNom(): void
    {
        $client = $this->clientAuthentifie();
        $charge = [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => ['nom' => 'Point.P Toulouse Nord'],
        ];

        $client->request('POST', '/api/fournisseurs', $charge);
        self::assertResponseStatusCodeSame(201);

        $client->request('POST', '/api/fournisseurs', $charge);
        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('porte deja ce nom', (string) self::getClient()->getResponse()->getContent(false));
    }

    /**
     * L'artisan saisit "point" ou "POINT" sans y penser : la recherche doit repondre
     * dans les deux cas, y compris sur la commune de l'adresse.
     */
    public function testLaRechercheEstInsensibleALaCasse(): void
    {
        $client = $this->clientAuthentifie();

        $client->request('POST', '/api/fournisseurs', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'nom' => 'Point.P Toulouse Nord',
                'adresse' => ['ligne1' => '45 avenue des Etats-Unis', 'codePostal' => '31200', 'ville' => 'Toulouse'],
            ],
        ]);
        self::assertResponseStatusCodeSame(201);

        $client->request('GET', '/api/fournisseurs?nom=POINT');
        self::assertResponseIsSuccessful();
        self::assertJsonContains(['totalItems' => 1]);

        $client->request('GET', '/api/fournisseurs?ville=toulouse');
        self::assertResponseIsSuccessful();
        self::assertJsonContains(['totalItems' => 1]);
    }

    public function testUneUrlInvalideEstRejetee(): void
    {
        $this->clientAuthentifie()->request('POST', '/api/fournisseurs', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => ['nom' => 'Gedimat Blagnac', 'siteWeb' => 'pas-une-url'],
        ]);

        self::assertResponseStatusCodeSame(422);
    }
}
