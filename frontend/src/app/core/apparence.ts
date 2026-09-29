import { computed, Injectable, signal } from '@angular/core';

export type Apparence = 'clair' | 'sombre';

const CLE = 'cja.apparence';

/**
 * Pose l'apparence choisie avant le démarrage d'Angular, pour éviter un flash
 * au chargement. Sans valeur enregistrée, le navigateur suit l'ordinateur.
 */
export function restaurerApparence(): void {
  const valeur = localStorage.getItem(CLE);
  if (valeur === 'clair' || valeur === 'sombre') {
    document.documentElement.dataset['apparence'] = valeur;
  }
}

@Injectable({ providedIn: 'root' })
export class ApparenceService {
  private readonly choisie = signal<Apparence | null>(this.lire());
  private readonly systeme = signal<Apparence>(this.lireSysteme());

  /** Apparence affichée : le choix enregistré, sinon celui de l'ordinateur. */
  readonly effective = computed(() => this.choisie() ?? this.systeme());

  constructor() {
    const media = window.matchMedia('(prefers-color-scheme: dark)');
    media.addEventListener('change', () => this.systeme.set(this.lireSysteme()));
  }

  readonly sombre = computed(() => this.effective() === 'sombre');

  definir(sombre: boolean): void {
    const suivante: Apparence = sombre ? 'sombre' : 'clair';
    this.choisie.set(suivante);
    localStorage.setItem(CLE, suivante);
    document.documentElement.dataset['apparence'] = suivante;
  }

  private lire(): Apparence | null {
    const valeur = localStorage.getItem(CLE);
    return valeur === 'clair' || valeur === 'sombre' ? valeur : null;
  }

  private lireSysteme(): Apparence {
    return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'sombre' : 'clair';
  }
}
