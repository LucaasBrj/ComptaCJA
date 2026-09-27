import { CurrencyPipe } from '@angular/common';
import { HttpErrorResponse } from '@angular/common/http';
import { Component, inject, input, OnInit, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { FormArray, FormBuilder, FormGroup, ReactiveFormsModule, Validators } from '@angular/forms';
import { MatAutocompleteModule } from '@angular/material/autocomplete';
import { MatButtonModule } from '@angular/material/button';
import { MAT_DIALOG_DATA, MatDialog, MatDialogModule } from '@angular/material/dialog';
import { MatCardModule } from '@angular/material/card';
import { MatCheckboxModule } from '@angular/material/checkbox';
import { MatNativeDateModule } from '@angular/material/core';
import { MatDatepickerModule } from '@angular/material/datepicker';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatIconModule } from '@angular/material/icon';
import { MatMenuModule } from '@angular/material/menu';
import { MatInputModule } from '@angular/material/input';
import { MatProgressBarModule } from '@angular/material/progress-bar';
import { MatSelectModule } from '@angular/material/select';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { CdkDragDrop, DragDropModule, moveItemInArray } from '@angular/cdk/drag-drop';
import { Observable, Subject, debounceTime, distinctUntilChanged, skip, switchMap } from 'rxjs';
import { ClientApiService } from '../../../core/http/client-api.service';
import { DocumentApiService, PayloadDocument } from '../../../core/http/document-api.service';
import { EntrepriseApiService } from '../../../core/http/entreprise-api.service';
import { FournisseurApiService } from '../../../core/http/fournisseur-api.service';
import { PrestationApiService } from '../../../core/http/prestation-api.service';
import { appliquerViolations, erreurServeur, messageErreur } from '../../../core/http/violation';
import { identifiantDepuisIri } from '../../../core/iri';
import {
  Chantier,
  Client,
  LIBELLES_STATUT_DOCUMENT,
  LIBELLES_TYPE_DOCUMENT,
  TypeDocument,
} from '../../../core/models/client.model';
import {
  DocumentDetail,
  LigneDocument,
  PieceLiee,
  Prestation,
  ResumeClient,
  TAUX_TVA,
  TauxTva,
  TotauxDocument,
  TypeLigne,
  UNITES,
  UnitePrestation,
  calculerTotaux,
  montantAcompte,
} from '../../../core/models/document.model';
import { Fournisseur } from '../../../core/models/fournisseur.model';
import { NotificationService } from '../../../core/notification.service';
import { EnvoiEmailDialog, texteDebours } from './envoi-email-dialog';

