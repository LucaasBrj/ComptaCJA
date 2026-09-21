import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';
import { CollectionHydra } from '../models/api.model';
import { Fournisseur } from '../models/fournisseur.model';

export interface CriteresFournisseurs {
  readonly nom?: string;
  readonly page: number;
  readonly parPage: number;
}

@Injectable({ providedIn: 'root' })
export class FournisseurApiService {
  private readonly http = inject(HttpClient);

  lister(criteres: CriteresFournisseurs): Observable<CollectionHydra<Fournisseur>> {
    let parametres = new HttpParams()
      .set('page', criteres.page + 1)
      .set('itemsPerPage', criteres.parPage);

    if (criteres.nom) {
      parametres = parametres.set('nom', criteres.nom);
    }

    return this.http.get<CollectionHydra<Fournisseur>>('/api/fournisseurs', { params: parametres });
  }

  creer(fournisseur: Fournisseur): Observable<Fournisseur> {
    return this.http.post<Fournisseur>('/api/fournisseurs', fournisseur, {
      headers: { 'Content-Type': 'application/ld+json' },
    });
  }

  modifier(id: string, modifications: Partial<Fournisseur>): Observable<Fournisseur> {
    return this.http.patch<Fournisseur>(`/api/fournisseurs/${id}`, modifications, {
      headers: { 'Content-Type': 'application/merge-patch+json' },
    });
  }

  supprimer(id: string): Observable<void> {
    return this.http.delete<void>(`/api/fournisseurs/${id}`);
  }
}
