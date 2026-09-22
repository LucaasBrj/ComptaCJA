<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\Document;
use App\Enum\StatutDocument;
use App\Enum\TypeDocument;
use App\Service\RenduDocumentHtml;
use App\Tests\ApiTestCase;
use Symfony\Component\Uid\Uuid;

final class DocumentTest extends ApiTestCase
{
    public function testLaCreationNumeroteDevisPuisFacture(): void
    {
        $http = $this->clientAuthentifie();
        $client = $this->creerClient($http);

        $devis = $this->creerPiece($http, $client, 'DEVIS', '2026-09-15');
        self::assertResponseStatusCodeSame(201);
        self::assertSame('DV2026-09-001', $devis['numero']);

        $second = $this->creerPiece($http, $client, 'DEVIS', '2026-09-20');
        self::assertSame('DV2026-09-002', $second['numero']);

        $facture = $this->creerPiece($http, $client, 'FACTURE', '2026-09-15');
        self::assertSame('FC2026-09-001', $facture['numero']);
    }

    public function testLesTotauxDuClientSontEcrasesParLeServeur(): void
    {
        $http = $this->clientAuthentifie();
        $reponse = $http->request('POST', '/api/documents', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'type' => 'DEVIS',
                'dateEmission' => '2026-09-15',
                'client' => $this->creerClient($http),
                'montantTtc' => '1.00',
                'lignes' => [
                    ['type' => 'TEXTE', 'libelle' => "Salle de bain\nDepose de l'existant"],
                    [
                        'type' => 'PRESTATION',
                        'libelle' => 'Carrelage mural',
                        'unite' => 'M2',
                        'quantite' => '2',
                        'prixUnitaireHt' => '10.00',
                        'tauxTva' => '3',
                    ],
                ],
            ],
        ]);

        self::assertResponseStatusCodeSame(201);
        $donnees = $reponse->toArray();
        self::assertSame('20.00', $donnees['montantHt']);
        self::assertSame('4.00', $donnees['montantTva']);
        self::assertSame('24.00', $donnees['montantTtc']);
        self::assertSame('0.00', $donnees['lignes'][0]['montantHt']);
        self::assertSame('20.00', $donnees['lignes'][1]['montantHt']);
        self::assertSame('4.00', $donnees['lignes'][1]['montantTva']);
    }

    public function testUnePrestationSansQuantiteEstRejetee(): void
    {
        $http = $this->clientAuthentifie();
        $http->request('POST', '/api/documents', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'type' => 'DEVIS',
                'dateEmission' => '2026-09-15',
                'client' => $this->creerClient($http),
                'lignes' => [[
                    'type' => 'PRESTATION',
                    'libelle' => 'Pose',
                    'unite' => 'U',
                    'prixUnitaireHt' => '10.00',
                    'tauxTva' => '2',
                ]],
            ],
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('quantite', (string) $http->getResponse()->getContent(false));
    }

    public function testUnTypeReserveAuLot3EstRejete(): void
    {
        $http = $this->clientAuthentifie();
        $http->request('POST', '/api/documents', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'type' => 'FACTURE_ACOMPTE',
                'dateEmission' => '2026-09-15',
                'client' => $this->creerClient($http),
            ],
        ]);

        self::assertResponseStatusCodeSame(422);
    }

    public function testUnDocumentEnvoyeNePeutPlusEtreModifie(): void
    {
        $http = $this->clientAuthentifie();
        $cree = $this->creerPiece($http, $this->creerClient($http), 'DEVIS', '2026-09-15');

        $http->request('PATCH', '/api/documents/'.$cree['id'], [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json' => ['statut' => 'ENVOYE'],
        ]);
        self::assertResponseIsSuccessful();
        self::assertTrue($http->getResponse()->toArray()['verrouille']);

        $http->request('PATCH', '/api/documents/'.$cree['id'], [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json' => ['objet' => 'Chantier modifie apres envoi'],
        ]);
        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('fige', (string) $http->getResponse()->getContent(false));

        $http->request('PATCH', '/api/documents/'.$cree['id'], [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json' => ['statut' => 'ACCEPTE'],
        ]);
        self::assertResponseIsSuccessful();
        self::assertSame('ACCEPTE', $http->getResponse()->toArray()['statut']);
    }

    public function testUnDocumentLegacyResteEnLectureSeule(): void
    {
        $http = $this->clientAuthentifie();
        $iriClient = $this->creerClient($http);
        $client = $this->entityManager->find(
            \App\Entity\Client::class,
            Uuid::fromString(basename($iriClient)),
        );

        $legacy = (new Document())
            ->setNumero('FC2020-01-001')
            ->setType(TypeDocument::FACTURE)
            ->setStatut(StatutDocument::PAYE)
            ->setDateEmission(new \DateTimeImmutable('2020-01-10'))
            ->setMontantHt('100.00')
            ->setMontantTva('10.00')
            ->setMontantTtc('110.00')
            ->setClient($client)
            ->setLegacy(true)
            ->setVerrouille(true);
        $this->entityManager->persist($legacy);
        $this->entityManager->flush();

        $http->request('PATCH', '/api/documents/'.$legacy->getId(), [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json' => ['objet' => 'Correction interdite'],
        ]);
        self::assertResponseStatusCodeSame(422);

        $http->request('GET', '/api/documents/'.$legacy->getId().'/pdf');
        self::assertResponseStatusCodeSame(422);
    }

    public function testLaMention293bDependDuRegimeEtDuTauxZero(): void
    {
        $http = $this->clientAuthentifie();
        $http->request('PATCH', '/api/entreprise', [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json' => ['regimeTva' => 'FRANCHISE_293B', 'raisonSociale' => 'CJA Batiment'],
        ]);
        self::assertResponseIsSuccessful();

        $devis = $this->creerPiece($http, $this->creerClient($http), 'DEVIS', '2026-09-15', '0');
        $html = $this->rendre($devis['id']);
        self::assertStringContainsString('TVA non applicable, art. 293 B du CGI', $html);
        self::assertStringContainsString('Assurance', $html);
        self::assertStringContainsString('BROUILLON', $html);

        $bascule = $http->request('PATCH', '/api/entreprise', [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json' => ['regimeTva' => 'ASSUJETTI'],
        ]);
        self::assertResponseIsSuccessful();
        self::assertSame('ASSUJETTI', $bascule->toArray()['regimeTva']);

        $this->entityManager->clear();
        self::assertSame(
            'ASSUJETTI',
            $this->entityManager->getConnection()->fetchOne('SELECT regime_tva FROM entreprise'),
        );

        $htmlAssujetti = $this->rendre($devis['id']);
        self::assertStringNotContainsString('TVA non applicable, art. 293 B du CGI', $htmlAssujetti);
    }

    public function testLePdfEstRenvoyeParLeControleur(): void
    {
        $http = $this->clientAuthentifie();
        $devis = $this->creerPiece($http, $this->creerClient($http), 'DEVIS', '2026-09-15');

        $reponse = $http->request('GET', '/api/documents/'.$devis['id'].'/pdf', [
            'headers' => ['Accept' => 'application/pdf'],
        ]);

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'application/pdf');
        self::assertStringStartsWith('%PDF', $reponse->getContent());
    }

    /**
     * @return array<string, mixed>
     */
    private function creerPiece(mixed $http, string $client, string $type, string $date, string $taux = '2'): array
    {
        $reponse = $http->request('POST', '/api/documents', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'type' => $type,
                'dateEmission' => $date,
                'objet' => 'Renovation',
                'client' => $client,
                'lignes' => [[
                    'type' => 'PRESTATION',
                    'libelle' => 'Pose',
                    'unite' => 'M2',
                    'quantite' => '1',
                    'prixUnitaireHt' => '100.00',
                    'tauxTva' => $taux,
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

    private function rendre(string $id): string
    {
        $this->entityManager->clear();
        $document = $this->entityManager->find(Document::class, Uuid::fromString($id));

        return static::getContainer()->get(RenduDocumentHtml::class)->rendre($document);
    }
}
