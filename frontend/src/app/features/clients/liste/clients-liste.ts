import { Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { MatButtonModule } from '@angular/material/button';
import { MatCardModule } from '@angular/material/card';
import { MatChipsModule } from '@angular/material/chips';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatIconModule } from '@angular/material/icon';
import { MatInputModule } from '@angular/material/input';
import { MatPaginatorModule, PageEvent } from '@angular/material/paginator';
import { MatProgressBarModule } from '@angular/material/progress-bar';
import { MatSelectModule } from '@angular/material/select';
import { MatSort, MatSortModule, Sort } from '@angular/material/sort';
import { MatTableModule } from '@angular/material/table';
import { MatTooltipModule } from '@angular/material/tooltip';
import { Router, RouterLink } from '@angular/router';
import { Subject, debounceTime, distinctUntilChanged } from 'rxjs';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { ClientApiService } from '../../../core/http/client-api.service';
import { Client, TYPOLOGIES, adresseEnUneLigne } from '../../../core/models/client.model';
import { identifiantDepuisIri } from '../../../core/iri';

@Component({
  selector: 'app-clients-liste',
  imports: [
    RouterLink,
    FormsModule,
    MatCardModule,
    MatTableModule,
    MatSortModule,
    MatPaginatorModule,
    MatFormFieldModule,
    MatInputModule,
    MatSelectModule,
    MatButtonModule,
    MatIconModule,
    MatChipsModule,
    MatProgressBarModule,
    MatTooltipModule,
  ],
  templateUrl: './clients-liste.html',
  styleUrl: './clients-liste.scss',
})
export class ClientsListe {
  private readonly api = inject(ClientApiService);
  private readonly router = inject(Router);

  protected readonly typologies = TYPOLOGIES;
  protected readonly colonnes = ['numeroClient', 'nom', 'typologie', 'contact', 'commune', 'actions'];

  protected readonly clients = signal<readonly Client[]>([]);
  protected readonly total = signal(0);
  protected readonly chargement = signal(false);

  protected recherche = '';
  protected typologie = '';
  protected page = 0;
  protected parPage = 25;
  private triChamp = 'numeroClient';
  private triSens: 'asc' | 'desc' = 'asc';

  /** La frappe de l'utilisateur est temporisee pour ne pas inonder l'API. */
  private readonly rechercheSaisie = new Subject<string>();

  protected readonly adresseEnUneLigne = adresseEnUneLigne;
  protected readonly identifiant = identifiantDepuisIri;

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

  protected surChangementTypologie(): void {
    this.page = 0;
    this.charger();
  }

  protected surPagination(evenement: PageEvent): void {
    this.page = evenement.pageIndex;
    this.parPage = evenement.pageSize;
    this.charger();
  }

  protected surTri(tri: Sort): void {
    if (tri.direction === '') {
      return;
    }

    this.triChamp = tri.active;
    this.triSens = tri.direction;
    this.charger();
  }

  protected reinitialiser(sort: MatSort): void {
    this.recherche = '';
    this.typologie = '';
    this.page = 0;
    sort.sort({ id: 'numeroClient', start: 'asc', disableClear: false });
  }

  protected ouvrir(client: Client): void {
    void this.router.navigate(['/clients', this.identifiant(client)]);
  }

  private charger(): void {
    this.chargement.set(true);

    this.api
      .lister({
        recherche: this.recherche || undefined,
        typologie: this.typologie || undefined,
        page: this.page,
        parPage: this.parPage,
        triChamp: this.triChamp,
        triSens: this.triSens,
      })
      .subscribe({
        next: (collection) => {
          this.clients.set(collection.member);
          this.total.set(collection.totalItems);
          this.chargement.set(false);
        },
        error: () => this.chargement.set(false),
      });
  }
}
