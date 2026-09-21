/**
 * Enveloppe des collections JSON-LD renvoyees par API Platform.
 * Depuis la version 4, les cles ne sont plus prefixees par "hydra:".
 */
export interface CollectionHydra<T> {
  readonly '@id': string;
  readonly totalItems: number;
  readonly member: readonly T[];
  readonly view?: {
    readonly '@id'?: string;
    readonly first?: string;
    readonly last?: string;
    readonly next?: string;
    readonly previous?: string;
  };
}

/**
 * Corps d'erreur renvoye par API Platform. Les violations de validation (422)
 * sont detaillees champ par champ, ce qui permet de les reporter sur le formulaire.
 */
export interface ProblemeApi {
  readonly title?: string;
  readonly detail?: string;
  readonly status?: number;
  readonly violations?: readonly ViolationApi[];
}

export interface ViolationApi {
  readonly propertyPath: string;
  readonly message: string;
}

/** Ressource minimale : toute entite exposee porte un IRI et un identifiant. */
export interface RessourceApi {
  readonly '@id'?: string;
  readonly id?: string;
}
