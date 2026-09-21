import { HttpErrorResponse } from '@angular/common/http';
import { AbstractControl, FormGroup } from '@angular/forms';
import { ProblemeApi, ViolationApi } from '../models/api.model';

/** Message lisible a partir d'une reponse d'erreur de l'API. */
export function messageErreur(erreur: HttpErrorResponse): string {
  if (erreur.status === 0) {
    return "L'API est injoignable. Vérifiez que le serveur Symfony est démarré.";
  }

  const corps = erreur.error as ProblemeApi | string | null;

  if (typeof corps === 'string' && corps !== '') {
    return corps;
  }

  if (corps && typeof corps === 'object') {
    const violations = corps.violations;

    if (violations?.length) {
      return violations.map((violation) => violation.message).join(' ');
    }

    if (corps.detail) {
      return corps.detail;
    }
  }

  return `Une erreur est survenue (code ${erreur.status}).`;
}

/**
 * Reporte les violations du serveur sur les controles du formulaire.
 *
 * Le serveur reste la source de verite : ce qu'il refuse doit s'afficher sous le
 * champ concerne, sans avoir a dupliquer ses regles cote client.
 *
 * @returns les messages qui n'ont pu etre rattaches a aucun controle
 */
export function appliquerViolations(erreur: HttpErrorResponse, formulaire: FormGroup): string[] {
  const violations = (erreur.error as ProblemeApi | null)?.violations ?? [];
  const orphelins: string[] = [];

  for (const violation of violations) {
    const controle = trouverControle(formulaire, violation);

    if (controle) {
      controle.setErrors({ ...(controle.errors ?? {}), serveur: violation.message });
      controle.markAsTouched();
    } else {
      orphelins.push(violation.message);
    }
  }

  return orphelins;
}

function trouverControle(formulaire: FormGroup, violation: ViolationApi): AbstractControl | null {
  if (violation.propertyPath === '') {
    return null;
  }

  // Les chemins Symfony utilisent la notation "adresseFacturation.ville" et
  // "chantiers[0].libelle", que l'API de formulaires Angular accepte apres
  // conversion des crochets en segments.
  const chemin = violation.propertyPath.replace(/\[(\d+)\]/g, '.$1');

  return formulaire.get(chemin);
}

/** Premier message serveur porte par un controle, pour l'affichage dans mat-error. */
export function erreurServeur(controle: AbstractControl | null): string | null {
  const message = controle?.errors?.['serveur'];

  return typeof message === 'string' ? message : null;
}
