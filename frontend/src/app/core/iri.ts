import { RessourceApi } from './models/api.model';

/**
 * Identifiant technique d'une ressource.
 *
 * API Platform expose l'IRI ("/api/clients/0192...") et, selon les groupes de
 * serialisation, l'identifiant brut. Les routes du frontend ont besoin du second.
 */
export function identifiantDepuisIri(ressource: RessourceApi | string | null | undefined): string {
  if (!ressource) {
    return '';
  }

  if (typeof ressource === 'string') {
    return ressource.split('/').pop() ?? '';
  }

  return ressource.id ?? identifiantDepuisIri(ressource['@id']);
}
