import { Component, computed, inject, signal, viewChild } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { MatButtonModule } from '@angular/material/button';
import { MatCardModule } from '@angular/material/card';
import { MatChipsModule } from '@angular/material/chips';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatIconModule } from '@angular/material/icon';
import { MatProgressBarModule } from '@angular/material/progress-bar';
import { MatSelectModule } from '@angular/material/select';
import { MatStepper, MatStepperModule } from '@angular/material/stepper';
import { MatTableModule } from '@angular/material/table';
import { MatTooltipModule } from '@angular/material/tooltip';
import { RouterLink } from '@angular/router';
import { ImportApiService } from '../../../core/http/import-api.service';
import { identifiantDepuisIri } from '../../../core/iri';
import { NotificationService } from '../../../core/notification.service';
import {
  ChampImport,
  LIBELLES_STATUT_LIGNE,
  LIBELLES_TYPE_IMPORT,
  LigneRapport,
  SessionImport,
  TypeImport,
} from '../../../core/models/import.model';

@Component({
  selector: 'app-import-assistant',
  imports: [
    FormsModule,
    RouterLink,
    MatCardModule,
    MatStepperModule,
    MatFormFieldModule,
    MatSelectModule,
    MatButtonModule,
    MatIconModule,
    MatTableModule,
    MatChipsModule,
    MatProgressBarModule,
    MatTooltipModule,
  ],
  templateUrl: './import-assistant.html',
  styleUrl: './import-assistant.scss',
})
export class ImportAssistant {
  private readonly api = inject(ImportApiService);
  private readonly notifications = inject(NotificationService);

  private readonly stepper = viewChild.required(MatStepper);

  protected readonly libellesType = LIBELLES_TYPE_IMPORT;
  protected readonly typesDisponibles: readonly TypeImport[] = ['CLIENTS', 'HISTORIQUE'];

  protected readonly type = signal<TypeImport>('CLIENTS');
  protected readonly fichier = signal<File | null>(null);
  protected readonly session = signal<SessionImport | null>(null);
  protected readonly champs = signal<readonly ChampImport[]>([]);
  protected readonly mapping = signal<Record<string, string>>({});
  protected readonly enCours = signal(false);
  protected readonly simulationFaite = signal(false);
  protected readonly importTermine = signal(false);

  protected readonly colonnesRapport = ['ligne', 'statut', 'apercu', 'messages'];

  /** Colonnes du fichier, plus l'option "ne pas importer". */
  protected readonly colonnesFichier = computed(() => this.session()?.colonnesDetectees ?? []);

  protected readonly champsObligatoiresManquants = computed(() => {
    const mapping = this.mapping();

    return this.champs()
      .filter((champ) => champ.obligatoire && !mapping[champ.code])
      .map((champ) => champ.libelle);
  });

  protected readonly mappingValide = computed(() => this.champsObligatoiresManquants().length === 0);

  /** Nombre de colonnes du fichier laissees de cote, information utile avant de valider. */
  protected readonly colonnesIgnorees = computed(() => {
    const utilisees = new Set(Object.values(this.mapping()));

    return this.colonnesFichier().filter((colonne) => !utilisees.has(colonne));
  });

  protected readonly lignesEnErreur = computed(
    () => this.session()?.rapport.filter((ligne) => ligne.statut === 'ERREUR') ?? [],
  );

  protected libelleStatutLigne(ligne: LigneRapport): string {
    return LIBELLES_STATUT_LIGNE[ligne.statut];
  }

  protected surSelectionFichier(evenement: Event): void {
    const entree = evenement.target as HTMLInputElement;
    this.fichier.set(entree.files?.[0] ?? null);
  }

  protected televerser(): void {
    const fichier = this.fichier();

    if (fichier === null || this.enCours()) {
      return;
    }

    this.enCours.set(true);
    const type = this.type();

    this.api.demarrer(fichier, type).subscribe({
      next: (session) => {
        this.session.set(session);
        this.mapping.set({ ...session.mapping });
        this.simulationFaite.set(false);
        this.importTermine.set(false);

        this.api.champs(type).subscribe({
          next: (reponse) => {
            this.champs.set(reponse.champs);
            this.enCours.set(false);
            this.stepper().next();
          },
          error: () => this.enCours.set(false),
        });
      },
      error: () => this.enCours.set(false),
    });
  }

  protected definirColonne(codeChamp: string, colonne: string | null): void {
    this.mapping.update((actuel) => {
      const copie = { ...actuel };

      if (colonne === null || colonne === '') {
        delete copie[codeChamp];
      } else {
        copie[codeChamp] = colonne;
      }

      return copie;
    });

    // Modifier le mapping invalide la simulation precedente.
    this.simulationFaite.set(false);
  }

  protected validerMapping(): void {
    const session = this.session();

    if (session === null || !this.mappingValide() || this.enCours()) {
      return;
    }

    this.enCours.set(true);

    this.api.definirMapping(identifiantDepuisIri(session), this.mapping()).subscribe({
      next: (miseAJour) => {
        this.session.set(miseAJour);
        this.enCours.set(false);
        this.stepper().next();
      },
      error: () => this.enCours.set(false),
    });
  }

  protected simuler(): void {
    this.executer(true);
  }

  protected importer(): void {
    this.executer(false);
  }

  protected recommencer(): void {
    this.session.set(null);
    this.fichier.set(null);
    this.mapping.set({});
    this.champs.set([]);
    this.simulationFaite.set(false);
    this.importTermine.set(false);
    this.stepper().reset();
  }

  private executer(aBlanc: boolean): void {
    const session = this.session();

    if (session === null || this.enCours()) {
      return;
    }

    this.enCours.set(true);

    this.api.executer(identifiantDepuisIri(session), aBlanc).subscribe({
      next: (rapport) => {
        this.session.set(rapport);
        this.enCours.set(false);

        if (aBlanc) {
          this.simulationFaite.set(true);
          this.notifications.succes(
            rapport.nbErreurs === 0
              ? `Simulation réussie : ${rapport.nbSucces} ligne(s) prêtes à être importées.`
              : `Simulation terminée avec ${rapport.nbErreurs} erreur(s) à corriger.`,
          );

          return;
        }

        if (rapport.statut === 'TERMINE') {
          this.importTermine.set(true);
          this.notifications.succes(`Import terminé : ${rapport.nbSucces} ligne(s) enregistrées.`);
        } else {
          this.notifications.erreur(
            `Import annulé : ${rapport.nbErreurs} erreur(s). Aucune donnée n'a été enregistrée.`,
          );
        }
      },
      error: () => this.enCours.set(false),
    });
  }
}
