import { HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';
import { AuthService } from '../auth/auth.service';

/**
 * Ajoute le jeton JWT a toutes les requetes de l'API, sauf a la demande de
 * connexion elle-meme.
 */
export const jwtInterceptor: HttpInterceptorFn = (requete, suivant) => {
  const jeton = inject(AuthService).jeton;

  if (jeton === null || requete.url.includes('/api/login_check')) {
    return suivant(requete);
  }

  return suivant(
    requete.clone({ setHeaders: { Authorization: `Bearer ${jeton}` } }),
  );
};
