<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Tests\ApiTestCase;
use App\Tests\Mock\CollecteurMessages;
use Symfony\Component\Mime\Email;

final class EnvoiEmailTest extends ApiTestCase
{
    public function testLEnvoiJointLePdfEtRemplaceLesJetonsRestants(): void
    {
        $http = $this->clientAuthentifie();
        $client = $http->request('POST', '/api/clients', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'typologie' => 'PARTICULIER',
                'nom' => 'Martin',
                'prenom' => 'Lea',
                'email' => 'lea@example.com',
            ],
        ])->toArray();
        self::assertResponseStatusCodeSame(201);

        $devis = $http->request('POST', '/api/documents', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'type' => 'DEVIS',
                'dateEmission' => '2026-09-15',
                'objet' => 'Salle de bain',
                'client' => $client['@id'],
                'lignes' => [[
                    'type' => 'PRESTATION',
                    'libelle' => 'Pose',
                    'unite' => 'M2',
                    'quantite' => '1',
                    'prixUnitaireHt' => '100.00',
                    'tauxTva' => '2',
                ]],
            ],
        ])->toArray();
        self::assertResponseStatusCodeSame(201);

        $http->request('POST', '/api/documents/'.$devis['id'].'/envoyer-email', [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => [
                'destinataire' => 'lea@example.com',
                'sujet' => 'Devis {{numero}}',
                'corps' => 'Bonjour {{client}}, montant {{montant}}.',
            ],
        ]);
        self::assertResponseStatusCodeSame(422);

        $http->request('PATCH', '/api/entreprise', [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json' => ['email' => 'artisan@cja.test'],
        ]);
        self::assertResponseIsSuccessful();

        $http->request('POST', '/api/documents/'.$devis['id'].'/envoyer-email', [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => [
                'destinataire' => 'pas-une-adresse',
                'sujet' => 'Devis {{numero}}',
                'corps' => 'Bonjour.',
            ],
        ]);
        self::assertResponseStatusCodeSame(422);

        $http->request('POST', '/api/documents/'.$devis['id'].'/envoyer-email', [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => [
                'destinataire' => 'lea@example.com',
                'sujet' => 'Devis {{numero}}',
                'corps' => 'Bonjour {{client}}, montant {{montant}}.',
            ],
        ]);
        self::assertResponseStatusCodeSame(204);

        $collecteur = static::getContainer()->get(CollecteurMessages::class);
        self::assertInstanceOf(CollecteurMessages::class, $collecteur);
        self::assertCount(1, $collecteur->messages);
        $message = $collecteur->messages[0];
        self::assertInstanceOf(Email::class, $message);
        self::assertSame('artisan@cja.test', $message->getFrom()[0]->getAddress());
        self::assertSame('lea@example.com', $message->getTo()[0]->getAddress());
        self::assertSame('Devis '.$devis['numero'], $message->getSubject());
        self::assertStringContainsString('Bonjour Martin Lea, montant 110,00 €.', $message->getTextBody() ?? '');

        $pieces = $message->getAttachments();
        self::assertCount(1, $pieces);
        self::assertSame($devis['numero'].'.pdf', $pieces[0]->getFilename());
        self::assertSame('application/pdf', $pieces[0]->getContentType());
        self::assertStringStartsWith('%PDF', $pieces[0]->getBody());

        $relu = $http->request('GET', '/api/documents/'.$devis['id'])->toArray();
        self::assertResponseIsSuccessful();
        self::assertSame('ENVOYE', $relu['statut']);
        self::assertTrue($relu['verrouille']);
    }

    public function testLesAnnexesCocheesPartentAvecLaPieceEtUneAnnexeEtrangereEstRefusee(): void
    {
        $http = $this->clientAuthentifie();
        $client = $this->creerClient($http);
        $this->renseignerExpediteur($http);

        $devis = $this->creerDevis($http, $client);
        $annexe = $http->request('POST', '/api/documents/'.$devis['id'].'/annexe-debours')->toArray();
        self::assertResponseStatusCodeSame(201);

        $http->request('POST', '/api/documents/'.$devis['id'].'/envoyer-email', [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => [
                'destinataire' => 'lea@example.com',
                'sujet' => 'Devis et annexe',
                'corps' => 'Deux pieces jointes.',
                'annexes' => [$annexe['id']],
            ],
        ]);
        self::assertResponseStatusCodeSame(204);

        $collecteur = static::getContainer()->get(CollecteurMessages::class);
        self::assertInstanceOf(CollecteurMessages::class, $collecteur);
        $message = $collecteur->messages[0];
        self::assertInstanceOf(Email::class, $message);
        $noms = array_map(static fn ($piece) => $piece->getFilename(), $message->getAttachments());
        self::assertSame([$devis['numero'].'.pdf', $annexe['numero'].'.pdf'], $noms);

        $devisRelu = $http->request('GET', '/api/documents/'.$devis['id'])->toArray();
        $annexeRelue = $http->request('GET', '/api/documents/'.$annexe['id'])->toArray();
        self::assertSame('ENVOYE', $devisRelu['statut']);
        self::assertTrue($devisRelu['verrouille']);
        self::assertSame('ENVOYE', $annexeRelue['statut']);
        self::assertTrue($annexeRelue['verrouille']);

        $autre = $this->creerDevis($http, $client);
        $annexeAutre = $http->request('POST', '/api/documents/'.$autre['id'].'/annexe-debours')->toArray();
        self::assertResponseStatusCodeSame(201);

        $http->request('POST', '/api/documents/'.$autre['id'].'/envoyer-email', [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => [
                'destinataire' => 'lea@example.com',
                'sujet' => 'Sans annexe',
                'corps' => 'Une seule piece.',
            ],
        ]);
        self::assertResponseStatusCodeSame(204);
        $collecteur = static::getContainer()->get(CollecteurMessages::class);
        self::assertInstanceOf(CollecteurMessages::class, $collecteur);
        $seul = $collecteur->messages[array_key_last($collecteur->messages)];
        self::assertInstanceOf(Email::class, $seul);
        self::assertCount(1, $seul->getAttachments());
        $annexeLaisee = $http->request('GET', '/api/documents/'.$annexeAutre['id'])->toArray();
        self::assertSame('BROUILLON', $annexeLaisee['statut']);
        self::assertFalse($annexeLaisee['verrouille']);

        $http->request('POST', '/api/documents/'.$annexeAutre['id'].'/envoyer-email', [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => [
                'destinataire' => 'lea@example.com',
                'sujet' => 'Annexe seule',
                'corps' => 'Ci-joint.',
            ],
        ]);
        self::assertResponseStatusCodeSame(204);
        $annexeSeule = $http->request('GET', '/api/documents/'.$annexeAutre['id'])->toArray();
        self::assertSame('ENVOYE', $annexeSeule['statut']);
        self::assertTrue($annexeSeule['verrouille']);

        $http->request('POST', '/api/documents/'.$devis['id'].'/envoyer-email', [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => [
                'destinataire' => 'lea@example.com',
                'sujet' => 'Annexe etrangere',
                'corps' => 'Refus.',
                'annexes' => [$annexeAutre['id']],
            ],
        ]);
        self::assertResponseStatusCodeSame(422);
    }

    private function creerClient(mixed $http): string
    {
        $client = $http->request('POST', '/api/clients', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'typologie' => 'PARTICULIER',
                'nom' => 'Martin',
                'prenom' => 'Lea',
                'email' => 'lea@example.com',
            ],
        ])->toArray();
        self::assertResponseStatusCodeSame(201);

        return $client['@id'];
    }

    private function renseignerExpediteur(mixed $http): void
    {
        $http->request('PATCH', '/api/entreprise', [
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json' => ['email' => 'artisan@cja.test'],
        ]);
        self::assertResponseIsSuccessful();
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
                'objet' => 'Salle de bain',
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
        ])->toArray();
        self::assertResponseStatusCodeSame(201);

        return $devis;
    }
}
