import { Component } from '@angular/core';
import { MatButtonModule } from '@angular/material/button';
import { MatDialog, MatDialogModule } from '@angular/material/dialog';
import { CanDeactivateFn } from '@angular/router';
import { map, Observable } from 'rxjs';

export interface PageReglages {
  confirmerDepart(): boolean | Observable<boolean>;
}

export const confirmerAbandonReglages: CanDeactivateFn<PageReglages> = (page) => page.confirmerDepart();

export function demanderAbandon(dialog: MatDialog, modifie: boolean): boolean | Observable<boolean> {
  if (!modifie) {
    return true;
  }

  return dialog
    .open<ConfirmationQuitterDialog, undefined, boolean>(ConfirmationQuitterDialog)
    .afterClosed()
    .pipe(map((quitte) => quitte === true));
}

@Component({
  selector: 'app-confirmation-quitter',
  imports: [MatDialogModule, MatButtonModule],
  template: `
    <h2 mat-dialog-title>Modifications non enregistrées</h2>
    <mat-dialog-content>
      <p>Les changements de cette page seront perdus.</p>
    </mat-dialog-content>
    <mat-dialog-actions align="end">
      <button matButton type="button" mat-dialog-close>Annuler</button>
      <button matButton="filled" type="button" [mat-dialog-close]="true">Quitter</button>
    </mat-dialog-actions>
  `,
})
class ConfirmationQuitterDialog {}
