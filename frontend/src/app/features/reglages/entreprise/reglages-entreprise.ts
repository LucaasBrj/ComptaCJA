import { HttpErrorResponse } from '@angular/common/http';
import { Component, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { MatButtonModule } from '@angular/material/button';
import { MatCardModule } from '@angular/material/card';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatIconModule } from '@angular/material/icon';
import { MatInputModule } from '@angular/material/input';
import { MatProgressBarModule } from '@angular/material/progress-bar';
import { MatSelectModule } from '@angular/material/select';
import { EntrepriseApiService } from '../../../core/http/entreprise-api.service';
import { appliquerViolations, erreurServeur } from '../../../core/http/violation';
import { RegimeTva } from '../../../core/models/document.model';
import { Adresse } from '../../../core/models/client.model';
import { NotificationService } from '../../../core/notification.service';
import { AdresseChamps } from '../../../shared/adresse-champs/adresse-champs';
import { groupeAdresse } from '../../../shared/adresse-champs/adresse-formulaire';

@Component({
  selector: 'app-reglages-entreprise',
  imports: [
    ReactiveFormsModule,
    AdresseChamps,
    MatCardModule,
    MatFormFieldModule,
    MatInputModule,
    MatSelectModule,
    MatButtonModule,
    MatIconModule,
    MatProgressBarModule,
  ],
  templateUrl: './reglages-entreprise.html',
  styleUrl: './reglages-entreprise.scss',
})
export class ReglagesEntreprise {
  private readonly api = inject(EntrepriseApiService);
  private readonly fb = inject(FormBuilder);
  private readonly notifications = inject(NotificationService);

  protected readonly chargement = signal(true);
  protected readonly enregistrement = signal(false);
  protected readonly erreurServeur = erreurServeur;

  protected readonly formulaire = this.fb.group({
    raisonSociale: this.fb.nonNullable.control('', Validators.required),
    formeJuridique: this.fb.control<string | null>(null),
    siret: this.fb.control<string | null>(null),
    codeApe: this.fb.control<string | null>(null),
    numeroTvaIntracom: this.fb.control<string | null>(null),
    telephone: this.fb.control<string | null>(null),
    email: this.fb.control<string | null>(null, Validators.email),
    adresse: groupeAdresse(this.fb),
    regimeTva: this.fb.nonNullable.control<RegimeTva>('FRANCHISE_293B'),
    assureurNom: this.fb.control<string | null>(null),
    numeroContrat: this.fb.control<string | null>(null),
    couvertureGeographique: this.fb.control<string | null>(null),
    iban: this.fb.control<string | null>(null),
    bic: this.fb.control<string | null>(null),
    banque: this.fb.control<string | null>(null),
    conditionsReglement: this.fb.control<string | null>(null),
    penalitesRetard: this.fb.control<string | null>(null),
    indemniteRecouvrement: this.fb.nonNullable.control('40.00'),
    modeleDevisSujet: this.fb.nonNullable.control('', Validators.required),
    modeleDevisCorps: this.fb.nonNullable.control('', Validators.required),
    modeleFactureSujet: this.fb.nonNullable.control('', Validators.required),
    modeleFactureCorps: this.fb.nonNullable.control('', Validators.required),
  });

  constructor() {
    this.api.lire().subscribe({
      next: (entreprise) => {
        this.formulaire.patchValue(entreprise);
        this.chargement.set(false);
      },
      error: () => this.chargement.set(false),
    });
  }

  protected enregistrer(): void {
    if (this.formulaire.invalid || this.enregistrement()) {
      this.formulaire.markAllAsTouched();
      return;
    }

    this.enregistrement.set(true);
    const valeurs = this.formulaire.getRawValue();
    this.api.modifier({ ...valeurs, adresse: valeurs.adresse as Adresse }).subscribe({
      next: () => {
        this.enregistrement.set(false);
        this.notifications.succes('Réglages enregistrés. Ils apparaîtront sur les prochains PDF.');
      },
      error: (erreur: HttpErrorResponse) => {
        this.enregistrement.set(false);
        appliquerViolations(erreur, this.formulaire);
      },
    });
  }
}
