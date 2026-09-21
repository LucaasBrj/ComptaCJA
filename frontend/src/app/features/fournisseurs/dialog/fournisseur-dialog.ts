import { HttpErrorResponse } from '@angular/common/http';
import { Component, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { MatButtonModule } from '@angular/material/button';
import { MatCheckboxModule } from '@angular/material/checkbox';
import { MAT_DIALOG_DATA, MatDialogModule, MatDialogRef } from '@angular/material/dialog';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { FournisseurApiService } from '../../../core/http/fournisseur-api.service';
import { identifiantDepuisIri } from '../../../core/iri';
import { appliquerViolations, erreurServeur } from '../../../core/http/violation';
import { NotificationService } from '../../../core/notification.service';
import { Fournisseur } from '../../../core/models/fournisseur.model';
import { AdresseChamps } from '../../../shared/adresse-champs/adresse-champs';
import { groupeAdresse } from '../../../shared/adresse-champs/adresse-formulaire';

interface DonneesFournisseurDialog {
  readonly fournisseur: Fournisseur | null;
}

@Component({
  selector: 'app-fournisseur-dialog',
  imports: [
    ReactiveFormsModule,
    AdresseChamps,
    MatDialogModule,
    MatFormFieldModule,
    MatInputModule,
    MatButtonModule,
    MatCheckboxModule,
  ],
  templateUrl: './fournisseur-dialog.html',
  styleUrl: './fournisseur-dialog.scss',
})
export class FournisseurDialog {
  private readonly api = inject(FournisseurApiService);
  private readonly notifications = inject(NotificationService);
  private readonly reference = inject<MatDialogRef<FournisseurDialog, boolean>>(MatDialogRef);
  private readonly donnees = inject<DonneesFournisseurDialog>(MAT_DIALOG_DATA);
  private readonly fb = inject(FormBuilder);

  private readonly existant = this.donnees.fournisseur;

  protected readonly modeEdition = this.existant !== null;
  protected readonly enregistrement = signal(false);
  protected readonly erreurServeur = erreurServeur;

  protected readonly formulaire = this.fb.group({
    nom: this.fb.control<string | null>(this.existant?.nom ?? null, Validators.required),
    contactNom: this.fb.control<string | null>(this.existant?.contactNom ?? null),
    telephone: this.fb.control<string | null>(this.existant?.telephone ?? null),
    email: this.fb.control<string | null>(this.existant?.email ?? null, Validators.email),
    siteWeb: this.fb.control<string | null>(this.existant?.siteWeb ?? null),
    siret: this.fb.control<string | null>(this.existant?.siret ?? null),
    adresse: groupeAdresse(this.fb, this.existant?.adresse),
    notes: this.fb.control<string | null>(this.existant?.notes ?? null),
    actif: this.fb.nonNullable.control(this.existant?.actif ?? true),
  });

  protected soumettre(): void {
    if (this.formulaire.invalid || this.enregistrement()) {
      this.formulaire.markAllAsTouched();

      return;
    }

    this.enregistrement.set(true);
    const valeurs = this.formulaire.getRawValue() as Fournisseur;

    const requete = this.existant
      ? this.api.modifier(identifiantDepuisIri(this.existant), valeurs)
      : this.api.creer(valeurs);

    requete.subscribe({
      next: () => {
        this.enregistrement.set(false);
        this.notifications.succes(this.existant ? 'Fournisseur mis à jour.' : 'Fournisseur ajouté.');
        this.reference.close(true);
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
