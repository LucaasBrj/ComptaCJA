import { HttpErrorResponse, HttpInterceptorFn } from '@angular/common/http';
import { inject } from '@angular/core';
import { catchError, throwError } from 'rxjs';
import { AuthService } from '../auth/auth.service';
import { NotificationService } from '../notification.service';
import { messageErreur } from './violation';

/**
 * Signale les erreurs techniques a l'utilisateur et deconnecte sur jeton expire.
 *
 * Les erreurs de validation (422) ne declenchent pas de notification : elles sont
 * reportees champ par champ sur le formulaire concerne.
 */
export const erreurInterceptor: HttpInterceptorFn = (requete, suivant) => {
  const notifications = inject(NotificationService);
  const auth = inject(AuthService);

  return suivant(requete).pipe(
    catchError((erreur: HttpErrorResponse) => {
      if (erreur.status === 401 && !requete.url.includes('/api/login_check')) {
        auth.deconnexion();
        notifications.erreur('Votre session a expiré, merci de vous reconnecter.');

        return throwError(() => erreur);
      }

      if (erreur.status !== 422) {
        notifications.erreur(messageErreur(erreur));
      }

      return throwError(() => erreur);
    }),
  );
};
