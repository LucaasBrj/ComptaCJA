import { RessourceApi } from './api.model';
import { Adresse, Chantier, TypeDocument, StatutDocument } from './client.model';

export type TauxTva = '0' | '1' | '2' | '3';
export type TypeLigne = 'TEXTE' | 'PRESTATION';
export type UnitePrestation = 'U' | 'M2' | 'ML' | 'FORFAIT';
export type RegimeTva = 'FRANCHISE_293B' | 'ASSUJETTI';

export const TAUX_TVA: readonly { readonly code: TauxTva; readonly libelle: string; readonly taux: number }[] = [
  { code: '0', libelle: '0 %', taux: 0 },
  { code: '1', libelle: '5,5 %', taux: 5.5 },
  { code: '2', libelle: '10 %', taux: 10 },
  { code: '3', libelle: '20 %', taux: 20 },
];

export const UNITES: readonly { readonly valeur: UnitePrestation; readonly libelle: string }[] = [
  { valeur: 'U', libelle: 'u' },
  { valeur: 'M2', libelle: 'm²' },
  { valeur: 'ML', libelle: 'ml' },
  { valeur: 'FORFAIT', libelle: 'forfait' },
];

export interface ResumeClient {
  readonly '@id'?: string;
  readonly id?: string;
  readonly numeroClient?: string;
  readonly nomAffichage?: string;
}

export interface LigneDocument {
  readonly '@id'?: string;
  type: TypeLigne;
  position?: number;
  libelle: string;
  unite: UnitePrestation | null;
  quantite: string | null;
  prixUnitaireHt: string | null;
  tauxTva: TauxTva | null;
  montantHt?: string;
  montantTva?: string;
  montantTtc?: string;
  prestation?: string | null;
}

export interface DocumentDetail extends RessourceApi {
  numero?: string;
  type: TypeDocument;
  statut: StatutDocument;
  dateEmission: string;
  dateEcheance: string | null;
  objet: string | null;
  montantHt: string;
  montantTva: string;
  montantTtc: string;
  legacy: boolean;
  verrouille: boolean;
  client: ResumeClient | string;
  chantier?: (Chantier & { '@id'?: string }) | string | null;
  lignes: LigneDocument[];
}

export interface Prestation extends RessourceApi {
  code: string;
  libelle: string;
  unite: UnitePrestation;
  tauxTvaDefaut: TauxTva;
  prixUnitaireHtDefaut: string | null;
  actif: boolean;
}

export interface Entreprise extends RessourceApi {
  raisonSociale: string;
  formeJuridique: string | null;
  siret: string | null;
  codeApe: string | null;
  numeroTvaIntracom: string | null;
  telephone: string | null;
  email: string | null;
  adresse: Adresse;
  regimeTva: RegimeTva;
  assureurNom: string | null;
  numeroContrat: string | null;
  couvertureGeographique: string | null;
  iban: string | null;
  bic: string | null;
  banque: string | null;
  conditionsReglement: string | null;
  penalitesRetard: string | null;
  indemniteRecouvrement: string;
}

export interface VentilationTva {
  readonly code: TauxTva;
  readonly libelle: string;
  readonly ht: number;
  readonly tva: number;
}

export interface TotauxDocument {
  readonly ht: number;
  readonly tva: number;
  readonly ttc: number;
  readonly ventilation: readonly VentilationTva[];
}

/** Apercu des totaux. Le serveur reste la reference a l'enregistrement. */
export function calculerTotaux(
  lignes: readonly {
    type: TypeLigne | null;
    quantite: string | null;
    prixUnitaireHt: string | null;
    tauxTva: TauxTva | null;
  }[],
): TotauxDocument {
  const paniers = new Map<TauxTva, { ht: number; tva: number }>();
  let ht = 0;
  let tva = 0;

  for (const ligne of lignes) {
    if (ligne.type !== 'PRESTATION' || !ligne.quantite || !ligne.prixUnitaireHt || !ligne.tauxTva) {
      continue;
    }

    const montantHt = arrondir(Number(ligne.quantite) * Number(ligne.prixUnitaireHt));
    const taux = TAUX_TVA.find((item) => item.code === ligne.tauxTva)?.taux ?? 0;
    const montantTva = arrondir((montantHt * taux) / 100);
    const panier = paniers.get(ligne.tauxTva) ?? { ht: 0, tva: 0 };
    panier.ht = arrondir(panier.ht + montantHt);
    panier.tva = arrondir(panier.tva + montantTva);
    paniers.set(ligne.tauxTva, panier);
    ht = arrondir(ht + montantHt);
    tva = arrondir(tva + montantTva);
  }

  return {
    ht,
    tva,
    ttc: arrondir(ht + tva),
    ventilation: [...paniers.entries()].map(([code, panier]) => ({
      code,
      libelle: TAUX_TVA.find((item) => item.code === code)?.libelle ?? code,
      ht: panier.ht,
      tva: panier.tva,
    })),
  };
}

function arrondir(valeur: number): number {
  return Math.round((valeur + Number.EPSILON) * 100) / 100;
}
