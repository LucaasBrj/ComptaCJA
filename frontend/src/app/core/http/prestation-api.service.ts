import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';
import { CollectionHydra } from '../models/api.model';
import { Prestation } from '../models/document.model';

@Injectable({ providedIn: 'root' })
export class PrestationApiService {
  private readonly http = inject(HttpClient);

  lister(recherche = ''): Observable<CollectionHydra<Prestation>> {
    let parametres = new HttpParams().set('itemsPerPage', 50).set('actif', true);

    if (recherche) {
      parametres = parametres.set('libelle', recherche);
    }

    return this.http.get<CollectionHydra<Prestation>>('/api/prestations', { params: parametres });
  }

  listerToutes(page: number, parPage: number, code: string): Observable<CollectionHydra<Prestation>> {
    let parametres = new HttpParams().set('page', page + 1).set('itemsPerPage', parPage);

    if (code) {
      parametres = parametres.set('code', code);
    }

    return this.http.get<CollectionHydra<Prestation>>('/api/prestations', { params: parametres });
  }

  creer(prestation: Partial<Prestation>): Observable<Prestation> {
    return this.http.post<Prestation>('/api/prestations', prestation, {
      headers: { 'Content-Type': 'application/ld+json' },
    });
  }

  modifier(id: string, prestation: Partial<Prestation>): Observable<Prestation> {
    return this.http.patch<Prestation>(`/api/prestations/${id}`, prestation, {
      headers: { 'Content-Type': 'application/merge-patch+json' },
    });
  }

  supprimer(id: string): Observable<void> {
    return this.http.delete<void>(`/api/prestations/${id}`);
  }
}
