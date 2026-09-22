import { CurrencyPipe, DatePipe } from '@angular/common';
import { Component, inject, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { FormsModule } from '@angular/forms';
import { MatButtonModule } from '@angular/material/button';
import { MatCardModule } from '@angular/material/card';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatIconModule } from '@angular/material/icon';
import { MatInputModule } from '@angular/material/input';
import { MatPaginatorModule, PageEvent } from '@angular/material/paginator';
import { MatProgressBarModule } from '@angular/material/progress-bar';
import { MatSelectModule } from '@angular/material/select';
import { MatTableModule } from '@angular/material/table';
import { RouterLink } from '@angular/router';
import { Subject, debounceTime, distinctUntilChanged } from 'rxjs';
import { DocumentApiService } from '../../../core/http/document-api.service';
import { identifiantDepuisIri } from '../../../core/iri';
import {
  LIBELLES_STATUT_DOCUMENT,
  LIBELLES_TYPE_DOCUMENT,
  StatutDocument,
  TypeDocument,
} from '../../../core/models/client.model';
import { DocumentDetail, ResumeClient } from '../../../core/models/document.model';

@Component({
  selector: 'app-documents-liste',
  imports: [
    CurrencyPipe,
    DatePipe,
    FormsModule,
    RouterLink,
    MatCardModule,
    MatTableModule,
    MatPaginatorModule,
    MatFormFieldModule,
    MatInputModule,
    MatSelectModule,
    MatButtonModule,
    MatIconModule,
    MatProgressBarModule,
  ],
  templateUrl: './documents-liste.html',
  styleUrl: './documents-liste.scss',
})
export class DocumentsListe {
  private readonly api = inject(DocumentApiService);

  protected readonly colonnes = ['numero', 'type', 'client', 'dateEmission', 'montantTtc', 'statut'];
  protected readonly documents = signal<readonly DocumentDetail[]>([]);
  protected readonly total = signal(0);
  protected readonly chargement = signal(false);
  protected readonly libellesType = LIBELLES_TYPE_DOCUMENT;
  protected readonly libellesStatut = LIBELLES_STATUT_DOCUMENT;
  protected readonly types: readonly TypeDocument[] = ['DEVIS', 'FACTURE'];
  protected readonly statuts = Object.keys(LIBELLES_STATUT_DOCUMENT) as StatutDocument[];

  protected recherche = '';
  protected type = '';
  protected statut = '';
  protected page = 0;
  protected parPage = 30;

  private readonly rechercheSaisie = new Subject<string>();

  constructor() {
    this.rechercheSaisie
      .pipe(debounceTime(300), distinctUntilChanged(), takeUntilDestroyed())
      .subscribe((terme) => {
        this.recherche = terme;
        this.page = 0;
        this.charger();
      });

    this.charger();
  }

  protected surSaisieRecherche(terme: string): void {
    this.rechercheSaisie.next(terme);
  }

  protected filtrer(): void {
    this.page = 0;
    this.charger();
  }

  protected surPagination(evenement: PageEvent): void {
    this.page = evenement.pageIndex;
    this.parPage = evenement.pageSize;
    this.charger();
  }

  protected identifiant(document: DocumentDetail): string {
    return identifiantDepuisIri(document);
  }

  protected libelleType(type: string): string {
    return this.libellesType[type as TypeDocument] ?? type;
  }

  protected libelleStatut(statut: string): string {
    return this.libellesStatut[statut as StatutDocument] ?? statut;
  }

  protected nomClient(document: DocumentDetail): string {
    const client = document.client as ResumeClient | string;

    return typeof client === 'string' ? client : (client.nomAffichage ?? client.numeroClient ?? '');
  }

  private charger(): void {
    this.chargement.set(true);
    this.api
      .lister({
        recherche: this.recherche,
        type: this.type,
        statut: this.statut,
        page: this.page,
        parPage: this.parPage,
      })
      .subscribe({
        next: (page) => {
          this.documents.set(page.member);
          this.total.set(page.totalItems);
          this.chargement.set(false);
        },
        error: () => this.chargement.set(false),
      });
  }
}
