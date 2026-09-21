import { Adresse, adresseVide } from './client.model';
import { RessourceApi } from './api.model';

export interface Fournisseur extends RessourceApi {
  nom: string;
  contactNom: string | null;
  telephone: string | null;
  email: string | null;
  siteWeb: string | null;
  siret: string | null;
  adresse: Adresse;
  notes: string | null;
  actif: boolean;
  createdAt?: string;
}

export function fournisseurVide(): Fournisseur {
  return {
    nom: '',
    contactNom: null,
    telephone: null,
    email: null,
    siteWeb: null,
    siret: null,
    adresse: adresseVide(),
    notes: null,
    actif: true,
  };
}