@Component({
  selector: 'app-document-editeur',
  imports: [
    CurrencyPipe,
    ReactiveFormsModule,
    RouterLink,
    DragDropModule,
    MatCardModule,
    MatCheckboxModule,
    MatFormFieldModule,
    MatInputModule,
    MatSelectModule,
    MatButtonModule,
    MatDialogModule,
    MatIconModule,
    MatMenuModule,
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
  private readonly fournisseursApi = inject(FournisseurApiService);
  private readonly entrepriseApi = inject(EntrepriseApiService);
  private readonly notifications = inject(NotificationService);
  private readonly dialog = inject(MatDialog);
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
  protected readonly legacy = signal(false);
  protected readonly numero = signal<string | null>(null);
  protected readonly statut = signal<string>('BROUILLON');
  protected readonly clientsTrouves = signal<readonly Client[]>([]);
  protected readonly chantiers = signal<readonly Chantier[]>([]);
  protected readonly prestations = signal<readonly Prestation[]>([]);
  protected readonly fournisseurs = signal<readonly Fournisseur[]>([]);
  protected readonly franchise = signal(false);
  protected readonly totaux = signal<TotauxDocument>({ ht: 0, tva: 0, ttc: 0, ventilation: [] });
  private readonly emailClient = signal<string | null>(null);
  private readonly nomClient = signal('');
  private readonly montantTtc = signal('0.00');
  private readonly raisonSociale = signal('');
  private readonly piecesLiees = signal<readonly PieceLiee[]>([]);
  private sourceAnnexe: string | null = null;

  protected readonly rechercheClient = this.fb.nonNullable.control('');
  private readonly rechercheClient$ = new Subject<string>();

  protected readonly formulaire = this.fb.group({
    type: this.fb.nonNullable.control<TypeDocument>('DEVIS', Validators.required),
    dateEmission: this.fb.control<Date | null>(new Date(), Validators.required),
    dateEcheance: this.fb.control<Date | null>(null),
    objet: this.fb.control<string | null>(null),
    avecAcompte: this.fb.nonNullable.control(false),
    tauxAcompte: this.fb.nonNullable.control('0'),
    client: this.fb.control<string | null>(null, Validators.required),
    chantier: this.fb.nonNullable.control(''),
    lignes: this.fb.array<FormGroup>([]),
  });

  constructor() {
    this.formulaire.controls.tauxAcompte.disable({ emitEvent: false });
    this.formulaire.controls.avecAcompte.valueChanges.pipe(takeUntilDestroyed()).subscribe((actif) => {
      this.reglerChampAcompte(actif);
    });
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
    this.fournisseursApi
      .lister({ page: 0, parPage: 100 })
      .subscribe((page) => this.fournisseurs.set(page.member));
    this.route.paramMap.pipe(skip(1), takeUntilDestroyed()).subscribe((params) => {
      const identifiant = params.get('id');
      if (identifiant) {
        this.charger(identifiant);
      }
    });
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
    if (type === 'ANNEXE_DEBOURS') {
      this.ouvrirBrouillonAnnexe();
      return;
    }
    if (type === 'FACTURE' || type === 'DEVIS') {
      this.formulaire.controls.type.setValue(type);
    }
    const client = this.route.snapshot.queryParamMap.get('client');
    if (client) {
      this.selectionnerClientParId(client);
    }
    this.ajouterLigne('PRESTATION');
  }

  protected titreEntete(): string {
    switch (this.formulaire.controls.type.value) {
      case 'FACTURE':
        return 'Nouvelle facture';
      case 'ANNEXE_DEBOURS':
        return 'Nouvelle annexe de débours';
      default:
        return 'Nouveau devis';
    }
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

  protected peutEnvoyer(): boolean {
    const type = this.formulaire.controls.type.value;

    return (
      !!this.id() &&
      !!this.numero() &&
      !this.legacy() &&
      (type === 'DEVIS' || type === 'FACTURE' || type === 'FACTURE_ACOMPTE' || type === 'ANNEXE_DEBOURS')
    );
  }

  protected ouvrirEnvoi(): void {
    const identifiant = this.id();
    const numero = this.numero();
    if (!identifiant || !numero) {
      return;
    }

    this.entrepriseApi.lire().subscribe({
      next: (entreprise) => {
        this.raisonSociale.set(entreprise.raisonSociale);
        const type = this.formulaire.controls.type.value;
        const facture = type !== 'DEVIS';
        const reference = this.dialog.open(EnvoiEmailDialog, {
          data: {
            id: identifiant,
            destinataire: this.emailClient() ?? '',
            sujet: this.remplir(facture ? entreprise.modeleFactureSujet : entreprise.modeleDevisSujet),
            corps: this.remplir(facture ? entreprise.modeleFactureCorps : entreprise.modeleDevisCorps),
            nomFichier: `${numero}.pdf`,
            avertissement: this.avertissementEnvoi(),
            annexes:
              type === 'ANNEXE_DEBOURS'
                ? []
                : this.piecesLiees()
                    .filter((piece) => piece.type === 'ANNEXE_DEBOURS' && piece.numero)
                    .map((piece) => ({
                      id: piece.id,
                      numero: piece.numero ?? '',
                      montantTtc: piece.montantTtc,
                    })),
          },
          width: '36rem',
        });
        reference.afterClosed().subscribe((envoye) => {
          if (envoye) {
            this.notifications.succes('Message envoyé.');
            this.charger(identifiant);
          }
        });
      },
      error: (erreur: HttpErrorResponse) => this.notifications.erreur(messageErreur(erreur)),
    });
  }

  protected choisirClient(client: Client): void {
    this.formulaire.controls.client.setValue(client['@id'] ?? `/api/clients/${client.id}`);
    this.rechercheClient.setValue(client.nomAffichage ?? '', { emitEvent: false });
    this.nomClient.set(client.nomAffichage ?? '');
    this.emailClient.set(client.email);
    this.chantiers.set(client.chantiers ?? []);
    this.formulaire.controls.chantier.setValue('');
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

  protected raccourciTva(index: number, evenement: KeyboardEvent): void {
    if (evenement.key !== '0' && evenement.key !== '1' && evenement.key !== '2' && evenement.key !== '3') {
      return;
    }

    this.lignes.at(index).controls['tauxTva'].setValue(evenement.key);
    evenement.preventDefault();
    evenement.stopPropagation();
  }

  protected aAcompte(): boolean {
    return (
      this.formulaire.controls.type.value === 'DEVIS' &&
      this.formulaire.controls.avecAcompte.value &&
      this.tauxPositif(this.formulaire.controls.tauxAcompte.value)
    );
  }

  private reglerChampAcompte(actif: boolean): void {
    if (this.lectureSeule()) {
      return;
    }

    const taux = this.formulaire.controls.tauxAcompte;
    if (actif) {
      taux.enable({ emitEvent: false });
    } else {
      taux.disable({ emitEvent: false });
    }
  }

  private afficherTaux(taux: string | null | undefined): string {
    const nombre = Number(String(taux ?? '0').replace(',', '.'));

    return Number.isFinite(nombre) ? String(nombre).replace('.', ',') : '0';
  }

  private tauxPositif(taux: string | null | undefined): boolean {
    const nombre = Number(String(taux ?? '').replace(',', '.'));

    return Number.isFinite(nombre) && nombre > 0;
  }

  protected apercuAcompte(): number {
    return montantAcompte(this.totaux().ventilation, this.formulaire.controls.tauxAcompte.value);
  }

  protected libelleAcompte(): string {
    return this.afficherTaux(this.formulaire.controls.tauxAcompte.value);
  }

  protected accepter(): void {
    const identifiant = this.id();
    if (!identifiant) {
      return;
    }

    this.enregistrement.set(true);
    this.api.modifier(identifiant, { statut: 'ACCEPTE' }).subscribe({
      next: (document) => {
        this.enregistrement.set(false);
        this.notifications.succes('Devis accepté.');
        this.appliquerDocument(document);
      },
      error: (erreur: HttpErrorResponse) => {
        this.enregistrement.set(false);
        this.notifications.erreur(messageErreur(erreur));
      },
    });
  }

  protected pieceLiee(type: 'FACTURE_ACOMPTE' | 'FACTURE'): PieceLiee | undefined {
    return this.piecesLiees().find((piece) => piece.type === type && piece.statut !== 'ANNULE');
  }

  protected piecesAffichees(): readonly PieceLiee[] {
    const type = this.formulaire.controls.type.value;
    if (type !== 'DEVIS' && type !== 'FACTURE') {
      return [];
    }

    return this.piecesLiees().filter(
      (piece) => piece.type === 'FACTURE_ACOMPTE' || piece.type === 'FACTURE' || piece.type === 'ANNEXE_DEBOURS',
    );
  }

  protected libellePiece(piece: PieceLiee): string {
    if (piece.type === 'FACTURE') {
      return 'Facture de solde';
    }

    return LIBELLES_TYPE_DOCUMENT[piece.type as TypeDocument] ?? piece.type ?? '';
  }

  protected classeStatut(): string {
    switch (this.statut()) {
      case 'ENVOYE':
        return 'envoye';
      case 'ACCEPTE':
      case 'PAYE':
        return 'accepte';
      case 'REFUSE':
      case 'ANNULE':
        return 'refuse';
      case 'EN_RETARD':
        return 'retard';
      default:
        return 'brouillon';
    }
  }

  protected peutPlus(): boolean {
    if (this.numero()) {
      return true;
    }

    if (!this.id() || this.legacy()) {
      return false;
    }

    const type = this.formulaire.controls.type.value;

    return type === 'DEVIS' || type === 'FACTURE';
  }

  protected confirmerEnvoi(): void {
    const phrase = this.avertissementEnvoi();
    if (!phrase) {
      this.enregistrer(true);
      return;
    }

    this.dialog
      .open(ConfirmationVerrouDialog, { data: phrase })
      .afterClosed()
      .subscribe((confirme: boolean | undefined) => {
        if (confirme) {
          this.enregistrer(true);
        }
      });
  }

  protected creerAcompte(): void {
    this.generer(this.api.factureAcompte(this.id() ?? ''), 'Facture d\'acompte créée.');
  }

  protected creerSolde(): void {
    this.generer(this.api.factureSolde(this.id() ?? ''), 'Facture de solde créée.');
  }

  protected creerAnnexe(): void {
    const identifiant = this.id();
    if (!identifiant) {
      return;
    }

    void this.router.navigate(['/documents/nouveau'], {
      queryParams: { type: 'ANNEXE_DEBOURS', source: identifiant },
    });
  }

  protected dupliquer(): void {
    this.generer(this.api.dupliquer(this.id() ?? ''), 'Devis dupliqué.');
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

  private generer(requete: Observable<DocumentDetail>, message: string): void {
    if (!this.id() || this.enregistrement()) {
      return;
    }

    this.enregistrement.set(true);
    requete.subscribe({
      next: (document) => {
        this.enregistrement.set(false);
        this.notifications.succes(message);
        void this.router.navigate(['/documents', identifiantDepuisIri(document)]);
      },
      error: (erreur: HttpErrorResponse) => {
        this.enregistrement.set(false);
        this.notifications.erreur(messageErreur(erreur));
      },
    });
  }

  private charger(id: string): void {
    this.formulaire.enable({ emitEvent: false });
    this.rechercheClient.enable({ emitEvent: false });
    this.lectureSeule.set(false);
    this.legacy.set(false);
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
    this.legacy.set(document.legacy);
    this.lectureSeule.set(document.verrouille || document.legacy);
    const client = document.client as ResumeClient;
    this.nomClient.set(client.nomAffichage ?? '');
    this.montantTtc.set(document.montantTtc);
    this.emailClient.set(null);
    this.piecesLiees.set(document.piecesLieesResume ?? []);
    this.rechercheClient.setValue(client.nomAffichage ?? '', { emitEvent: false });
    this.formulaire.patchValue({
      type: document.type as TypeDocument,
      dateEmission: document.dateEmission ? this.dateLocale(document.dateEmission) : null,
      dateEcheance: document.dateEcheance ? this.dateLocale(document.dateEcheance) : null,
      objet: document.objet,
      avecAcompte: this.tauxPositif(document.type === 'DEVIS' ? document.tauxAcompte : null),
      tauxAcompte: this.afficherTaux(document.tauxAcompte),
      client: typeof document.client === 'string' ? document.client : (client['@id'] ?? null),
      chantier: this.iriChantier(document),
    });
    this.lignes.clear();
    for (const ligne of document.lignes ?? []) {
      this.lignes.push(this.groupeLigne(ligne.type, ligne));
    }
    if (client['@id'] || client.id) {
      this.clientsApi.recuperer(identifiantDepuisIri(client)).subscribe((fiche) => {
        this.chantiers.set(fiche.chantiers ?? []);
        this.nomClient.set(fiche.nomAffichage ?? '');
        this.emailClient.set(fiche.email);
      });
    }
    if (this.lectureSeule()) {
      this.formulaire.disable();
      this.rechercheClient.disable();
    } else {
      this.formulaire.controls.type.disable({ emitEvent: false });
      this.reglerChampAcompte(this.formulaire.controls.avecAcompte.value);
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
      unite: this.fb.control<UnitePrestation | null>(
        ligne?.unite ?? (type === 'PRESTATION' ? 'M2' : type === 'DEBOURS' ? 'U' : type === 'DEDUCTION' ? 'FORFAIT' : null),
      ),
      quantite: this.fb.control(ligne?.quantite ?? (type === 'TEXTE' ? null : '1')),
      prixUnitaireHt: this.fb.control(ligne?.prixUnitaireHt ?? null),
      tauxTva: this.fb.control<TauxTva | null>(
        ligne?.tauxTva ?? (type === 'PRESTATION' || type === 'DEBOURS' ? '2' : null),
      ),
      prestation: this.fb.control<string | null>(
        typeof ligne?.prestation === 'string' ? ligne.prestation : null,
      ),
      fournisseur: this.fb.control<string | null>(this.iriFournisseur(ligne)),
    });
  }

  private ouvrirBrouillonAnnexe(): void {
    this.formulaire.controls.type.setValue('ANNEXE_DEBOURS');
    this.formulaire.controls.type.disable({ emitEvent: false });
    const source = this.route.snapshot.queryParamMap.get('source');
    if (source) {
      this.chargement.set(true);
      this.api.lire(source).subscribe({
        next: (document) => {
          this.chargement.set(false);
          this.reprendreSource(document);
        },
        error: () => this.chargement.set(false),
      });
    }
    this.ajouterLigne('DEBOURS');
  }

  private reprendreSource(document: DocumentDetail): void {
    this.sourceAnnexe = document['@id'] ?? `/api/documents/${identifiantDepuisIri(document)}`;
    const nature = document.type === 'FACTURE' ? 'facture' : 'devis';
    this.formulaire.controls.objet.setValue(`Annexe au ${nature} ${document.numero ?? ''}`.trim());
    const chantier = this.iriChantier(document);
    const client = document.client;
    const clientId = typeof client === 'string' ? identifiantDepuisIri(client) : identifiantDepuisIri(client);
    if (!clientId) {
      return;
    }

    this.clientsApi.recuperer(clientId).subscribe((fiche) => {
      this.choisirClient(fiche);
      this.formulaire.controls.chantier.setValue(chantier);
    });
  }

  private iriChantier(document: DocumentDetail): string {
    if (!document.chantier) {
      return '';
    }

    if (typeof document.chantier === 'string') {
      return document.chantier;
    }

    return document.chantier['@id'] ?? '';
  }

  private payload(envoyer: boolean): PayloadDocument {
    const valeurs = this.formulaire.getRawValue();

    return {
      type: valeurs.type,
      statut: envoyer ? 'ENVOYE' : undefined,
      dateEmission: this.formaterDate(valeurs.dateEmission),
      dateEcheance: valeurs.dateEcheance ? this.formaterDate(valeurs.dateEcheance) : null,
      objet: valeurs.objet,
      tauxAcompte:
        valeurs.type === 'DEVIS' && !valeurs.avecAcompte
          ? '0.00'
          : (this.decimal(valeurs.tauxAcompte) ?? '0.00'),
      client: valeurs.client ?? '',
      chantier: valeurs.chantier || null,
      ...(this.sourceAnnexe && !this.id() ? { documentSource: this.sourceAnnexe } : {}),
      lignes: valeurs.lignes.map((ligne) => ({
        type: ligne['type'] as TypeLigne,
        libelle: (ligne['libelle'] as string) ?? '',
        unite: ligne['type'] === 'TEXTE' ? null : (ligne['unite'] as UnitePrestation),
        quantite: ligne['type'] === 'TEXTE' ? null : this.decimal(ligne['quantite']),
        prixUnitaireHt: ligne['type'] === 'TEXTE' ? null : this.decimal(ligne['prixUnitaireHt']),
        tauxTva:
          ligne['type'] === 'TEXTE' ? null : (ligne['tauxTva'] as TauxTva),
        prestation: (ligne['prestation'] as string | null) ?? null,
        fournisseur: ligne['type'] === 'DEBOURS' ? (ligne['fournisseur'] as string | null) : null,
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

  private iriFournisseur(ligne?: LigneDocument): string | null {
    const fournisseur = ligne?.fournisseur;

    if (!fournisseur) {
      return null;
    }

    return typeof fournisseur === 'string' ? fournisseur : (fournisseur['@id'] ?? null);
  }

  private avertissementEnvoi(): string | null {
    if (this.statut() !== 'BROUILLON') {
      return null;
    }

    const phrase = (article: string, nom: string, accorde: string): string =>
      `Une fois envoyé${accorde}, ${article} ${nom} ne sera plus modifiable.`;

    switch (this.formulaire.controls.type.value) {
      case 'FACTURE':
        return phrase('la', 'facture', 'e');
      case 'FACTURE_ACOMPTE':
        return phrase("la", "facture d'acompte", 'e');
      case 'ANNEXE_DEBOURS':
        return "Une fois envoyée, l'annexe ne sera plus modifiable.";
      default:
        return phrase('le', 'devis', '');
    }
  }

  private remplir(modele: string): string {
    const echeance = this.formulaire.getRawValue().dateEcheance;
    const valeurs: Record<string, string> = {
      client: this.nomClient(),
      numero: this.numero() ?? '',
      objet: this.formulaire.getRawValue().objet ?? '',
      montant: new Intl.NumberFormat('fr-FR', { style: 'currency', currency: 'EUR' }).format(
        Number(this.montantTtc()),
      ),
      echeance: echeance ? new Intl.DateTimeFormat('fr-FR').format(echeance) : '',
      entreprise: this.raisonSociale(),
      debours: texteDebours(
        this.piecesLiees()
          .filter((piece) => piece.type === 'ANNEXE_DEBOURS' && piece.numero)
          .map((piece) => ({ numero: piece.numero ?? '', montantTtc: piece.montantTtc })),
      ),
    };

    return modele.replace(/\{\{(\w+)\}\}/g, (_jeton, cle: string) => valeurs[cle] ?? '');
  }

  private decimal(valeur: unknown): string | null {
    if (valeur === null || valeur === undefined || valeur === '') {
      return null;
    }

    return String(valeur).replace(',', '.');
  }
}

@Component({
  selector: 'app-confirmation-verrou',
  imports: [MatDialogModule, MatButtonModule],
  template: `
    <h2 mat-dialog-title>Marquer comme envoyé</h2>
    <mat-dialog-content>
      <p>{{ phrase }}</p>
    </mat-dialog-content>
    <mat-dialog-actions align="end">
      <button matButton type="button" mat-dialog-close>Annuler</button>
      <button matButton="filled" type="button" [mat-dialog-close]="true">Marquer comme envoyé</button>
    </mat-dialog-actions>
  `,
})
class ConfirmationVerrouDialog {
  protected readonly phrase = inject<string>(MAT_DIALOG_DATA);
}
