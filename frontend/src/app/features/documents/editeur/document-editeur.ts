import { CurrencyPipe } from '@angular/common';
import { HttpErrorResponse } from '@angular/common/http';
import { Component, inject, input, OnInit, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { FormArray, FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { MatAutocompleteModule } from '@angular/material/autocomplete';
import { MatButtonModule } from '@angular/material/button';
import { MatCardModule } from '@angular/material/card';
import { MatNativeDateModule } from '@angular/material/core';
import { MatDatepickerModule } from '@angular/material/datepicker';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatIconModule } from '@angular/material/icon';
import { MatInputModule } from '@angular/material/input';
import { MatProgressBarModule } from '@angular/material/progress-bar';
import { MatSelectModule } from '@angular/material/select';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { CdkDragDrop, DragDropModule, moveItemInArray } from '@angular/cdk/drag-drop';
import { Subject, debounceTime, distinctUntilChanged, switchMap } from 'rxjs';
import { ClientApiService } from '../../../core/http/client-api.service';
import { DocumentApiService, PayloadDocument } from '../../../core/http/document-api.service';
import { EntrepriseApiService } from '../../../core/http/entreprise-api.service';
import { PrestationApiService } from '../../../core/http/prestation-api.service';
import { appliquerViolations, erreurServeur } from '../../../core/http/violation';
import { identifiantDepuisIri } from '../../../core/iri';
import { Chantier, Client, LIBELLES_STATUT_DOCUMENT, TypeDocument } from '../../../core/models/client.model';
import {
  DocumentDetail,
  LigneDocument,
  Prestation,
  ResumeClient,
  TAUX_TVA,
  TauxTva,
  TotauxDocument,
  TypeLigne,
  UNITES,
  UnitePrestation,
  calculerTotaux,
} from '../../../core/models/document.model';
import { NotificationService } from '../../../core/notification.service';

@Component({
  selector: 'app-document-editeur',
  imports: [
    CurrencyPipe,
    ReactiveFormsModule,
    RouterLink,
    DragDropModule,
    MatCardModule,
    MatFormFieldModule,
    MatInputModule,
    MatSelectModule,
    MatButtonModule,
    MatIconModule,
    MatAutocompleteModule,
    MatDatepickerModule,
    MatNativeDateModule,
    MatProgressBarModule,
  ],
  templateUrl: './document-editeur.html',
  styleUrl: './document-editeur.scss',
})
export class DocumentEditeur implements OnInit {
  private readonly fb = inject(FormBuilder);
  private readonly api = inject(DocumentApiService);
  private readonly clientsApi = inject(ClientApiService);
  private readonly prestationsApi = inject(PrestationApiService);
  private readonly entrepriseApi = inject(EntrepriseApiService);
  private readonly notifications = inject(NotificationService);
  private readonly router = inject(Router);
  private readonly route = inject(ActivatedRoute);

  readonly id = input<string>();

  protected readonly taux = TAUX_TVA;
  protected readonly unites = UNITES;
  protected readonly libellesStatut = LIBELLES_STATUT_DOCUMENT;
  protected readonly erreurServeur = erreurServeur;
  protected readonly chargement = signal(false);
  protected readonly enregistrement = signal(false);
  protected readonly lectureSeule = signal(false);
  protected readonly numero = signal<string | null>(null);
  protected readonly statut = signal<string>('BROUILLON');
  protected readonly clientsTrouves = signal<readonly Client[]>([]);
  protected readonly chantiers = signal<readonly Chantier[]>([]);
  protected readonly prestations = signal<readonly Prestation[]>([]);
  protected readonly franchise = signal(false);
  protected readonly totaux = signal<TotauxDocument>({ ht: 0, tva: 0, ttc: 0, ventilation: [] });

  protected readonly rechercheClient = this.fb.nonNullable.control('');
  private readonly rechercheClient$ = new Subject<string>();

  protected readonly formulaire = this.fb.group({
    type: this.fb.nonNullable.control<TypeDocument>('DEVIS', Validators.required),
    dateEmission: this.fb.control<Date | null>(new Date(), Validators.required),
    dateEcheance: this.fb.control<Date | null>(null),
    objet: this.fb.control<string | null>(null),
    client: this.fb.control<string | null>(null, Validators.required),
    chantier: this.fb.control<string | null>(null),
    lignes: this.fb.array<FormGroup>([]),
  });

  constructor() {
    this.formulaire.valueChanges.pipe(takeUntilDestroyed()).subscribe(() => this.recalculer());
    this.rechercheClient$
      .pipe(
        debounceTime(250),
        distinctUntilChanged(),
        switchMap((terme) => this.clientsApi.lister({ recherche: terme, page: 0, parPage: 8 })),
        takeUntilDestroyed(),
      )
      .subscribe((page) => this.clientsTrouves.set(page.member));

    this.prestationsApi.lister().subscribe((page) => this.prestations.set(page.member));
    this.entrepriseApi.lire().subscribe((entreprise) => {
      this.franchise.set(entreprise.regimeTva === 'FRANCHISE_293B');
    });
  }

  ngOnInit(): void {
    const identifiant = this.id();
    if (identifiant && identifiant !== 'nouveau') {
      this.charger(identifiant);
      return;
    }

    const type = this.route.snapshot.queryParamMap.get('type');
    if (type === 'FACTURE' || type === 'DEVIS') {
      this.formulaire.controls.type.setValue(type);
    }
    const client = this.route.snapshot.queryParamMap.get('client');
    if (client) {
      this.selectionnerClientParId(client);
    }
    this.ajouterLigne('PRESTATION');
  }

  protected get lignes(): FormArray<FormGroup> {
    return this.formulaire.controls.lignes;
  }

  protected surRechercheClient(terme: string): void {
    this.rechercheClient$.next(terme);
  }

  protected choisirDepuisLibelle(nom: string): void {
    const client = this.clientsTrouves().find((item) => item.nomAffichage === nom);
    if (client) {
      this.choisirClient(client);
    }
  }

  protected choisirClient(client: Client): void {
    this.formulaire.controls.client.setValue(client['@id'] ?? `/api/clients/${client.id}`);
    this.rechercheClient.setValue(client.nomAffichage ?? '', { emitEvent: false });
    this.chantiers.set(client.chantiers ?? []);
    this.formulaire.controls.chantier.setValue(null);
    if (!client.chantiers) {
      this.clientsApi.recuperer(identifiantDepuisIri(client)).subscribe((fiche) => {
        this.chantiers.set(fiche.chantiers ?? []);
      });
    }
  }

  protected ajouterLigne(type: TypeLigne): void {
    this.lignes.push(this.groupeLigne(type));
  }

  protected retirerLigne(index: number): void {
    this.lignes.removeAt(index);
  }

  protected deposer(evenement: CdkDragDrop<unknown>): void {
    moveItemInArray(this.lignes.controls, evenement.previousIndex, evenement.currentIndex);
    this.lignes.updateValueAndValidity();
  }

  protected appliquerPrestation(index: number, prestation: Prestation | null): void {
    if (!prestation) {
      this.lignes.at(index).patchValue({ prestation: null });
      return;
    }

    this.lignes.at(index).patchValue({
      prestation: prestation['@id'] ?? null,
      libelle: prestation.libelle,
      unite: prestation.unite,
      tauxTva: prestation.tauxTvaDefaut,
      prixUnitaireHt: prestation.prixUnitaireHtDefaut,
    });
  }

  protected appliquerCodeTva(index: number, evenement: Event): void {
    const saisie = (evenement.target as HTMLInputElement).value.trim();
    if (saisie === '0' || saisie === '1' || saisie === '2' || saisie === '3') {
      this.lignes.at(index).controls['tauxTva'].setValue(saisie);
      (evenement.target as HTMLInputElement).value = saisie;
    }
  }

  protected libelleTaux(code: string | null): string {
    return TAUX_TVA.find((item) => item.code === code)?.libelle ?? '';
  }

  protected libelleStatut(statut: string): string {
    return this.libellesStatut[statut as keyof typeof this.libellesStatut] ?? statut;
  }

  protected mentionFranchise(): boolean {
    return (
      this.franchise() &&
      this.lignes.controls.some((ligne) => ligne.controls['tauxTva'].value === '0')
    );
  }

  protected enregistrer(envoyer = false): void {
    if (this.lectureSeule() || this.formulaire.invalid || this.enregistrement()) {
      this.formulaire.markAllAsTouched();
      return;
    }

    this.enregistrement.set(true);
    const payload = this.payload(envoyer);
    const identifiant = this.id();
    const requete = identifiant ? this.api.modifier(identifiant, payload) : this.api.creer(payload);

    requete.subscribe({
      next: (document) => {
        this.enregistrement.set(false);
        this.notifications.succes(envoyer ? 'Document envoyé et figé.' : 'Document enregistré.');
        const prochain = identifiantDepuisIri(document);
        if (!identifiant) {
          void this.router.navigate(['/documents', prochain]);
        } else {
          this.appliquerDocument(document);
        }
      },
      error: (erreur: HttpErrorResponse) => {
        this.enregistrement.set(false);
        appliquerViolations(erreur, this.formulaire);
      },
    });
  }

  protected telechargerPdf(): void {
    const identifiant = this.id();
    if (!identifiant) {
      return;
    }

    this.api.pdf(identifiant).subscribe((blob) => {
      const url = URL.createObjectURL(blob);
      const lien = document.createElement('a');
      lien.href = url;
      lien.download = `${this.numero() ?? 'document'}.pdf`;
      lien.click();
      URL.revokeObjectURL(url);
    });
  }

  private charger(id: string): void {
    this.chargement.set(true);
    this.api.lire(id).subscribe({
      next: (document) => {
        this.chargement.set(false);
        this.appliquerDocument(document);
      },
      error: () => this.chargement.set(false),
    });
  }

  private appliquerDocument(document: DocumentDetail): void {
    this.numero.set(document.numero ?? null);
    this.statut.set(document.statut);
    this.lectureSeule.set(document.verrouille || document.legacy);
    const client = document.client as ResumeClient;
    this.rechercheClient.setValue(client.nomAffichage ?? '', { emitEvent: false });
    this.formulaire.patchValue({
      type: document.type as TypeDocument,
      dateEmission: document.dateEmission ? this.dateLocale(document.dateEmission) : null,
      dateEcheance: document.dateEcheance ? this.dateLocale(document.dateEcheance) : null,
      objet: document.objet,
      client: typeof document.client === 'string' ? document.client : (client['@id'] ?? null),
      chantier:
        document.chantier && typeof document.chantier !== 'string'
          ? (document.chantier['@id'] ?? null)
          : typeof document.chantier === 'string'
            ? document.chantier
            : null,
    });
    this.lignes.clear();
    for (const ligne of document.lignes ?? []) {
      this.lignes.push(this.groupeLigne(ligne.type, ligne));
    }
    if (client['@id'] || client.id) {
      this.clientsApi.recuperer(identifiantDepuisIri(client)).subscribe((fiche) => {
        this.chantiers.set(fiche.chantiers ?? []);
      });
    }
    if (this.lectureSeule()) {
      this.formulaire.disable();
      this.rechercheClient.disable();
    }
    this.recalculer();
  }

  private selectionnerClientParId(id: string): void {
    this.clientsApi.recuperer(id).subscribe((client) => this.choisirClient(client));
  }

  private groupeLigne(type: TypeLigne, ligne?: LigneDocument): FormGroup {
    return this.fb.group({
      type: this.fb.nonNullable.control(type),
      libelle: this.fb.control(ligne?.libelle ?? '', Validators.required),
      unite: this.fb.control<UnitePrestation | null>(ligne?.unite ?? (type === 'PRESTATION' ? 'M2' : null)),
      quantite: this.fb.control(ligne?.quantite ?? (type === 'PRESTATION' ? '1' : null)),
      prixUnitaireHt: this.fb.control(ligne?.prixUnitaireHt ?? null),
      tauxTva: this.fb.control<TauxTva | null>(ligne?.tauxTva ?? (type === 'PRESTATION' ? '2' : null)),
      prestation: this.fb.control<string | null>(
        typeof ligne?.prestation === 'string' ? ligne.prestation : null,
      ),
    });
  }

  private payload(envoyer: boolean): PayloadDocument {
    const valeurs = this.formulaire.getRawValue();

    return {
      type: valeurs.type,
      statut: envoyer ? 'ENVOYE' : undefined,
      dateEmission: this.formaterDate(valeurs.dateEmission),
      dateEcheance: valeurs.dateEcheance ? this.formaterDate(valeurs.dateEcheance) : null,
      objet: valeurs.objet,
      client: valeurs.client ?? '',
      chantier: valeurs.chantier,
      lignes: valeurs.lignes.map((ligne) => ({
        type: ligne['type'] as TypeLigne,
        libelle: (ligne['libelle'] as string) ?? '',
        unite: ligne['type'] === 'PRESTATION' ? (ligne['unite'] as UnitePrestation) : null,
        quantite: ligne['type'] === 'PRESTATION' ? this.decimal(ligne['quantite']) : null,
        prixUnitaireHt: ligne['type'] === 'PRESTATION' ? this.decimal(ligne['prixUnitaireHt']) : null,
        tauxTva: ligne['type'] === 'PRESTATION' ? (ligne['tauxTva'] as TauxTva) : null,
        prestation: (ligne['prestation'] as string | null) ?? null,
      })),
    };
  }

  private recalculer(): void {
    this.totaux.set(
      calculerTotaux(
        this.lignes.getRawValue().map((ligne) => ({
          type: ligne['type'] as TypeLigne,
          quantite: ligne['quantite'] as string | null,
          prixUnitaireHt: ligne['prixUnitaireHt'] as string | null,
          tauxTva: ligne['tauxTva'] as TauxTva | null,
        })),
      ),
    );
  }

  private dateLocale(valeur: string): Date {
    const [annee, mois, jour] = valeur.slice(0, 10).split('-').map(Number);

    return new Date(annee, (mois ?? 1) - 1, jour);
  }

  private formaterDate(date: Date | null): string {
    if (!date) {
      return '';
    }
    const mois = `${date.getMonth() + 1}`.padStart(2, '0');
    const jour = `${date.getDate()}`.padStart(2, '0');

    return `${date.getFullYear()}-${mois}-${jour}`;
  }

  private decimal(valeur: unknown): string | null {
    if (valeur === null || valeur === undefined || valeur === '') {
      return null;
    }

    return String(valeur).replace(',', '.');
  }
}
