import { BreakpointObserver, Breakpoints } from '@angular/cdk/layout';
import { Component, inject, signal } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { MatButtonModule } from '@angular/material/button';
import { MatDividerModule } from '@angular/material/divider';
import { MatIconModule } from '@angular/material/icon';
import { MatListModule } from '@angular/material/list';
import { MatMenuModule } from '@angular/material/menu';
import { MatSidenavModule } from '@angular/material/sidenav';
import { MatToolbarModule } from '@angular/material/toolbar';
import { RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';
import { map } from 'rxjs';
import { AuthService } from '../../core/auth/auth.service';

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

  protected readonly libelleUtilisateur = this.auth.libelleUtilisateur;

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

  protected readonly entrees: readonly EntreeMenu[] = [
    { chemin: '/clients', libelle: 'Clients', icone: 'groups' },
    { chemin: '/fournisseurs', libelle: 'Fournisseurs', icone: 'local_shipping' },
    { chemin: '/imports', libelle: 'Importation', icone: 'upload_file' },
  ];

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
