import { HttpErrorResponse } from '@angular/common/http';
import { Component, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { MatButtonModule } from '@angular/material/button';
import { MatCardModule } from '@angular/material/card';
import { MatDialog } from '@angular/material/dialog';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatProgressBarModule } from '@angular/material/progress-bar';
import { Observable } from 'rxjs';
import { EntrepriseApiService } from '../../../core/http/entreprise-api.service';
import { appliquerViolations } from '../../../core/http/violation';
import { NotificationService } from '../../../core/notification.service';
import { demanderAbandon } from '../confirmer-abandon';

@Component({
  selector: 'app-reglages-emails',
  imports: [
    ReactiveFormsModule,
    MatCardModule,
    MatFormFieldModule,
    MatInputModule,
    MatButtonModule,
    MatProgressBarModule,
  ],
  templateUrl: './reglages-emails.html',
  styleUrl: './reglages-emails.scss',
})
export class ReglagesEmails {
  private readonly api = inject(EntrepriseApiService);
  private readonly fb = inject(FormBuilder);
  private readonly notifications = inject(NotificationService);
  private readonly dialog = inject(MatDialog);

  protected readonly jetons = ['client', 'numero', 'objet', 'montant', 'echeance', 'entreprise', 'debours'] as const;
  protected readonly chargement = signal(true);
  protected readonly enregistrement = signal(false);

  protected readonly formulaire = this.fb.group({
    modeleDevisSujet: this.fb.nonNullable.control('', Validators.required),
    modeleDevisCorps: this.fb.nonNullable.control('', Validators.required),
    modeleFactureSujet: this.fb.nonNullable.control('', Validators.required),
    modeleFactureCorps: this.fb.nonNullable.control('', Validators.required),
  });

  constructor() {
    this.api.lire().subscribe({
      next: (entreprise) => {
        this.formulaire.patchValue(entreprise, { emitEvent: false });
        this.formulaire.markAsPristine();
        this.chargement.set(false);
      },
      error: () => this.chargement.set(false),
    });
  }

  confirmerDepart(): boolean | Observable<boolean> {
    return demanderAbandon(this.dialog, this.formulaire.dirty);
  }

  protected libelleJeton(jeton: string): string {
    return `{{${jeton}}}`;
  }

  protected inserer(jeton: string): void {
    const champ = document.activeElement;
    if (!(champ instanceof HTMLInputElement || champ instanceof HTMLTextAreaElement)) {
      return;
    }

    const controle = this.formulaire.get(champ.name);
    if (!controle) {
      return;
    }

    const texte = this.libelleJeton(jeton);
    const debut = champ.selectionStart ?? champ.value.length;
    const fin = champ.selectionEnd ?? debut;
    controle.setValue(champ.value.slice(0, debut) + texte + champ.value.slice(fin));
    controle.markAsDirty();
    const curseur = debut + texte.length;
    queueMicrotask(() => {
      champ.focus();
      champ.setSelectionRange(curseur, curseur);
    });
  }

  protected enregistrer(): void {
    if (this.formulaire.invalid || this.enregistrement()) {
      this.formulaire.markAllAsTouched();
      return;
    }

    this.enregistrement.set(true);
    this.api.modifier(this.formulaire.getRawValue()).subscribe({
      next: () => {
        this.enregistrement.set(false);
        this.formulaire.markAsPristine();
        this.notifications.succes('Modèles enregistrés. Ils seront proposés aux prochains envois.');
      },
      error: (erreur: HttpErrorResponse) => {
        this.enregistrement.set(false);
        appliquerViolations(erreur, this.formulaire);
      },
    });
  }
}
