import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';

export interface ResultatRecherche {
  readonly iri: string;
  readonly libelle: string;
  readonly sousTitre: string;
}

export interface PageRecherche {
  readonly clients: readonly ResultatRecherche[];
  readonly documents: readonly ResultatRecherche[];
  readonly chantiers: readonly ResultatRecherche[];
}

export interface Compteur {
  readonly nombre: number;
  readonly montantTtc: string;
}

export interface TableauDeBord {
  readonly enAttente: Compteur;
  readonly accepte: Compteur;
  readonly paye: Compteur;
  readonly enRetard: Compteur;
  readonly anomalies: number;
}

@Injectable({ providedIn: 'root' })
export class SuiviApiService {
  private readonly http = inject(HttpClient);

  rechercher(terme: string): Observable<PageRecherche> {
    return this.http.get<PageRecherche>('/api/recherche', {
      params: new HttpParams().set('q', terme),
    });
  }

  tableauDeBord(): Observable<TableauDeBord> {
    return this.http.get<TableauDeBord>('/api/tableau-de-bord');
  }

  exportComptable(du: string, au: string): Observable<Blob> {
    return this.http.get('/api/exports/comptable', {
      params: new HttpParams().set('du', du).set('au', au),
      responseType: 'blob',
    });
  }
}
