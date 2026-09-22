import { Component, inject, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { FormsModule } from '@angular/forms';
import { MatButtonModule } from '@angular/material/button';
import { MatCardModule } from '@angular/material/card';
import { MatDialog, MatDialogModule } from '@angular/material/dialog';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatIconModule } from '@angular/material/icon';
import { MatInputModule } from '@angular/material/input';
import { MatPaginatorModule, PageEvent } from '@angular/material/paginator';
import { MatProgressBarModule } from '@angular/material/progress-bar';
import { MatSortModule, Sort } from '@angular/material/sort';
import { MatTableModule } from '@angular/material/table';
import { MatTooltipModule } from '@angular/material/tooltip';
import { Subject, debounceTime, distinctUntilChanged } from 'rxjs';
import { FournisseurApiService } from '../../../core/http/fournisseur-api.service';
import { identifiantDepuisIri } from '../../../core/iri';
import { adresseEnUneLigne } from '../../../core/models/client.model';
import { Fournisseur } from '../../../core/models/fournisseur.model';
import { NotificationService } from '../../../core/notification.service';
import { ConfirmationDialog } from '../../../shared/confirmation-dialog/confirmation-dialog';
import { FournisseurDialog } from '../dialog/fournisseur-dialog';

@Component({
  selector: 'app-fournisseurs-liste',
  imports: [
    FormsModule,
    MatCardModule,
    MatTableModule,
    MatSortModule,
    MatPaginatorModule,
    MatFormFieldModule,
    MatInputModule,
    MatButtonModule,
    MatIconModule,
    MatDialogModule,
    MatProgressBarModule,
    MatTooltipModule,
  ],
  templateUrl: './fournisseurs-liste.html',
  styleUrl: './fournisseurs-liste.scss',
})
export class FournisseursListe {
  private readonly api = inject(FournisseurApiService);
  private readonly dialog = inject(MatDialog);
  private readonly notifications = inject(NotificationService);

  protected readonly colonnes = ['nom', 'contact', 'commune', 'siteWeb', 'actions'];
  protected readonly fournisseurs = signal<readonly Fournisseur[]>([]);
  protected readonly total = signal(0);
  protected readonly chargement = signal(false);

  protected recherche = '';
  protected page = 0;
  protected parPage = 25;
  private triChamp = '';
  private triSens: 'asc' | 'desc' = 'asc';

  private readonly rechercheSaisie = new Subject<string>();

  protected readonly adresseEnUneLigne = adresseEnUneLigne;

  constructor() {
    this.rechercheSaisie
      .pipe(debounceTime(300), distinctUntilChanged(), takeUntilDestroyed())
      .subscribe((terme) => {
        this.recherche = terme;
        this.page = 0;
        this.charger();
      });

    this.charger();
  }

  protected surSaisieRecherche(terme: string): void {
    this.rechercheSaisie.next(terme);
  }

  protected surTri(tri: Sort): void {
    if (tri.direction === '') {
      return;
    }

    this.triChamp = tri.active;
    this.triSens = tri.direction;
    this.page = 0;
    this.charger();
  }

  protected surPagination(evenement: PageEvent): void {
    this.page = evenement.pageIndex;
    this.parPage = evenement.pageSize;
    this.charger();
  }

  protected ajouter(): void {
    this.ouvrirDialog(null);
  }

  protected modifier(fournisseur: Fournisseur): void {
    this.ouvrirDialog(fournisseur);
  }

  protected supprimer(fournisseur: Fournisseur): void {
    this.dialog
      .open(ConfirmationDialog, {
        data: {
          titre: 'Supprimer ce fournisseur ?',
          message: `« ${fournisseur.nom} » sera retiré de votre annuaire.`,
          libelleConfirmation: 'Supprimer',
          destructif: true,
        },
      })
      .afterClosed()
      .subscribe((confirme) => {
        if (confirme !== true) {
          return;
        }

        this.api.supprimer(identifiantDepuisIri(fournisseur)).subscribe(() => {
          this.notifications.succes('Fournisseur supprimé.');
          this.charger();
        });
      });
  }

  private ouvrirDialog(fournisseur: Fournisseur | null): void {
    this.dialog
      .open(FournisseurDialog, { data: { fournisseur }, width: '40rem', maxWidth: '95vw' })
      .afterClosed()
      .subscribe((enregistre) => {
        if (enregistre === true) {
          this.charger();
        }
      });
  }

  private charger(): void {
    this.chargement.set(true);

    this.api
      .lister({
        nom: this.recherche || undefined,
        page: this.page,
        parPage: this.parPage,
        triChamp: this.triChamp || undefined,
        triSens: this.triChamp ? this.triSens : undefined,
      })
      .subscribe({
        next: (collection) => {
          this.fournisseurs.set(collection.member);
          this.total.set(collection.totalItems);
          this.chargement.set(false);
        },
        error: () => this.chargement.set(false),
      });
  }
}
