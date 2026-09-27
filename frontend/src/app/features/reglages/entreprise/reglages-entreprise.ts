import { HttpErrorResponse } from '@angular/common/http';
import { Component, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { MatButtonModule } from '@angular/material/button';
import { MatCardModule } from '@angular/material/card';
import { MatDialog } from '@angular/material/dialog';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';
import { MatProgressBarModule } from '@angular/material/progress-bar';
import { MatSelectModule } from '@angular/material/select';
import { Observable } from 'rxjs';
import { EntrepriseApiService } from '../../../core/http/entreprise-api.service';
import { appliquerViolations, erreurServeur } from '../../../core/http/violation';
import { Adresse } from '../../../core/models/client.model';
import { RegimeTva } from '../../../core/models/document.model';
import { NotificationService } from '../../../core/notification.service';
import { groupeAdresse } from '../../../shared/adresse-champs/adresse-formulaire';
import { demanderAbandon } from '../confirmer-abandon';

@Component({
  selector: 'app-reglages-entreprise',
  imports: [
    ReactiveFormsModule,
    MatCardModule,
    MatFormFieldModule,
    MatInputModule,
    MatSelectModule,
    MatButtonModule,
    MatProgressBarModule,
  ],
  templateUrl: './reglages-entreprise.html',
  styleUrl: './reglages-entreprise.scss',
})
export class ReglagesEntreprise {
  private readonly api = inject(EntrepriseApiService);
  private readonly fb = inject(FormBuilder);
  private readonly notifications = inject(NotificationService);
  private readonly dialog = inject(MatDialog);

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
    indemniteRecouvrement: this.fb.nonNullable.control('40'),
  });

  constructor() {
    this.api.lire().subscribe({
      next: (entreprise) => {
        this.formulaire.patchValue(
          { ...entreprise, indemniteRecouvrement: this.afficherMontant(entreprise.indemniteRecouvrement) },
          { emitEvent: false },
        );
        this.formulaire.markAsPristine();
        this.chargement.set(false);
      },
      error: () => this.chargement.set(false),
    });
  }

  confirmerDepart(): boolean | Observable<boolean> {
    return demanderAbandon(this.dialog, this.formulaire.dirty);
  }

  protected enregistrer(): void {
    if (this.formulaire.invalid || this.enregistrement()) {
      this.formulaire.markAllAsTouched();
      return;
    }

    this.enregistrement.set(true);
    const valeurs = this.formulaire.getRawValue();
    this.api
      .modifier({
        raisonSociale: valeurs.raisonSociale,
        formeJuridique: valeurs.formeJuridique,
        siret: valeurs.siret,
        codeApe: valeurs.codeApe,
        numeroTvaIntracom: valeurs.numeroTvaIntracom,
        telephone: valeurs.telephone,
        email: valeurs.email,
        adresse: valeurs.adresse as Adresse,
        regimeTva: valeurs.regimeTva,
        assureurNom: valeurs.assureurNom,
        numeroContrat: valeurs.numeroContrat,
        couvertureGeographique: valeurs.couvertureGeographique,
        iban: valeurs.iban,
        bic: valeurs.bic,
        banque: valeurs.banque,
        conditionsReglement: valeurs.conditionsReglement,
        penalitesRetard: valeurs.penalitesRetard,
        indemniteRecouvrement: this.decimal(valeurs.indemniteRecouvrement),
      })
      .subscribe({
        next: (entreprise) => {
          this.enregistrement.set(false);
          this.formulaire.patchValue(
            { indemniteRecouvrement: this.afficherMontant(entreprise.indemniteRecouvrement) },
            { emitEvent: false },
          );
          this.formulaire.markAsPristine();
          this.notifications.succes('Réglages enregistrés. Ils apparaîtront sur les prochains PDF.');
        },
        error: (erreur: HttpErrorResponse) => {
          this.enregistrement.set(false);
          appliquerViolations(erreur, this.formulaire);
        },
      });
  }

  private afficherMontant(valeur: string): string {
    const nombre = Number(String(valeur).replace(',', '.'));

    return Number.isFinite(nombre) ? String(nombre).replace('.', ',') : valeur;
  }

  private decimal(valeur: string): string {
    const nombre = Number(valeur.replace(',', '.'));

    return Number.isFinite(nombre) ? nombre.toFixed(2) : '0.00';
  }
}
