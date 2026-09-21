import { Injectable, inject } from '@angular/core';
import { MatSnackBar } from '@angular/material/snack-bar';

@Injectable({ providedIn: 'root' })
export class NotificationService {
  private readonly snackBar = inject(MatSnackBar);

  succes(message: string): void {
    this.snackBar.open(message, 'Fermer', {
      duration: 4000,
      panelClass: 'notification-succes',
    });
  }

  erreur(message: string): void {
    // Les erreurs restent affichees plus longtemps : elles demandent une action.
    this.snackBar.open(message, 'Fermer', {
      duration: 8000,
      panelClass: 'notification-erreur',
    });
  }
}
