<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Chantier;
use App\Entity\Client;
use App\Entity\Document;
use App\Enum\StatutDocument;
use App\Repository\ChantierRepository;
use App\Repository\ClientRepository;
use App\Repository\DocumentRepository;

/**
 * Recherche instantanee sur les clients, les numeros, les libelles de ligne
 * et la commune du chantier.
 */
final class RechercheGlobale
{
    private const PLAFOND = 8;

    public function __construct(
        private readonly ClientRepository $clients,
        private readonly DocumentRepository $documents,
        private readonly ChantierRepository $chantiers,
    ) {
    }

    /**
     * @return array{clients: list<array{iri: string, libelle: string, sousTitre: string}>, documents: list<array{iri: string, libelle: string, sousTitre: string}>, chantiers: list<array{iri: string, libelle: string, sousTitre: string}>}
     */
    public function chercher(string $terme): array
    {
        $terme = trim($terme);

        if (mb_strlen($terme) < 2) {
            return ['clients' => [], 'documents' => [], 'chantiers' => []];
        }

        $motif = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($terme)).'%';

        return [
            'clients' => array_map($this->decrireClient(...), $this->trouverClients($motif)),
            'documents' => array_map($this->decrireDocument(...), $this->trouverDocuments($motif)),
            'chantiers' => array_map($this->decrireChantier(...), $this->trouverChantiers($motif)),
        ];
    }

    /**
     * @return list<Client>
     */
    private function trouverClients(string $motif): array
    {
        /** @var list<Client> $clients */
        $clients = $this->clients->createQueryBuilder('c')
            ->andWhere('LOWER(c.nom) LIKE :motif ESCAPE \'!\' OR LOWER(c.prenom) LIKE :motif ESCAPE \'!\' OR LOWER(c.raisonSociale) LIKE :motif ESCAPE \'!\'')
            ->setParameter('motif', $motif)
            ->setMaxResults(self::PLAFOND)
            ->getQuery()
            ->getResult();

        return $clients;
    }

    /**
     * @return list<Document>
     */
    private function trouverDocuments(string $motif): array
    {
        /** @var list<Document> $documents */
        $documents = $this->documents->createQueryBuilder('d')
            ->leftJoin('d.lignes', 'l')
            ->andWhere('LOWER(d.numero) LIKE :motif ESCAPE \'!\' OR LOWER(l.libelle) LIKE :motif ESCAPE \'!\'')
            ->setParameter('motif', $motif)
            ->distinct()
            ->setMaxResults(self::PLAFOND)
            ->getQuery()
            ->getResult();

        return $documents;
    }

    /**
     * @return list<Chantier>
     */
    private function trouverChantiers(string $motif): array
    {
        /** @var list<Chantier> $chantiers */
        $chantiers = $this->chantiers->createQueryBuilder('c')
            ->andWhere('LOWER(c.adresse.ville) LIKE :motif ESCAPE \'!\'')
            ->setParameter('motif', $motif)
            ->setMaxResults(self::PLAFOND)
            ->getQuery()
            ->getResult();

        return $chantiers;
    }

    /**
     * @return array{iri: string, libelle: string, sousTitre: string}
     */
    private function decrireClient(Client $client): array
    {
        return [
            'iri' => '/api/clients/'.$client->getId(),
            'libelle' => $client->getNomAffichage(),
            'sousTitre' => (string) $client->getNumeroClient(),
        ];
    }

    /**
     * @return array{iri: string, libelle: string, sousTitre: string}
     */
    private function decrireDocument(Document $document): array
    {
        return [
            'iri' => '/api/documents/'.$document->getId(),
            'libelle' => (string) $document->getNumero(),
            'sousTitre' => trim(($document->getType()?->libelle() ?? '').' · '.$this->libelleStatut($document->getStatut())),
        ];
    }

    /**
     * @return array{iri: string, libelle: string, sousTitre: string}
     */
    private function decrireChantier(Chantier $chantier): array
    {
        return [
            'iri' => '/api/clients/'.$chantier->getClient()?->getId(),
            'libelle' => $chantier->getLibelleComplet(),
            'sousTitre' => $chantier->getClient()?->getNomAffichage() ?? '',
        ];
    }

    private function libelleStatut(StatutDocument $statut): string
    {
        return match ($statut) {
            StatutDocument::BROUILLON => 'Brouillon',
            StatutDocument::ENVOYE => 'Envoyé',
            StatutDocument::ACCEPTE => 'Accepté',
            StatutDocument::REFUSE => 'Refusé',
            StatutDocument::PAYE => 'Payé',
            StatutDocument::EN_RETARD => 'En retard',
            StatutDocument::ANNULE => 'Annulé',
        };
    }
}
