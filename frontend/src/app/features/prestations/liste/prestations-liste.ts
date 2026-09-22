import { Component, inject, signal } from '@angular/core';
import { MatButtonModule } from '@angular/material/button';
import { MatCardModule } from '@angular/material/card';
import { MatDialog, MatDialogModule } from '@angular/material/dialog';
import { MatIconModule } from '@angular/material/icon';
import { MatTableModule } from '@angular/material/table';
import { PrestationApiService } from '../../../core/http/prestation-api.service';
import { identifiantDepuisIri } from '../../../core/iri';
import { NotificationService } from '../../../core/notification.service';
import { Prestation, TAUX_TVA, UNITES } from '../../../core/models/document.model';
import { ConfirmationDialog } from '../../../shared/confirmation-dialog/confirmation-dialog';
import { PrestationDialog } from '../dialog/prestation-dialog';

@Component({
  selector: 'app-prestations-liste',
  imports: [MatCardModule, MatTableModule, MatButtonModule, MatIconModule, MatDialogModule],
  templateUrl: './prestations-liste.html',
  styleUrl: './prestations-liste.scss',
})
export class PrestationsListe {
  private readonly api = inject(PrestationApiService);
  private readonly dialog = inject(MatDialog);
  private readonly notifications = inject(NotificationService);

  protected readonly colonnes = ['code', 'libelle', 'unite', 'taux', 'prix', 'actions'];
  protected readonly prestations = signal<readonly Prestation[]>([]);
  protected readonly unites = UNITES;
  protected readonly taux = TAUX_TVA;

  constructor() {
    this.charger();
  }

  protected libelleUnite(valeur: string): string {
    return this.unites.find((item) => item.valeur === valeur)?.libelle ?? valeur;
  }

  protected libelleTaux(code: string): string {
    return this.taux.find((item) => item.code === code)?.libelle ?? code;
  }

  protected ouvrir(prestation: Prestation | null): void {
    this.dialog
      .open(PrestationDialog, { data: { prestation }, width: '32rem', maxWidth: '95vw' })
      .afterClosed()
      .subscribe((enregistre) => {
        if (enregistre) {
          this.charger();
        }
      });
  }

  protected supprimer(prestation: Prestation): void {
    this.dialog
      .open(ConfirmationDialog, {
        data: {
          titre: 'Supprimer la prestation',
          message: `Retirer ${prestation.code} de la bibliothèque ?`,
        },
      })
      .afterClosed()
      .subscribe((confirme) => {
        if (!confirme) {
          return;
        }
        this.api.supprimer(identifiantDepuisIri(prestation)).subscribe(() => {
          this.notifications.succes('Prestation supprimée.');
          this.charger();
        });
      });
  }

  private charger(): void {
    this.api.listerToutes(0, 50, '').subscribe((page) => this.prestations.set(page.member));
  }
}
