import { BreakpointObserver, Breakpoints } from '@angular/cdk/layout';
import { Component, inject, signal } from '@angular/core';
import { takeUntilDestroyed, toSignal } from '@angular/core/rxjs-interop';
import { MatButtonModule } from '@angular/material/button';
import { MatDividerModule } from '@angular/material/divider';
import { MatIconModule } from '@angular/material/icon';
import { MatListModule } from '@angular/material/list';
import { MatMenuModule } from '@angular/material/menu';
import { MatSidenavModule } from '@angular/material/sidenav';
import { MatToolbarModule } from '@angular/material/toolbar';
import { Router, RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';
import { Subject, debounceTime, distinctUntilChanged, map, of, switchMap } from 'rxjs';
import { AuthService } from '../../core/auth/auth.service';
import { PageRecherche, ResultatRecherche, SuiviApiService } from '../../core/http/suivi-api.service';

interface EntreeMenu {
  readonly chemin: string;
  readonly libelle: string;
  readonly icone: string;
}

@Component({
  selector: 'app-shell',
  imports: [
    RouterOutlet,
    RouterLink,
    RouterLinkActive,
    MatSidenavModule,
    MatToolbarModule,
    MatIconModule,
    MatListModule,
    MatButtonModule,
    MatMenuModule,
    MatDividerModule,
  ],
  templateUrl: './shell.html',
  styleUrl: './shell.scss',
})
export class Shell {
  private readonly auth = inject(AuthService);
  private readonly suivi = inject(SuiviApiService);
  private readonly router = inject(Router);
  private readonly saisie = new Subject<string>();

  protected readonly libelleUtilisateur = this.auth.libelleUtilisateur;
  protected readonly terme = signal('');
  protected readonly resultats = signal<PageRecherche | null>(null);

  /**
   * En dessous de la largeur tablette, le menu passe en tiroir superpose pour
   * laisser toute la place au contenu.
   */
  protected readonly estCompact = toSignal(
    inject(BreakpointObserver)
      .observe([Breakpoints.XSmall, Breakpoints.Small])
      .pipe(map((etat) => etat.matches)),
    { initialValue: false },
  );

  protected readonly tiroirOuvert = signal(true);

  constructor() {
    this.saisie
      .pipe(
        debounceTime(250),
        distinctUntilChanged(),
        switchMap((terme) =>
          terme.trim().length < 2
            ? of({ clients: [], documents: [], chantiers: [] })
            : this.suivi.rechercher(terme.trim()),
        ),
        takeUntilDestroyed(),
      )
      .subscribe((page) => this.resultats.set(page));
  }

  protected readonly entrees: readonly EntreeMenu[] = [
    { chemin: '/tableau-de-bord', libelle: 'Tableau de bord', icone: 'dashboard' },
    { chemin: '/documents', libelle: 'Documents', icone: 'request_quote' },
    { chemin: '/clients', libelle: 'Clients', icone: 'groups' },
    { chemin: '/fournisseurs', libelle: 'Fournisseurs', icone: 'local_shipping' },
    { chemin: '/prestations', libelle: 'Prestations', icone: 'straighten' },
    { chemin: '/imports', libelle: 'Importation', icone: 'upload_file' },
    { chemin: '/reglages', libelle: 'Réglages', icone: 'settings' },
  ];

  protected surRecherche(terme: string): void {
    this.terme.set(terme);
    this.saisie.next(terme);
  }

  protected groupes(): readonly { titre: string; items: readonly ResultatRecherche[] }[] {
    const page = this.resultats();
    if (!page) {
      return [];
    }

    return [
      { titre: 'Clients', items: page.clients },
      { titre: 'Documents', items: page.documents },
      { titre: 'Chantiers', items: page.chantiers },
    ].filter((groupe) => groupe.items.length > 0);
  }

  protected aDesResultats(): boolean {
    const page = this.resultats();

    return !!page && (page.clients.length > 0 || page.documents.length > 0 || page.chantiers.length > 0);
  }

  protected ouvrir(resultat: ResultatRecherche): void {
    const identifiant = resultat.iri.split('/').pop();
    const chemin = resultat.iri.includes('/documents/') ? '/documents' : '/clients';
    this.terme.set('');
    this.resultats.set(null);
    void this.router.navigate([chemin, identifiant]);
  }

  protected basculerTiroir(): void {
    this.tiroirOuvert.update((ouvert) => !ouvert);
  }

  protected fermerSiCompact(): void {
    if (this.estCompact()) {
      this.tiroirOuvert.set(false);
    }
  }

  protected deconnexion(): void {
    this.auth.deconnexion();
  }
}
