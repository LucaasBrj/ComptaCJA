import { HttpClient } from '@angular/common/http';
import { Injectable, computed, inject, signal } from '@angular/core';
import { Router } from '@angular/router';
import { Observable, tap } from 'rxjs';

interface ReponseConnexion {
  readonly token: string;
}

export interface UtilisateurCourant {
  readonly email: string;
  readonly nomComplet: string | null;
  readonly roles: readonly string[];
}

const CLE_JETON = 'cja.jeton';

@Injectable({ providedIn: 'root' })
export class AuthService {
  private readonly http = inject(HttpClient);
  private readonly router = inject(Router);

  private readonly jetonSignal = signal<string | null>(localStorage.getItem(CLE_JETON));
  private readonly utilisateurSignal = signal<UtilisateurCourant | null>(null);

  readonly utilisateur = this.utilisateurSignal.asReadonly();
  readonly estConnecte = computed(() => this.jetonSignal() !== null);

  /**
   * Le libelle affiche dans la barre superieure : nom complet si renseigne,
   * email sinon.
   */
  readonly libelleUtilisateur = computed(() => {
    const utilisateur = this.utilisateurSignal();

    return utilisateur?.nomComplet ?? utilisateur?.email ?? '';
  });

  get jeton(): string | null {
    return this.jetonSignal();
  }

  connexion(email: string, motDePasse: string): Observable<ReponseConnexion> {
    return this.http
      .post<ReponseConnexion>('/api/login_check', { email, password: motDePasse })
      .pipe(
        tap((reponse) => {
          localStorage.setItem(CLE_JETON, reponse.token);
          this.jetonSignal.set(reponse.token);
        }),
      );
  }

  /**
   * Recharge le profil depuis /api/me. Sert aussi de verification de validite du
   * jeton au demarrage de l'application.
   */
  chargerProfil(): Observable<UtilisateurCourant> {
    return this.http
      .get<UtilisateurCourant>('/api/me')
      .pipe(tap((utilisateur) => this.utilisateurSignal.set(utilisateur)));
  }

  deconnexion(rediriger = true): void {
    localStorage.removeItem(CLE_JETON);
    this.jetonSignal.set(null);
    this.utilisateurSignal.set(null);

    if (rediriger) {
      void this.router.navigate(['/connexion']);
    }
  }
}
