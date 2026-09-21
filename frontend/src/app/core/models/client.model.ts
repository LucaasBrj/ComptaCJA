import { RessourceApi } from './api.model';

export type TypologieClient = 'PARTICULIER' | 'PROFESSIONNEL';

export const TYPOLOGIES: readonly { readonly valeur: TypologieClient; readonly libelle: string }[] = [
  { valeur: 'PARTICULIER', libelle: 'Particulier' },
  { valeur: 'PROFESSIONNEL', libelle: 'Professionnel' },
];

export const CIVILITES = ['M.', 'Mme'] as const;

export interface Adresse {
  ligne1: string | null;
  ligne2: string | null;
  codePostal: string | null;
  ville: string | null;
  pays: string | null;
}

export function adresseVide(): Adresse {
  return { ligne1: null, ligne2: null, codePostal: null, ville: null, pays: 'France' };
}

export function adresseEnUneLigne(adresse: Adresse | null | undefined): string {
  if (!adresse) {
    return '';
  }

  const commune = [adresse.codePostal, adresse.ville].filter(Boolean).join(' ');

  return [adresse.ligne1, adresse.ligne2, commune].filter((partie) => !!partie).join(', ');
}

export interface Chantier extends RessourceApi {
  libelle: string;
  libelleComplet?: string;
  adresse: Adresse;
  /** IRI du client, absent quand le chantier est imbrique dans la fiche client. */
  client?: string;
  notes: string | null;
  actif: boolean;
}

export interface Client extends RessourceApi {
  numeroClient?: string;
  typologie: TypologieClient;
  civilite: string | null;
  nom: string | null;
  prenom: string | null;
  raisonSociale: string | null;
  telephone: string | null;
  email: string | null;
  adresseFacturation: Adresse;
  siret: string | null;
  numeroTvaIntracom: string | null;
  notes: string | null;
  actif: boolean;
  importe?: boolean;
  nomAffichage?: string;
  createdAt?: string;
  updatedAt?: string | null;
  /** Presents uniquement sur la fiche detaillee (groupe client:item). */
  chantiers?: Chantier[];
  documents?: Document[];
}

export type TypeDocument = 'DEVIS' | 'FACTURE' | 'FACTURE_ACOMPTE' | 'ANNEXE_DEBOURS';

export type StatutDocument =
  | 'BROUILLON'
  | 'ENVOYE'
  | 'ACCEPTE'
  | 'REFUSE'
  | 'PAYE'
  | 'EN_RETARD'
  | 'ANNULE';

export interface Document extends RessourceApi {
  numero: string;
  type: TypeDocument;
  statut: StatutDocument;
  dateEmission: string;
  dateEcheance: string | null;
  objet: string | null;
  /** Montants transmis en chaine : la precision decimale est preservee de bout en bout. */
  montantHt: string;
  montantTva: string;
  montantTtc: string;
  legacy: boolean;
  verrouille: boolean;
  chantier?: Chantier | string | null;
}

export const LIBELLES_TYPE_DOCUMENT: Readonly<Record<TypeDocument, string>> = {
  DEVIS: 'Devis',
  FACTURE: 'Facture',
  FACTURE_ACOMPTE: "Facture d'acompte",
  ANNEXE_DEBOURS: 'Annexe de débours',
};

export const LIBELLES_STATUT_DOCUMENT: Readonly<Record<StatutDocument, string>> = {
  BROUILLON: 'Brouillon',
  ENVOYE: 'Envoyé',
  ACCEPTE: 'Accepté',
  REFUSE: 'Refusé',
  PAYE: 'Payé',
  EN_RETARD: 'En retard',
  ANNULE: 'Annulé',
};

export function clientVide(): Client {
  return {
    typologie: 'PARTICULIER',
    civilite: null,
    nom: null,
    prenom: null,
    raisonSociale: null,
    telephone: null,
    email: null,
    adresseFacturation: adresseVide(),
    siret: null,
    numeroTvaIntracom: null,
    notes: null,
    actif: true,
  };
}
