<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Tests\ApiTestCase;

final class ClientTest extends ApiTestCase
{
    public function testLaCollectionEstRefuseeSansJeton(): void
    {
        static::createClient()->request('GET', '/api/clients');

        self::assertResponseStatusCodeSame(401);
    }

    public function testLaCreationAttribueUnNumeroSequentiel(): void
    {
        $client = $this->clientAuthentifie();

        foreach (['Dupont', 'Martin', 'Bernard'] as $rang => $nom) {
            $reponse = $client->request('POST', '/api/clients', [
                'headers' => ['Content-Type' => 'application/ld+json'],
                'json' => ['typologie' => 'PARTICULIER', 'nom' => $nom],
            ]);

            self::assertResponseStatusCodeSame(201);
            self::assertSame(\sprintf('CLI-%04d', $rang + 1), $reponse->toArray()['numeroClient']);
        }
    }

    public function testUnProfessionnelSansSiretEstRejete(): void
    {
        $this->clientAuthentifie()->request('POST', '/api/clients', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => ['typologie' => 'PROFESSIONNEL', 'raisonSociale' => 'BTP Sud SARL'],
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('SIRET est obligatoire', (string) self::getClient()->getResponse()->getContent(false));
    }

    public function testUnSiretNeRespectantPasLaCleDeLuhnEstRejete(): void
    {
        $this->clientAuthentifie()->request('POST', '/api/clients', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'typologie' => 'PROFESSIONNEL',
                'raisonSociale' => 'BTP Sud SARL',
                'siret' => '12345678901234',
            ],
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('Luhn', (string) self::getClient()->getResponse()->getContent(false));
    }

    public function testUnProfessionnelAvecSiretValideEstAccepte(): void
    {
        $reponse = $this->clientAuthentifie()->request('POST', '/api/clients', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'typologie' => 'PROFESSIONNEL',
                'raisonSociale' => 'BTP Sud SARL',
                'siret' => '73282932000074',
                'numeroTvaIntracom' => 'FR40303265045',
            ],
        ]);

        self::assertResponseStatusCodeSame(201);
        self::assertSame('BTP Sud SARL', $reponse->toArray()['nomAffichage']);
    }

    public function testLesChantiersImbriquesSontCreesAvecLeClient(): void
    {
        $reponse = $this->clientAuthentifie()->request('POST', '/api/clients', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'typologie' => 'PARTICULIER',
                'nom' => 'Dupont',
                'chantiers' => [
                    ['libelle' => 'Salle de bain', 'adresse' => ['codePostal' => '31000', 'ville' => 'Toulouse']],
                    ['libelle' => 'Terrasse', 'adresse' => ['codePostal' => '31200', 'ville' => 'Toulouse']],
                ],
            ],
        ]);

        self::assertResponseStatusCodeSame(201);
        $donnees = $reponse->toArray();
        self::assertCount(2, $donnees['chantiers']);
        self::assertSame('Salle de bain (Toulouse)', $donnees['chantiers'][0]['libelleComplet']);
    }

    public function testLaRechercheGlobaleEstInsensibleALaCasse(): void
    {
        $client = $this->clientAuthentifie();
        $client->request('POST', '/api/clients', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'typologie' => 'PARTICULIER',
                'nom' => 'Dupont',
                'prenom' => 'Jean',
                'email' => 'jean.dupont@example.com',
                'adresseFacturation' => ['codePostal' => '31000', 'ville' => 'Toulouse'],
            ],
        ]);

        foreach (['DUPONT', 'dupont', 'CLI-0001', 'jean.dupont'] as $terme) {
            $reponse = $client->request('GET', '/api/clients?recherche='.urlencode($terme));
            self::assertSame(1, $reponse->toArray()['totalItems'], \sprintf('Le terme "%s" doit ramener le client.', $terme));
        }

        $reponse = $client->request('GET', '/api/clients?recherche=introuvable');
        self::assertSame(0, $reponse->toArray()['totalItems']);
    }

    public function testLeFiltreSurLaCommuneEstInsensibleALaCasse(): void
    {
        $client = $this->clientAuthentifie();
        $client->request('POST', '/api/clients', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'typologie' => 'PARTICULIER',
                'nom' => 'Dupont',
                'adresseFacturation' => ['codePostal' => '31000', 'ville' => 'Toulouse'],
            ],
        ]);

        foreach (['toulouse', 'TOULOUSE', 'toul'] as $terme) {
            $reponse = $client->request('GET', '/api/clients?ville='.$terme);
            self::assertSame(1, $reponse->toArray()['totalItems'], \sprintf('La commune "%s" doit ramener le client.', $terme));
        }

        $reponse = $client->request('GET', '/api/clients?ville=paris');
        self::assertSame(0, $reponse->toArray()['totalItems']);
    }
}
