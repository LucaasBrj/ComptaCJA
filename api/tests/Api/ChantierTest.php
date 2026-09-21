<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Tests\ApiTestCase;

final class ChantierTest extends ApiTestCase
{
    public function testUnChantierEstRattacheAUnClientEtFiltrable(): void
    {
        $client = $this->clientAuthentifie();

        $iriClient = $client->request('POST', '/api/clients', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => ['typologie' => 'PARTICULIER', 'nom' => 'Dupont'],
        ])->toArray()['@id'];

        $client->request('POST', '/api/chantiers', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'libelle' => 'Terrasse bois',
                'client' => $iriClient,
                'adresse' => ['ligne1' => '5 rue du Port', 'codePostal' => '31200', 'ville' => 'Toulouse'],
            ],
        ]);

        self::assertResponseStatusCodeSame(201);
        self::assertJsonContains(['libelleComplet' => 'Terrasse bois (Toulouse)']);

        $reponse = $client->request('GET', '/api/chantiers?client='.$iriClient);
        self::assertSame(1, $reponse->toArray()['totalItems']);
    }

    public function testUnChantierSansClientEstRejete(): void
    {
        $this->clientAuthentifie()->request('POST', '/api/chantiers', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => ['libelle' => 'Chantier orphelin'],
        ]);

        self::assertResponseStatusCodeSame(422);
    }

    public function testLaSuppressionDUnClientSupprimeSesChantiers(): void
    {
        $client = $this->clientAuthentifie();

        $iriClient = $client->request('POST', '/api/clients', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'typologie' => 'PARTICULIER',
                'nom' => 'Dupont',
                'chantiers' => [['libelle' => 'Salle de bain']],
            ],
        ])->toArray()['@id'];

        $client->request('DELETE', $iriClient);
        self::assertResponseStatusCodeSame(204);

        $reponse = $client->request('GET', '/api/chantiers');
        self::assertSame(0, $reponse->toArray()['totalItems']);
    }
}
