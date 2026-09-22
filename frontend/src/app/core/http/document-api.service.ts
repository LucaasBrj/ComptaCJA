import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';
import { CollectionHydra } from '../models/api.model';
import { DocumentDetail, LigneDocument } from '../models/document.model';

export interface CriteresDocuments {
  readonly recherche?: string;
  readonly type?: string;
  readonly statut?: string;
  readonly client?: string;
  readonly page: number;
  readonly parPage: number;
}

export interface PayloadDocument {
  type: string;
  statut?: string;
  dateEmission: string;
  dateEcheance: string | null;
  objet: string | null;
  client: string;
  chantier: string | null;
  lignes: LigneDocument[];
}

@Injectable({ providedIn: 'root' })
export class DocumentApiService {
  private readonly http = inject(HttpClient);

  lister(criteres: CriteresDocuments): Observable<CollectionHydra<DocumentDetail>> {
    let parametres = new HttpParams()
      .set('page', criteres.page + 1)
      .set('itemsPerPage', criteres.parPage);

    if (criteres.recherche) {
      parametres = parametres.set('recherche', criteres.recherche);
    }
    if (criteres.type) {
      parametres = parametres.set('type', criteres.type);
    }
    if (criteres.statut) {
      parametres = parametres.set('statut', criteres.statut);
    }
    if (criteres.client) {
      parametres = parametres.set('client', criteres.client);
    }

    return this.http.get<CollectionHydra<DocumentDetail>>('/api/documents', { params: parametres });
  }

  lire(id: string): Observable<DocumentDetail> {
    return this.http.get<DocumentDetail>(`/api/documents/${id}`);
  }

  creer(payload: PayloadDocument): Observable<DocumentDetail> {
    return this.http.post<DocumentDetail>('/api/documents', payload, {
      headers: { 'Content-Type': 'application/ld+json' },
    });
  }

  modifier(id: string, payload: PayloadDocument | { statut: string }): Observable<DocumentDetail> {
    return this.http.patch<DocumentDetail>(`/api/documents/${id}`, payload, {
      headers: { 'Content-Type': 'application/merge-patch+json' },
    });
  }

  pdf(id: string): Observable<Blob> {
    return this.http.get(`/api/documents/${id}/pdf`, {
      responseType: 'blob',
      headers: { Accept: 'application/pdf' },
    });
  }
}
