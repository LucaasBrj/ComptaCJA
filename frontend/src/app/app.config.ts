import { registerLocaleData } from '@angular/common';
import localeFr from '@angular/common/locales/fr';
import { provideHttpClient, withInterceptors } from '@angular/common/http';
import { ApplicationConfig, LOCALE_ID, provideBrowserGlobalErrorListeners } from '@angular/core';
import { MAT_FORM_FIELD_DEFAULT_OPTIONS } from '@angular/material/form-field';
import { provideRouter, withComponentInputBinding } from '@angular/router';
import { routes } from './app.routes';
import { erreurInterceptor } from './core/http/erreur.interceptor';
import { jwtInterceptor } from './core/http/jwt.interceptor';

// Dates au format jj/mm/aaaa et montants en euros : l'application est
// exclusivement destinee a un artisan francais.
registerLocaleData(localeFr);

export const appConfig: ApplicationConfig = {
  providers: [
    provideBrowserGlobalErrorListeners(),
    provideRouter(routes, withComponentInputBinding()),
    { provide: LOCALE_ID, useValue: 'fr-FR' },
    // L'ordre compte : le jeton est pose avant que l'intercepteur d'erreurs
    // n'observe la reponse.
    provideHttpClient(withInterceptors([jwtInterceptor, erreurInterceptor])),
    {
      provide: MAT_FORM_FIELD_DEFAULT_OPTIONS,
      useValue: { appearance: 'outline', subscriptSizing: 'dynamic' },
    },
  ],
};
