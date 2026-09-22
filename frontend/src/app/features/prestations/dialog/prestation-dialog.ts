import { HttpErrorResponse } from '@angular/common/http';
import { Component, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { MatButtonModule } from '@angular/material/button';
import { MAT_DIALOG_DATA, MatDialogModule, MatDialogRef } from '@angular/material/dialog';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatSelectModule } from '@angular/material/select';
import { PrestationApiService } from '../../../core/http/prestation-api.service';
import { appliquerViolations, erreurServeur } from '../../../core/http/violation';
import { identifiantDepuisIri } from '../../../core/iri';
import { NotificationService } from '../../../core/notification.service';
import { Prestation, TAUX_TVA, TauxTva, UNITES, UnitePrestation } from '../../../core/models/document.model';

@Component({
  selector: 'app-prestation-dialog',
  imports: [
    ReactiveFormsModule,
    MatDialogModule,
    MatFormFieldModule,
    MatInputModule,
    MatSelectModule,
    MatButtonModule,
  ],
  templateUrl: './prestation-dialog.html',
})
export class PrestationDialog {
  private readonly api = inject(PrestationApiService);
  private readonly fb = inject(FormBuilder);
  private readonly notifications = inject(NotificationService);
  private readonly reference = inject<MatDialogRef<PrestationDialog, boolean>>(MatDialogRef);
  private readonly donnees = inject<{ prestation: Prestation | null }>(MAT_DIALOG_DATA);

  protected readonly existante = this.donnees.prestation;
  protected readonly taux = TAUX_TVA;
  protected readonly unites = UNITES;
  protected readonly erreurServeur = erreurServeur;
  protected readonly enregistrement = signal(false);

  protected readonly formulaire = this.fb.group({
    code: this.fb.nonNullable.control(this.existante?.code ?? '', Validators.required),
    libelle: this.fb.nonNullable.control(this.existante?.libelle ?? '', Validators.required),
    unite: this.fb.nonNullable.control<UnitePrestation>(this.existante?.unite ?? 'M2'),
    tauxTvaDefaut: this.fb.nonNullable.control<TauxTva>(this.existante?.tauxTvaDefaut ?? '2'),
    prixUnitaireHtDefaut: this.fb.control<string | null>(this.existante?.prixUnitaireHtDefaut ?? null),
    actif: this.fb.nonNullable.control(this.existante?.actif ?? true),
  });

  protected soumettre(): void {
    if (this.formulaire.invalid || this.enregistrement()) {
      this.formulaire.markAllAsTouched();
      return;
    }

    this.enregistrement.set(true);
    const valeurs = this.formulaire.getRawValue();
    const prix = valeurs.prixUnitaireHtDefaut?.trim();

    const corps = {
      ...valeurs,
      code: valeurs.code.trim().toUpperCase(),
      prixUnitaireHtDefaut: prix ? prix.replace(',', '.') : null,
    };
    const requete = this.existante
      ? this.api.modifier(identifiantDepuisIri(this.existante), corps)
      : this.api.creer(corps);

    requete.subscribe({
      next: () => {
        this.notifications.succes(this.existante ? 'Prestation mise à jour.' : 'Prestation ajoutée.');
        this.reference.close(true);
      },
      error: (erreur: HttpErrorResponse) => {
        this.enregistrement.set(false);
        appliquerViolations(erreur, this.formulaire);
      },
    });
  }
}
