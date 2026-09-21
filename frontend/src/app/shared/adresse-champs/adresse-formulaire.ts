import { FormBuilder, FormGroup, Validators } from '@angular/forms';
import { Adresse } from '../../core/models/client.model';

/**
 * Construit le groupe de controles correspondant a l'embeddable Adresse cote API.
 * Le code postal est valide des la saisie, comme le fait le serveur.
 */
export function groupeAdresse(fb: FormBuilder, valeurs?: Adresse | null): FormGroup {
  return fb.group({
    ligne1: fb.control<string | null>(valeurs?.ligne1 ?? null),
    ligne2: fb.control<string | null>(valeurs?.ligne2 ?? null),
    codePostal: fb.control<string | null>(valeurs?.codePostal ?? null, Validators.pattern(/^\d{5}$/)),
    ville: fb.control<string | null>(valeurs?.ville ?? null),
    pays: fb.control<string | null>(valeurs?.pays ?? 'France'),
  });
}
