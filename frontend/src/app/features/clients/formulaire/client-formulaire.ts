import { HttpErrorResponse } from '@angular/common/http';
import { Component, computed, inject, input, signal } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import {
  FormBuilder,
  FormGroup,
  ReactiveFormsModule,
  Validators,
} from '@angular/forms';
import { MatButtonModule } from '@angular/material/button';
import { MatCardModule } from '@angular/material/card';
import { MatCheckboxModule } from '@angular/material/checkbox';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatIconModule } from '@angular/material/icon';
import { MatInputModule } from '@angular/material/input';
import { MatProgressBarModule } from '@angular/material/progress-bar';
import { MatSelectModule } from '@angular/material/select';
import { Router, RouterLink } from '@angular/router';
import { NotificationService } from '../../../core/notification.service';
import { ClientApiService } from '../../../core/http/client-api.service';
import { appliquerViolations, erreurServeur } from '../../../core/http/violation';
import {
  CIVILITES,
  Client,
  TYPOLOGIES,
  TypologieClient,
  adresseVide,
} from '../../../core/models/client.model';
import { AdresseChamps } from '../../../shared/adresse-champs/adresse-champs';
import { groupeAdresse } from '../../../shared/adresse-champs/adresse-formulaire';

@Component({
  selector: 'app-client-formulaire',
  imports: [
    ReactiveFormsModule,
    RouterLink,
    AdresseChamps,
    MatCardModule,
    MatFormFieldModule,
    MatInputModule,
    MatSelectModule,
    MatButtonModule,
    MatIconModule,
    MatCheckboxModule,
    MatProgressBarModule,
  ],
  templateUrl: './client-formulaire.html',
  styleUrl: './client-formulaire.scss',
})
export class ClientFormulaire {
  private readonly api = inject(ClientApiService);
  private readonly router = inject(Router);
  private readonly notifications = inject(NotificationService);
  private readonly fb = inject(FormBuilder);

  /** Renseigne par le routeur sur /clients/:id/modifier. */
  readonly id = input<string>();

  protected readonly typologies = TYPOLOGIES;
  protected readonly civilites = CIVILITES;
  protected readonly enregistrement = signal(false);
  protected readonly chargement = signal(false);
  protected readonly numeroClient = signal<string | null>(null);

  protected readonly formulaire: FormGroup = this.fb.nonNullable.group({
    typologie: this.fb.nonNullable.control<TypologieClient>('PARTICULIER', Validators.required),
    civilite: this.fb.control<string | null>(null),
    nom: this.fb.control<string | null>(null),
    prenom: this.fb.control<string | null>(null),
    raisonSociale: this.fb.control<string | null>(null),
    telephone: this.fb.control<string | null>(null),
    email: this.fb.control<string | null>(null, Validators.email),
    siret: this.fb.control<string | null>(null),
    numeroTvaIntracom: this.fb.control<string | null>(null),
    adresseFacturation: groupeAdresse(this.fb),
    notes: this.fb.control<string | null>(null),
    actif: this.fb.nonNullable.control(true),
  });

  /**
   * La typologie pilote l'affichage : le serveur exige raison sociale et SIRET
   * pour un professionnel, nom pour un particulier. Le formulaire reflete cette
   * regle au lieu de laisser l'utilisateur decouvrir l'erreur a l'envoi.
   */
  private readonly typologieChoisie = toSignal(
    this.formulaire.controls['typologie'].valueChanges,
    { initialValue: this.formulaire.getRawValue().typologie as TypologieClient },
  );

  protected readonly estProfessionnel = computed(() => this.typologieChoisie() === 'PROFESSIONNEL');
  protected readonly modeEdition = computed(() => this.id() !== undefined);

  protected readonly erreurServeur = erreurServeur;

  constructor() {
    this.formulaire.controls['typologie'].valueChanges.subscribe((typologie) =>
      this.appliquerRegles(typologie as TypologieClient),
    );
    this.appliquerRegles('PARTICULIER');
  }

  ngOnInit(): void {
    const identifiant = this.id();

    if (identifiant === undefined) {
      return;
    }

    this.chargement.set(true);
    this.api.recuperer(identifiant).subscribe({
      next: (client) => {
        this.numeroClient.set(client.numeroClient ?? null);
        this.appliquerRegles(client.typologie);
        this.formulaire.patchValue({
          ...client,
          adresseFacturation: { ...adresseVide(), ...client.adresseFacturation },
        });
        this.chargement.set(false);
      },
      error: () => {
        this.chargement.set(false);
        void this.router.navigate(['/clients']);
      },
    });
  }

  protected soumettre(): void {
    if (this.formulaire.invalid || this.enregistrement()) {
      this.formulaire.markAllAsTouched();

      return;
    }

    this.enregistrement.set(true);
    const valeurs = this.formulaire.getRawValue() as Client;
    const identifiant = this.id();

    const requete = identifiant
      ? this.api.modifier(identifiant, valeurs)
      : this.api.creer(valeurs);

    requete.subscribe({
      next: (client) => {
        this.enregistrement.set(false);
        this.notifications.succes(
          identifiant
            ? 'Fiche client mise à jour.'
            : `Client ${client.numeroClient} créé.`,
        );
        void this.router.navigate(['/clients', client.id ?? identifiant]);
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

  /**
   * Ajuste les champs obligatoires et vide ceux qui perdent leur sens, pour ne
   * pas envoyer un SIRET reste d'un passage en "professionnel".
   */
  private appliquerRegles(typologie: TypologieClient): void {
    const professionnel = typologie === 'PROFESSIONNEL';
    const nom = this.formulaire.controls['nom'];
    const raisonSociale = this.formulaire.controls['raisonSociale'];
    const siret = this.formulaire.controls['siret'];

    nom.setValidators(professionnel ? [] : [Validators.required]);
    raisonSociale.setValidators(professionnel ? [Validators.required] : []);
    siret.setValidators(
      professionnel ? [Validators.required, Validators.pattern(/^\d{14}$/)] : [],
    );

    if (professionnel) {
      this.formulaire.controls['civilite'].reset(null, { emitEvent: false });
    } else {
      siret.reset(null, { emitEvent: false });
      this.formulaire.controls['numeroTvaIntracom'].reset(null, { emitEvent: false });
      raisonSociale.reset(null, { emitEvent: false });
    }

    nom.updateValueAndValidity({ emitEvent: false });
    raisonSociale.updateValueAndValidity({ emitEvent: false });
    siret.updateValueAndValidity({ emitEvent: false });
  }
}
