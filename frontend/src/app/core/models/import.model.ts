import { RessourceApi } from './api.model';

export type TypeImport = 'CLIENTS' | 'HISTORIQUE';

export type StatutImport = 'EN_ATTENTE_MAPPING' | 'MAPPE' | 'TERMINE' | 'ECHOUE';

export type StatutLigne = 'CREE' | 'MIS_A_JOUR' | 'IGNORE' | 'ERREUR';

export interface ChampImport {
  readonly code: string;
  readonly libelle: string;
  readonly obligatoire: boolean;
  readonly aide: string | null;
}

export interface ChampsImport {
  readonly type: TypeImport;
  readonly libelle: string;
  readonly champs: readonly ChampImport[];
}

export interface LigneRapport {
  readonly ligne: number;
  readonly statut: StatutLigne;
  readonly messages: readonly string[];
  readonly apercu: string;
}

export interface SessionImport extends RessourceApi {
  readonly type: TypeImport;
  readonly statut: StatutImport;
  readonly nomFichier: string;
  readonly extension: string;
  readonly colonnesDetectees: readonly string[];
  readonly apercu: readonly Record<string, string | null>[];
  /** Code de champ metier => intitule de colonne du fichier. */
  readonly mapping: Record<string, string>;
  readonly nbLignes: number;
  readonly nbSucces: number;
  readonly nbErreurs: number;
  readonly rapport: readonly LigneRapport[];
  readonly createdAt: string;
  readonly executedAt: string | null;
}

export const LIBELLES_TYPE_IMPORT: Readonly<Record<TypeImport, string>> = {
  CLIENTS: 'Panel clients',
  HISTORIQUE: 'Historique des devis et factures',
};

export const LIBELLES_STATUT_LIGNE: Readonly<Record<StatutLigne, string>> = {
  CREE: 'Création',
  MIS_A_JOUR: 'Mise à jour',
  IGNORE: 'Ignorée',
  ERREUR: 'Erreur',
};
