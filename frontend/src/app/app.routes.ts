import { Routes } from '@angular/router';
import { authGuard, inviteGuard } from './core/auth/auth.guard';

export const routes: Routes = [
  {
    path: 'connexion',
    canActivate: [inviteGuard],
    title: 'Connexion - CJA',
    loadComponent: () => import('./features/auth/connexion/connexion').then((m) => m.Connexion),
  },
  {
    path: '',
    canActivate: [authGuard],
    loadComponent: () => import('./layout/shell/shell').then((m) => m.Shell),
    children: [
      { path: '', pathMatch: 'full', redirectTo: 'tableau-de-bord' },
      {
        path: 'tableau-de-bord',
        title: 'Tableau de bord - CJA',
        loadComponent: () =>
          import('./features/suivi/tableau-de-bord').then((m) => m.TableauDeBordPage),
      },
      {
        path: 'documents',
        title: 'Documents - CJA',
        loadComponent: () =>
          import('./features/documents/liste/documents-liste').then((m) => m.DocumentsListe),
      },
      {
        path: 'documents/nouveau',
        title: 'Nouveau document - CJA',
        loadComponent: () =>
          import('./features/documents/editeur/document-editeur').then((m) => m.DocumentEditeur),
      },
      {
        path: 'documents/:id',
        title: 'Document - CJA',
        loadComponent: () =>
          import('./features/documents/editeur/document-editeur').then((m) => m.DocumentEditeur),
      },
      {
        path: 'prestations',
        title: 'Prestations - CJA',
        loadComponent: () =>
          import('./features/prestations/liste/prestations-liste').then((m) => m.PrestationsListe),
      },
      {
        path: 'reglages',
        title: 'Réglages - CJA',
        loadComponent: () =>
          import('./features/reglages/entreprise/reglages-entreprise').then((m) => m.ReglagesEntreprise),
      },
      {
        path: 'clients',
        title: 'Clients - CJA',
        loadComponent: () =>
          import('./features/clients/liste/clients-liste').then((m) => m.ClientsListe),
      },
      {
        path: 'clients/nouveau',
        title: 'Nouveau client - CJA',
        loadComponent: () =>
          import('./features/clients/formulaire/client-formulaire').then((m) => m.ClientFormulaire),
      },
      {
        path: 'clients/:id',
        title: 'Fiche client - CJA',
        loadComponent: () =>
          import('./features/clients/detail/client-detail').then((m) => m.ClientDetail),
      },
      {
        path: 'clients/:id/modifier',
        title: 'Modifier le client - CJA',
        loadComponent: () =>
          import('./features/clients/formulaire/client-formulaire').then((m) => m.ClientFormulaire),
      },
      {
        path: 'fournisseurs',
        title: 'Fournisseurs - CJA',
        loadComponent: () =>
          import('./features/fournisseurs/liste/fournisseurs-liste').then(
            (m) => m.FournisseursListe,
          ),
      },
      {
        path: 'imports',
        title: 'Importation - CJA',
        loadComponent: () =>
          import('./features/imports/assistant/import-assistant').then((m) => m.ImportAssistant),
      },
    ],
  },
  { path: '**', redirectTo: '' },
];
