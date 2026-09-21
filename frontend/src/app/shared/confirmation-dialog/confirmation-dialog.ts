import { Component, inject } from '@angular/core';
import { MatButtonModule } from '@angular/material/button';
import { MAT_DIALOG_DATA, MatDialogModule } from '@angular/material/dialog';

export interface DonneesConfirmation {
  readonly titre: string;
  readonly message: string;
  readonly libelleConfirmation?: string;
  readonly destructif?: boolean;
}

/** Confirmation reutilisable avant une action irreversible. */
@Component({
  selector: 'app-confirmation-dialog',
  imports: [MatDialogModule, MatButtonModule],
  template: `
    <h2 mat-dialog-title>{{ donnees.titre }}</h2>

    <mat-dialog-content>
      <p>{{ donnees.message }}</p>
    </mat-dialog-content>

    <mat-dialog-actions align="end">
      <button matButton type="button" [mat-dialog-close]="false">Annuler</button>
      <button
        matButton="filled"
        type="button"
        [mat-dialog-close]="true"
        [class.destructif]="donnees.destructif"
      >
        {{ donnees.libelleConfirmation ?? 'Confirmer' }}
      </button>
    </mat-dialog-actions>
  `,
  styles: `
    p {
      margin: 0;
    }

    .destructif {
      --mat-filled-button-container-color: var(--mat-sys-error);
      --mat-filled-button-label-text-color: var(--mat-sys-on-error);
    }
  `,
})
export class ConfirmationDialog {
  protected readonly donnees = inject<DonneesConfirmation>(MAT_DIALOG_DATA);
}
