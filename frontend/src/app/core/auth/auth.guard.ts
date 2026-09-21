import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';
import { AuthService } from './auth.service';

/** Protege les routes de l'espace de travail. */
export const authGuard: CanActivateFn = (_route, state) => {
  const auth = inject(AuthService);

  if (auth.estConnecte()) {
    return true;
  }

  // Memorise la destination pour y revenir apres la connexion.
  return inject(Router).createUrlTree(['/connexion'], {
    queryParams: { retour: state.url },
  });
};

/** Empeche d'afficher l'ecran de connexion a un utilisateur deja authentifie. */
export const inviteGuard: CanActivateFn = () => {
  const auth = inject(AuthService);

  return auth.estConnecte() ? inject(Router).createUrlTree(['/clients']) : true;
};
