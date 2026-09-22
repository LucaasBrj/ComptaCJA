<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\Document;
use App\Enum\StatutDocument;
use App\Enum\TypeDocument;
use App\Tests\ApiTestCase;
use Symfony\Component\Uid\Uuid;

final class SuiviTest extends ApiTestCase
{
    public function testLaRechercheRetrouveClientDocumentLigneEtCommune(): void
    {
        $http = $this->clientAuthentifie();
        $client = $http->request('POST', '/api/clients', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => ['typologie' => 'PARTICULIER', 'nom' => 'Martin', 'prenom' => 'Lea'],
        ])->toArray();
        self::assertResponseStatusCodeSame(201);

        $http->request('POST', '/api/chantiers', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'libelle' => 'Extension',
                'client' => $client['@id'],
                'adresse' => ['ligne1' => '2 rue des Pins', 'codePostal' => '31700', 'ville' => 'Blagnac'],
            ],
        ]);
        self::assertResponseStatusCodeSame(201);

        $devis = $http->request('POST', '/api/documents', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'type' => 'DEVIS',
                'dateEmission' => '2026-09-15',
                'client' => $client['@id'],
                'lignes' => [[
                    'type' => 'PRESTATION',
                    'libelle' => 'Carrelage mural unique',
                    'unite' => 'M2',
                    'quantite' => '1',
                    'prixUnitaireHt' => '80.00',
                    'tauxTva' => '2',
                ]],
            ],
        ])->toArray();
        self::assertResponseStatusCodeSame(201);

        $parNom = $http->request('GET', '/api/recherche?q=Martin')->toArray();
        self::assertSame('Martin Lea', $parNom['clients'][0]['libelle']);

        $parNumero = $http->request('GET', '/api/recherche?q='.$devis['numero'])->toArray();
        self::assertSame($devis['numero'], $parNumero['documents'][0]['libelle']);

        $parLigne = $http->request('GET', '/api/recherche?q=Carrelage')->toArray();
        self::assertSame($devis['numero'], $parLigne['documents'][0]['libelle']);

        $parVille = $http->request('GET', '/api/recherche?q=Blagnac')->toArray();
        self::assertSame($client['@id'], $parVille['chantiers'][0]['iri']);
        self::assertStringContainsString('Martin', $parVille['chantiers'][0]['sousTitre']);

        $court = $http->request('GET', '/api/recherche?q=M')->toArray();
        self::assertSame([], $court['clients']);
    }

    public function testDupliquerCreeUnNouveauDevisSansModifierLOriginal(): void
    {
        $http = $this->clientAuthentifie();
        $client = $this->creerClient($http);
        $devis = $http->request('POST', '/api/documents', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'type' => 'DEVIS',
                'dateEmission' => '2026-09-15',
                'objet' => 'Salle de bain',
                'tauxAcompte' => '30.00',
                'client' => $client,
                'lignes' => [
                    ['type' => 'TEXTE', 'libelle' => 'Preparation'],
                    [
                        'type' => 'PRESTATION',
                        'libelle' => 'Pose',
                        'unite' => 'M2',
                        'quantite' => '2',
                        'prixUnitaireHt' => '40.00',
                        'tauxTva' => '2',
                    ],
                ],
            ],
        ])->toArray();
        self::assertResponseStatusCodeSame(201);

        $copie = $http->request('POST', '/api/documents/'.$devis['id'].'/dupliquer')->toArray();
        self::assertResponseStatusCodeSame(201);
        self::assertSame('DEVIS', $copie['type']);
        self::assertSame('BROUILLON', $copie['statut']);
        self::assertFalse($copie['verrouille']);
        self::assertNotSame($devis['numero'], $copie['numero']);
        self::assertMatchesRegularExpression('/^DV\d{4}-\d{2}-\d{3}$/', $copie['numero']);
        self::assertSame('Salle de bain', $copie['objet']);
        self::assertCount(2, $copie['lignes']);
        self::assertSame('88.00', $copie['montantTtc']);

        $original = $http->request('GET', '/api/documents/'.$devis['id'])->toArray();
        self::assertSame($devis['numero'], $original['numero']);
        self::assertSame('BROUILLON', $original['statut']);

        $facture = $http->request('POST', '/api/documents', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'type' => 'FACTURE',
                'dateEmission' => '2026-09-15',
                'client' => $client,
                'lignes' => [[
                    'type' => 'PRESTATION',
                    'libelle' => 'Pose',
                    'unite' => 'U',
                    'quantite' => '1',
                    'prixUnitaireHt' => '10.00',
                    'tauxTva' => '3',
                ]],
            ],
        ])->toArray();
        $http->request('POST', '/api/documents/'.$facture['id'].'/dupliquer');
        self::assertResponseStatusCodeSame(422);
    }

    public function testLExportIgnoreLesBrouillonsEtLeTableauCompteLeRetard(): void
    {
        $http = $this->clientAuthentifie();
        $client = $this->creerClient($http);

        $brouillon = $this->creerFacture($http, $client, '2026-09-15');
        $envoyee = $this->creerFacture($http, $client, '2026-09-15');
        $http->request('PATCH', '/api/documents/'.$envoyee['id'], [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json' => ['statut' => 'ENVOYE'],
        ]);
        self::assertResponseIsSuccessful();

        $csv = $http->request('GET', '/api/exports/comptable?du=2026-09-01&au=2026-09-30')->getContent();
        self::assertResponseHeaderSame('content-type', 'text/csv; charset=utf-8');
        self::assertStringContainsString($envoyee['numero'], $csv);
        self::assertStringNotContainsString($brouillon['numero'], $csv);
        self::assertStringContainsString('tva_10', $csv);

        $devis = $http->request('POST', '/api/documents', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'type' => 'DEVIS',
                'dateEmission' => '2026-09-15',
                'dateEcheance' => '2020-01-01',
                'client' => $client,
                'lignes' => [[
                    'type' => 'PRESTATION',
                    'libelle' => 'Pose',
                    'unite' => 'U',
                    'quantite' => '1',
                    'prixUnitaireHt' => '50.00',
                    'tauxTva' => '2',
                ]],
            ],
        ])->toArray();
        $http->request('PATCH', '/api/documents/'.$devis['id'], [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json' => ['statut' => 'ENVOYE'],
        ]);
        self::assertResponseIsSuccessful();

        $tableau = $http->request('GET', '/api/tableau-de-bord')->toArray();
        self::assertGreaterThanOrEqual(1, $tableau['enRetard']['nombre']);
        self::assertGreaterThanOrEqual(1, $tableau['enAttente']['nombre']);
        self::assertSame(0, $tableau['anomalies']);

        $retard = $http->request('GET', '/api/documents?enRetard=1')->toArray();
        $numeros = array_column($retard['member'], 'numero');
        self::assertContains($devis['numero'], $numeros);

        $anomalie = $this->entityManager->find(Document::class, Uuid::fromString($brouillon['id']));
        self::assertInstanceOf(Document::class, $anomalie);
        $anomalie->setStatut(StatutDocument::ENVOYE)->setVerrouille(false)->setType(TypeDocument::FACTURE);
        $this->entityManager->flush();

        $controle = $http->request('GET', '/api/tableau-de-bord')->toArray();
        self::assertSame(1, $controle['anomalies']);
    }

    /**
     * @return array<string, mixed>
     */
    private function creerFacture(mixed $http, string $client, string $date): array
    {
        $reponse = $http->request('POST', '/api/documents', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'type' => 'FACTURE',
                'dateEmission' => $date,
                'client' => $client,
                'lignes' => [[
                    'type' => 'PRESTATION',
                    'libelle' => 'Pose',
                    'unite' => 'M2',
                    'quantite' => '1',
                    'prixUnitaireHt' => '100.00',
                    'tauxTva' => '2',
                ]],
            ],
        ]);
        self::assertResponseStatusCodeSame(201);

        return $reponse->toArray();
    }

    private function creerClient(mixed $http): string
    {
        $reponse = $http->request('POST', '/api/clients', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => ['typologie' => 'PARTICULIER', 'nom' => 'Dupont'],
        ]);
        self::assertResponseStatusCodeSame(201);

        return $reponse->toArray()['@id'];
    }
}
