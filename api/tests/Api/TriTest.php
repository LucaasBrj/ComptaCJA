<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Tests\ApiTestCase;

final class TriTest extends ApiTestCase
{
    public function testLesDocumentsSeTrientParClientEtParStatut(): void
    {
        $http = $this->clientAuthentifie();
        $zoe = $this->creerClient($http, 'Zoe');
        $alice = $this->creerClient($http, 'Alice');
        $this->creerDevis($http, $zoe);
        $devisAlice = $this->creerDevis($http, $alice);

        $http->request('PATCH', '/api/documents/'.$devisAlice['id'], [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json' => ['statut' => 'ENVOYE'],
        ]);
        self::assertResponseIsSuccessful();

        $parClient = $http->request('GET', '/api/documents?order[client.nom]=asc')->toArray();
        self::assertResponseIsSuccessful();
        $noms = array_map(static fn (array $document): string => $document['client']['nomAffichage'], $parClient['member']);
        self::assertSame(['Alice Lea', 'Zoe Lea'], array_slice($noms, 0, 2));

        $parStatut = $http->request('GET', '/api/documents?order[statut]=asc')->toArray();
        self::assertResponseIsSuccessful();
        $statuts = array_column($parStatut['member'], 'statut');
        self::assertSame(['BROUILLON', 'ENVOYE'], array_slice($statuts, 0, 2));
    }

    public function testLesFournisseursSeTrientParCommune(): void
    {
        $http = $this->clientAuthentifie();
        $this->creerFournisseur($http, 'Point.P Lyon', 'Lyon');
        $this->creerFournisseur($http, 'BigMat Blagnac', 'Blagnac');

        $page = $http->request('GET', '/api/fournisseurs?order[adresse.ville]=asc')->toArray();
        self::assertResponseIsSuccessful();
        $villes = array_map(static fn (array $fournisseur): string => $fournisseur['adresse']['ville'], $page['member']);
        self::assertSame(['Blagnac', 'Lyon'], array_slice($villes, 0, 2));
    }

    private function creerClient(mixed $http, string $nom): string
    {
        $client = $http->request('POST', '/api/clients', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => ['typologie' => 'PARTICULIER', 'nom' => $nom, 'prenom' => 'Lea'],
        ])->toArray();
        self::assertResponseStatusCodeSame(201);

        return $client['@id'];
    }

    /**
     * @return array<string, mixed>
     */
    private function creerDevis(mixed $http, string $client): array
    {
        $devis = $http->request('POST', '/api/documents', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'type' => 'DEVIS',
                'dateEmission' => '2026-09-15',
                'client' => $client,
                'lignes' => [[
                    'type' => 'PRESTATION',
                    'libelle' => 'Pose',
                    'unite' => 'U',
                    'quantite' => '1',
                    'prixUnitaireHt' => '10.00',
                    'tauxTva' => '0',
                ]],
            ],
        ])->toArray();
        self::assertResponseStatusCodeSame(201);

        return $devis;
    }

    private function creerFournisseur(mixed $http, string $nom, string $ville): void
    {
        $http->request('POST', '/api/fournisseurs', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'nom' => $nom,
                'adresse' => ['ligne1' => '1 rue', 'codePostal' => '31000', 'ville' => $ville],
            ],
        ]);
        self::assertResponseStatusCodeSame(201);
    }
}
