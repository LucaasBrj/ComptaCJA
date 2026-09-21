<?php

declare(strict_types=1);

namespace App\Tests\Api;

use App\Entity\Client;
use App\Entity\Document;
use App\Tests\ApiTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class ImportTest extends ApiTestCase
{
    public function testLesChampsCiblesSontExposesPourLEcranDeMapping(): void
    {
        $reponse = $this->clientAuthentifie()->request('GET', '/api/imports/champs/CLIENTS');

        self::assertResponseIsSuccessful();
        $champs = $reponse->toArray()['champs'];
        self::assertContains('numeroClient', array_column($champs, 'code'));
        self::assertContains('chantierVille', array_column($champs, 'code'));
    }

    public function testLeTeleversementDetecteLesColonnesEtPreMappeLesChamps(): void
    {
        $import = $this->televerser('clients-exemple.csv', 'CLIENTS');

        self::assertSame('EN_ATTENTE_MAPPING', $import['statut']);
        self::assertContains('N° Client', $import['colonnesDetectees']);
        self::assertContains('Ville chantier', $import['colonnesDetectees']);

        // Le pre-mapping doit reconnaitre les intitules accentues et abreges.
        self::assertSame('N° Client', $import['mapping']['numeroClient']);
        self::assertSame('Prénom', $import['mapping']['prenom']);
        self::assertSame('Société', $import['mapping']['raisonSociale']);
        self::assertSame('Code postal', $import['mapping']['codePostal']);
        self::assertSame('Ville chantier', $import['mapping']['chantierVille']);
    }

    public function testLExecutionABlancNEcritRien(): void
    {
        $client = $this->clientAuthentifie();
        $import = $this->televerser('clients-exemple.csv', 'CLIENTS', $client);

        $client->request('POST', '/api/imports/'.$import['id'].'/mapping', [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => ['mapping' => $import['mapping']],
        ]);
        self::assertResponseIsSuccessful();

        $reponse = $client->request('POST', '/api/imports/'.$import['id'].'/execution?aBlanc=true');
        self::assertResponseIsSuccessful();

        $rapport = $reponse->toArray();
        self::assertSame(5, $rapport['nbLignes']);
        self::assertSame(5, $rapport['nbSucces']);
        self::assertSame(0, $rapport['nbErreurs']);
        self::assertSame('MAPPE', $rapport['statut'], "Un passage a blanc ne doit pas cloturer la session.");
        self::assertSame(0, $this->compter(Client::class), 'Aucun client ne doit subsister apres un passage a blanc.');
    }

    public function testLExecutionReelleCreeLesClientsEtLeursChantiers(): void
    {
        $this->importerClients();

        self::assertSame(5, $this->compter(Client::class));

        $clients = $this->entityManager->getRepository(Client::class)->findBy([], ['numeroClient' => 'ASC']);
        $premier = $clients[0];

        self::assertSame('CLI-0101', $premier->getNumeroClient(), 'Le numero present dans le fichier doit etre conserve.');
        self::assertSame('Dubois', $premier->getNom());
        self::assertSame('0611223344', $premier->getTelephone(), 'Les separateurs du telephone doivent etre retires.');
        self::assertSame('Toulouse', $premier->getAdresseFacturation()->getVille());
        self::assertCount(1, $premier->getChantiers());
        self::assertSame('Rénovation salle de bain', $premier->getChantiers()->first()->getLibelle());

        $societe = $this->entityManager->getRepository(Client::class)->findOneBy(['raisonSociale' => 'SCI Les Tilleuls']);
        self::assertNotNull($societe);
        self::assertSame('PROFESSIONNEL', $societe->getTypologie()->value);
        self::assertSame('73282932000074', $societe->getSiret());
    }

    public function testLaNumerotationRepartApresLesNumerosImportes(): void
    {
        $client = $this->importerClients();

        $reponse = $client->request('POST', '/api/clients', [
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => ['typologie' => 'PARTICULIER', 'nom' => 'Nouveau'],
        ]);

        self::assertResponseStatusCodeSame(201);
        self::assertSame(
            'CLI-0106',
            $reponse->toArray()['numeroClient'],
            'Le compteur doit avoir ete avance jusqu\'au plus grand numero importe.',
        );
    }

    public function testLImportDeLHistoriqueRattacheLesPiecesEtReserveLesNumeros(): void
    {
        $client = $this->importerClients();
        $this->importerFichier('historique-exemple.csv', 'HISTORIQUE', $client);

        self::assertSame(6, $this->compter(Document::class));

        $facture = $this->entityManager->getRepository(Document::class)->findOneBy(['numero' => 'FC2026-02-001']);
        self::assertNotNull($facture);
        self::assertSame('FACTURE', $facture->getType()->value);
        self::assertSame('PAYE', $facture->getStatut()->value);
        self::assertSame('4250.00', $facture->getMontantHt(), 'Le separateur de milliers doit etre interprete.');
        self::assertSame('4675.00', $facture->getMontantTtc());
        self::assertSame('CLI-0101', $facture->getClient()->getNumeroClient());
        self::assertTrue($facture->isLegacy());
        self::assertTrue($facture->isVerrouille());
        self::assertSame('Rénovation salle de bain', $facture->getChantier()?->getLibelle());

        $impayee = $this->entityManager->getRepository(Document::class)->findOneBy(['numero' => 'FC2026-04-002']);
        self::assertSame('EN_RETARD', $impayee->getStatut()->value, 'L\'etat "Impayé" doit etre reconnu.');
    }

    public function testUnFichierComportantUneErreurNEstPasImportePartiellement(): void
    {
        $chemin = \sprintf('%s/import-invalide-%s.csv', sys_get_temp_dir(), uniqid());
        file_put_contents($chemin, <<<'CSV'
            Nom;Email;Ville
            Valide;valide@example.com;Toulouse
            ;;
            Invalide;pas-une-adresse-email;Muret
            CSV);

        try {
            $client = $this->clientAuthentifie();
            $import = $this->televerserChemin($chemin, 'CLIENTS', $client);

            $client->request('POST', '/api/imports/'.$import['id'].'/mapping', [
                'headers' => ['Content-Type' => 'application/json'],
                'json' => ['mapping' => $import['mapping']],
            ]);

            $rapport = $client->request('POST', '/api/imports/'.$import['id'].'/execution?aBlanc=false')->toArray();

            self::assertSame('ECHOUE', $rapport['statut']);
            self::assertSame(2, $rapport['nbErreurs']);
            self::assertSame(0, $this->compter(Client::class), 'Un fichier partiellement invalide ne doit rien ecrire.');

            $messages = implode(' ', array_merge(...array_column($rapport['rapport'], 'messages')));
            self::assertStringContainsString('ni nom ni raison sociale', $messages);
            self::assertStringContainsString('email', $messages);
        } finally {
            @unlink($chemin);
        }
    }

    public function testLeMappingRefuseUnChampObligatoireNonAssocie(): void
    {
        $client = $this->clientAuthentifie();
        $import = $this->televerser('historique-exemple.csv', 'HISTORIQUE', $client);

        $mapping = $import['mapping'];
        unset($mapping['numero']);

        $client->request('POST', '/api/imports/'.$import['id'].'/mapping', [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => ['mapping' => $mapping],
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('Numero de piece', (string) self::getClient()->getResponse()->getContent(false));
    }

    /**
     * @return array<string, mixed>
     */
    private function televerser(string $nomFichier, string $type, ?object $client = null): array
    {
        return $this->televerserChemin(\dirname(__DIR__, 2).'/fixtures/'.$nomFichier, $type, $client);
    }

    /**
     * @return array<string, mixed>
     */
    private function televerserChemin(string $chemin, string $type, ?object $client = null): array
    {
        $client ??= $this->clientAuthentifie();

        // Le fichier est deplace par le service : on televerse une copie pour
        // preserver la fixture d'origine.
        $copie = \sprintf('%s/%s-%s', sys_get_temp_dir(), uniqid(), basename($chemin));
        copy($chemin, $copie);

        $reponse = $client->request('POST', '/api/imports', [
            'headers' => ['Content-Type' => 'multipart/form-data'],
            'extra' => [
                'parameters' => ['type' => $type],
                'files' => ['fichier' => new UploadedFile($copie, basename($chemin), 'text/csv', test: true)],
            ],
        ]);

        self::assertResponseStatusCodeSame(201);

        return $reponse->toArray();
    }

    private function importerClients(): object
    {
        $client = $this->clientAuthentifie();
        $this->importerFichier('clients-exemple.csv', 'CLIENTS', $client);

        return $client;
    }

    private function importerFichier(string $nomFichier, string $type, object $client): void
    {
        $import = $this->televerser($nomFichier, $type, $client);

        $client->request('POST', '/api/imports/'.$import['id'].'/mapping', [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => ['mapping' => $import['mapping']],
        ]);
        self::assertResponseIsSuccessful();

        $rapport = $client->request('POST', '/api/imports/'.$import['id'].'/execution?aBlanc=false')->toArray();
        self::assertSame('TERMINE', $rapport['statut'], 'Rapport : '.json_encode($rapport['rapport'], \JSON_UNESCAPED_UNICODE));
    }

    /**
     * @param class-string $classe
     */
    private function compter(string $classe): int
    {
        $this->entityManager->clear();

        return $this->entityManager->getRepository($classe)->count([]);
    }
}
