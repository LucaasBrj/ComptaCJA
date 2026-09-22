import { HttpErrorResponse } from '@angular/common/http';
import { Component, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { MatButtonModule } from '@angular/material/button';
import { MatCheckboxModule } from '@angular/material/checkbox';
import { MAT_DIALOG_DATA, MatDialogModule, MatDialogRef } from '@angular/material/dialog';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { DocumentApiService } from '../../../core/http/document-api.service';
import { messageErreur } from '../../../core/http/violation';

export interface AnnexeAJoindre {
  readonly id: string;
  readonly numero: string;
}

export interface DonneesEnvoiEmail {
  readonly id: string;
  readonly destinataire: string;
  readonly sujet: string;
  readonly corps: string;
  readonly nomFichier: string;
  readonly avertissement: string | null;
  readonly annexes: readonly AnnexeAJoindre[];
}

@Component({
  selector: 'app-envoi-email-dialog',
  imports: [
    ReactiveFormsModule,
    MatDialogModule,
    MatButtonModule,
    MatCheckboxModule,
    MatFormFieldModule,
    MatInputModule,
  ],
  templateUrl: './envoi-email-dialog.html',
  styleUrl: './envoi-email-dialog.scss',
})
export class EnvoiEmailDialog {
  private readonly api = inject(DocumentApiService);
  private readonly fb = inject(FormBuilder);
  private readonly dialogRef = inject(MatDialogRef<EnvoiEmailDialog, boolean>);
  protected readonly donnees = inject<DonneesEnvoiEmail>(MAT_DIALOG_DATA);

  protected readonly envoi = signal(false);
  protected readonly erreur = signal<string | null>(null);

  protected readonly formulaire = this.fb.nonNullable.group({
    destinataire: [this.donnees.destinataire, [Validators.required, Validators.email]],
    sujet: [this.donnees.sujet, Validators.required],
    corps: [this.donnees.corps, Validators.required],
  });

  protected readonly annexesCochees = this.fb.nonNullable.array(
    this.donnees.annexes.map(() => this.fb.nonNullable.control(true)),
  );

  protected fermer(): void {
    this.dialogRef.close(false);
  }

  protected envoyer(): void {
    if (this.formulaire.invalid || this.envoi()) {
      this.formulaire.markAllAsTouched();
      return;
    }

    this.envoi.set(true);
    this.erreur.set(null);
    const annexes = this.donnees.annexes
      .filter((_, index) => this.annexesCochees.at(index).value)
      .map((annexe) => annexe.id);
    this.api.envoyerEmail(this.donnees.id, { ...this.formulaire.getRawValue(), annexes }).subscribe({
      next: () => this.dialogRef.close(true),
      error: (erreur: HttpErrorResponse) => {
        this.envoi.set(false);
        this.erreur.set(messageErreur(erreur));
      },
    });
  }
}
