import { HttpErrorResponse } from '@angular/common/http';
import { Component, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { MatButtonModule } from '@angular/material/button';
import { MatCheckboxModule } from '@angular/material/checkbox';
import { MAT_DIALOG_DATA, MatDialogModule, MatDialogRef } from '@angular/material/dialog';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { ClientApiService } from '../../../core/http/client-api.service';
import { identifiantDepuisIri } from '../../../core/iri';
import { appliquerViolations, erreurServeur } from '../../../core/http/violation';
import { NotificationService } from '../../../core/notification.service';
import { Chantier } from '../../../core/models/client.model';
import { AdresseChamps } from '../../../shared/adresse-champs/adresse-champs';
import { groupeAdresse } from '../../../shared/adresse-champs/adresse-formulaire';

export interface DonneesChantierDialog {
  readonly chantier: Chantier | null;
  readonly iriClient: string | undefined;
}

export interface ResultatChantierDialog {
  readonly enregistre: boolean;
}

@Component({
  selector: 'app-chantier-dialog',
  imports: [
    ReactiveFormsModule,
    AdresseChamps,
    MatDialogModule,
    MatFormFieldModule,
    MatInputModule,
    MatButtonModule,
    MatCheckboxModule,
  ],
  templateUrl: './chantier-dialog.html',
  styleUrl: './chantier-dialog.scss',
})
export class ChantierDialog {
  private readonly api = inject(ClientApiService);
  private readonly notifications = inject(NotificationService);
  private readonly reference = inject<MatDialogRef<ChantierDialog, ResultatChantierDialog>>(MatDialogRef);
  private readonly donnees = inject<DonneesChantierDialog>(MAT_DIALOG_DATA);
  private readonly fb = inject(FormBuilder);

  protected readonly modeEdition = this.donnees.chantier !== null;
  protected readonly enregistrement = signal(false);
  protected readonly erreurServeur = erreurServeur;

  protected readonly formulaire = this.fb.group({
    libelle: this.fb.control<string | null>(this.donnees.chantier?.libelle ?? null, Validators.required),
    adresse: groupeAdresse(this.fb, this.donnees.chantier?.adresse),
    notes: this.fb.control<string | null>(this.donnees.chantier?.notes ?? null),
    actif: this.fb.nonNullable.control(this.donnees.chantier?.actif ?? true),
  });

  protected soumettre(): void {
    if (this.formulaire.invalid || this.enregistrement()) {
      this.formulaire.markAllAsTouched();

      return;
    }

    this.enregistrement.set(true);
    const valeurs = this.formulaire.getRawValue();
    const existant = this.donnees.chantier;

    const requete = existant
      ? this.api.modifierChantier(identifiantDepuisIri(existant), valeurs as Partial<Chantier>)
      : this.api.creerChantier({ ...valeurs, client: this.donnees.iriClient } as Chantier);

    requete.subscribe({
      next: () => {
        this.enregistrement.set(false);
        this.notifications.succes(existant ? 'Chantier mis à jour.' : 'Chantier ajouté.');
        this.reference.close({ enregistre: true });
      },
      error: (erreur: HttpErrorResponse) => {
        this.enregistrement.set(false);

        if (erreur.status === 422) {
          const orphelins = appliquerViolations(erreur, this.formulaire);

          if (orphelins.length > 0) {
            this.notifications.erreur(orphelins.join(' '));
          }
        }
      },
    });
  }
}
