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

    public function testLAcompteAuProrataPuisLeSolde(): void
    {
        $http = $this->clientAuthentifie();
        $devis = $http->request('POST', '/api/documents', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'type' => 'DEVIS',
                'dateEmission' => '2026-09-15',
                'tauxAcompte' => '30.00',
                'client' => $this->creerClient($http),
                'lignes' => [
                    [
                        'type' => 'PRESTATION',
                        'libelle' => 'Neuf',
                        'unite' => 'FORFAIT',
                        'quantite' => '1',
                        'prixUnitaireHt' => '100.00',
                        'tauxTva' => '3',
                    ],
                    [
                        'type' => 'PRESTATION',
                        'libelle' => 'Renovation',
                        'unite' => 'FORFAIT',
                        'quantite' => '1',
                        'prixUnitaireHt' => '100.00',
                        'tauxTva' => '2',
                    ],
                ],
            ],
        ])->toArray();
        self::assertResponseStatusCodeSame(201);
        self::assertSame('230.00', $devis['montantTtc']);

        $html = $this->rendre($devis['id']);
        self::assertStringContainsString('Acompte de 30 % à verser à la signature du devis : 69,00 €.', $html);

        $http->request('POST', '/api/documents/'.$devis['id'].'/facture-acompte');
        self::assertResponseStatusCodeSame(422);

        $http->request('PATCH', '/api/documents/'.$devis['id'], [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json' => ['statut' => 'ENVOYE'],
        ]);
        self::assertResponseIsSuccessful();

        $http->request('POST', '/api/documents/'.$devis['id'].'/facture-acompte');
        self::assertResponseStatusCodeSame(422);

        $http->request('PATCH', '/api/documents/'.$devis['id'], [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json' => ['statut' => 'ACCEPTE'],
        ]);
        self::assertResponseIsSuccessful();

        $acompte = $http->request('POST', '/api/documents/'.$devis['id'].'/facture-acompte')->toArray();
        self::assertResponseStatusCodeSame(201);
        self::assertSame('FACTURE_ACOMPTE', $acompte['type']);
        self::assertMatchesRegularExpression('/^FA\d{4}-\d{2}-\d{3}$/', $acompte['numero']);
        self::assertSame('69.00', $acompte['montantTtc']);
        self::assertCount(2, $acompte['lignes']);

        $http->request('POST', '/api/documents/'.$devis['id'].'/facture-acompte');
        self::assertResponseStatusCodeSame(422);

        $http->request('PATCH', '/api/documents/'.$acompte['id'], [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json' => ['statut' => 'ENVOYE'],
        ]);
        self::assertResponseIsSuccessful();

        $solde = $http->request('POST', '/api/documents/'.$devis['id'].'/facture-solde')->toArray();
        self::assertResponseStatusCodeSame(201);
        self::assertSame('FACTURE', $solde['type']);
        self::assertMatchesRegularExpression('/^FC\d{4}-\d{2}-\d{3}$/', $solde['numero']);
        self::assertSame('161.00', $solde['montantTtc']);
        self::assertStringStartsWith('Solde du devis', (string) $solde['objet']);

        $deductions = array_values(array_filter(
            $solde['lignes'],
            static fn (array $ligne): bool => 'DEDUCTION' === $ligne['type'],
        ));
        self::assertCount(2, $deductions);
        self::assertSame('-30.00', $deductions[0]['montantHt']);

        $http->request('POST', '/api/documents/'.$devis['id'].'/facture-solde');
        self::assertResponseStatusCodeSame(422);
    }

    public function testUnDevisSansAcompteNaffichePasLaMentionEtRefuseLaFactureDAcompte(): void
    {
        $http = $this->clientAuthentifie();
        $client = $this->creerClient($http);
        $devis = $http->request('POST', '/api/documents', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'type' => 'DEVIS',
                'dateEmission' => '2026-09-15',
                'tauxAcompte' => '0',
                'client' => $client,
                'lignes' => [[
                    'type' => 'PRESTATION',
                    'libelle' => 'Pose',
                    'unite' => 'FORFAIT',
                    'quantite' => '1',
                    'prixUnitaireHt' => '100.00',
                    'tauxTva' => '2',
                ]],
            ],
        ])->toArray();
        self::assertResponseStatusCodeSame(201);
        self::assertSame('0.00', $devis['tauxAcompte']);

        $vide = $http->request('POST', '/api/documents', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'type' => 'DEVIS',
                'dateEmission' => '2026-09-15',
                'tauxAcompte' => '',
                'client' => $client,
                'lignes' => [[
                    'type' => 'PRESTATION',
                    'libelle' => 'Pose',
                    'unite' => 'FORFAIT',
                    'quantite' => '1',
                    'prixUnitaireHt' => '100.00',
                    'tauxTva' => '2',
                ]],
            ],
        ])->toArray();
        self::assertResponseStatusCodeSame(201);
        self::assertSame('0.00', $vide['tauxAcompte']);

        $html = $this->rendre($devis['id']);
        self::assertStringNotContainsString('à verser à la signature', $html);

        $http->request('PATCH', '/api/documents/'.$devis['id'], [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json' => ['statut' => 'ENVOYE'],
        ]);
        self::assertResponseIsSuccessful();
        $http->request('PATCH', '/api/documents/'.$devis['id'], [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json' => ['statut' => 'ACCEPTE'],
        ]);
        self::assertResponseIsSuccessful();

        $http->request('POST', '/api/documents/'.$devis['id'].'/facture-acompte');
        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('aucun montant', (string) $http->getResponse()->getContent(false));

        foreach (['-1', '100.01'] as $taux) {
            $http->request('POST', '/api/documents', [
                'headers' => ['Content-Type' => 'application/ld+json'],
                'json' => [
                    'type' => 'DEVIS',
                    'dateEmission' => '2026-09-15',
                    'tauxAcompte' => $taux,
                    'client' => $client,
                    'lignes' => [[
                        'type' => 'PRESTATION',
                        'libelle' => 'Pose',
                        'unite' => 'FORFAIT',
                        'quantite' => '1',
                        'prixUnitaireHt' => '100.00',
                        'tauxTva' => '2',
                    ]],
                ],
            ]);
            self::assertResponseStatusCodeSame(422);
        }
    }

    public function testLAnnexeExigeUneSourceValideEtFigee(): void
    {
        $http = $this->clientAuthentifie();
        $client = $this->creerClient($http);
        $devis = $this->creerPiece($http, $client, 'DEVIS', '2026-09-15');

        $http->request('POST', '/api/documents', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'type' => 'ANNEXE_DEBOURS',
                'dateEmission' => '2026-09-15',
                'client' => $client,
            ],
        ]);
        self::assertResponseStatusCodeSame(422);

        $http->request('POST', '/api/documents', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'type' => 'FACTURE_ACOMPTE',
                'dateEmission' => '2026-09-15',
                'client' => $client,
                'documentSource' => $devis['@id'],
                'lignes' => [[
                    'type' => 'PRESTATION',
                    'libelle' => 'Acompte',
                    'unite' => 'FORFAIT',
                    'quantite' => '1',
                    'prixUnitaireHt' => '30.00',
                    'tauxTva' => '2',
                ]],
            ],
        ]);
        self::assertResponseStatusCodeSame(422);

        $annexe = $http->request('POST', '/api/documents', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'type' => 'ANNEXE_DEBOURS',
                'dateEmission' => '2026-09-15',
                'client' => $client,
                'documentSource' => $devis['@id'],
            ],
        ])->toArray();
        self::assertResponseStatusCodeSame(201);

        $http->request('POST', '/api/documents', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'type' => 'ANNEXE_DEBOURS',
                'dateEmission' => '2026-09-15',
                'client' => $client,
                'documentSource' => $annexe['@id'],
            ],
        ]);
        self::assertResponseStatusCodeSame(422);

        $autre = $this->creerPiece($http, $client, 'FACTURE', '2026-09-16');
        $http->request('PATCH', '/api/documents/'.$annexe['id'], [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json' => ['documentSource' => $autre['@id']],
        ]);
        self::assertResponseStatusCodeSame(422);

        $fiche = $this->entityManager->find(
            \App\Entity\Client::class,
            Uuid::fromString(basename($client)),
        );
        $legacy = (new Document())
            ->setNumero('FC2020-01-009')
            ->setType(TypeDocument::FACTURE)
            ->setStatut(StatutDocument::PAYE)
            ->setDateEmission(new \DateTimeImmutable('2020-01-10'))
            ->setClient($fiche)
            ->setLegacy(true)
            ->setVerrouille(true);
        $this->entityManager->persist($legacy);
        $this->entityManager->flush();

        $http->request('POST', '/api/documents', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'type' => 'ANNEXE_DEBOURS',
                'dateEmission' => '2026-09-15',
                'client' => $client,
                'documentSource' => '/api/documents/'.$legacy->getId(),
            ],
        ]);
        self::assertResponseStatusCodeSame(422);
    }

    public function testLAnnexeDeDeboursPorteLaMentionLegale(): void
    {
        $http = $this->clientAuthentifie();
        $fournisseur = $http->request('POST', '/api/fournisseurs', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => ['nom' => 'BigMat'],
        ])->toArray();
        self::assertResponseStatusCodeSame(201);

        $client = $this->creerClient($http);
        $devis = $this->creerPiece($http, $client, 'DEVIS', '2026-09-15');
        $annexe = $http->request('POST', '/api/documents', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'type' => 'ANNEXE_DEBOURS',
                'dateEmission' => '2026-09-15',
                'objet' => 'Annexe au devis '.$devis['numero'],
                'client' => $client,
                'documentSource' => $devis['@id'],
                'lignes' => [[
                    'type' => 'DEBOURS',
                    'libelle' => 'Carrelage',
                    'unite' => 'M2',
                    'quantite' => '4',
                    'prixUnitaireHt' => '25.00',
                    'tauxTva' => '3',
                    'fournisseur' => $fournisseur['@id'],
                ]],
            ],
        ])->toArray();
        self::assertResponseStatusCodeSame(201);
        self::assertSame('ANNEXE_DEBOURS', $annexe['type']);
        self::assertMatchesRegularExpression('/^AD\d{4}-\d{2}-\d{3}$/', $annexe['numero']);

        $html = $this->rendre($annexe['id']);
        self::assertStringContainsString('Annexe au devis '.$devis['numero'], $html);
        self::assertStringContainsString('BigMat', $html);
        self::assertStringContainsString(
            'Les matériaux seront à régler directement auprès de chaque fournisseur selon leur modalité de paiement.',
            $html,
        );
    }

    public function testLaRechercheRetrouveLeNomDuClient(): void
    {
        $http = $this->clientAuthentifie();
        $particulier = $http->request('POST', '/api/clients', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => ['typologie' => 'PARTICULIER', 'nom' => 'Martin', 'prenom' => 'Lea'],
        ])->toArray();
        self::assertResponseStatusCodeSame(201);
        $professionnel = $http->request('POST', '/api/clients', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'typologie' => 'PROFESSIONNEL',
                'raisonSociale' => 'Dupont BTP',
                'siret' => '73282932000074',
            ],
        ])->toArray();
        self::assertResponseStatusCodeSame(201);

        $devisMartin = $this->creerPiece($http, $particulier['@id'], 'DEVIS', '2026-09-15');
        $factureDupont = $this->creerPiece($http, $professionnel['@id'], 'FACTURE', '2026-09-16');

        $parNom = $http->request('GET', '/api/documents?recherche=martin')->toArray();
        self::assertResponseIsSuccessful();
        self::assertSame([$devisMartin['numero']], array_column($parNom['member'], 'numero'));

        $parPrenom = $http->request('GET', '/api/documents?recherche=lea')->toArray();
        self::assertResponseIsSuccessful();
        self::assertContains($devisMartin['numero'], array_column($parPrenom['member'], 'numero'));

        $parRaison = $http->request('GET', '/api/documents?recherche=Dupont')->toArray();
        self::assertResponseIsSuccessful();
        self::assertSame([$factureDupont['numero']], array_column($parRaison['member'], 'numero'));
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
