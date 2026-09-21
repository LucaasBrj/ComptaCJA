import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';
import { CollectionHydra } from '../models/api.model';
import { Chantier, Client } from '../models/client.model';

/** Criteres de la liste des clients, alignes sur les filtres exposes par l'API. */
export interface CriteresClients {
  readonly recherche?: string;
  readonly typologie?: string;
  readonly ville?: string;
  readonly actif?: boolean;
  readonly page: number;
  readonly parPage: number;
  readonly triChamp?: string;
  readonly triSens?: 'asc' | 'desc';
}

@Injectable({ providedIn: 'root' })
export class ClientApiService {
  private readonly http = inject(HttpClient);

  lister(criteres: CriteresClients): Observable<CollectionHydra<Client>> {
    let parametres = new HttpParams()
      .set('page', criteres.page + 1)
      .set('itemsPerPage', criteres.parPage);

    if (criteres.recherche) {
      parametres = parametres.set('recherche', criteres.recherche);
    }

    if (criteres.typologie) {
      parametres = parametres.set('typologie', criteres.typologie);
    }

    if (criteres.ville) {
      parametres = parametres.set('ville', criteres.ville);
    }

    if (criteres.actif !== undefined) {
      parametres = parametres.set('actif', criteres.actif);
    }

    if (criteres.triChamp && criteres.triSens) {
      parametres = parametres.set(`order[${criteres.triChamp}]`, criteres.triSens);
    }

    return this.http.get<CollectionHydra<Client>>('/api/clients', { params: parametres });
  }

  recuperer(id: string): Observable<Client> {
    return this.http.get<Client>(`/api/clients/${id}`);
  }

  creer(client: Client): Observable<Client> {
    return this.http.post<Client>('/api/clients', client, {
      headers: { 'Content-Type': 'application/ld+json' },
    });
  }

  modifier(id: string, modifications: Partial<Client>): Observable<Client> {
    return this.http.patch<Client>(`/api/clients/${id}`, modifications, {
      headers: { 'Content-Type': 'application/merge-patch+json' },
    });
  }

  supprimer(id: string): Observable<void> {
    return this.http.delete<void>(`/api/clients/${id}`);
  }

  creerChantier(chantier: Chantier): Observable<Chantier> {
    return this.http.post<Chantier>('/api/chantiers', chantier, {
      headers: { 'Content-Type': 'application/ld+json' },
    });
  }

  modifierChantier(id: string, modifications: Partial<Chantier>): Observable<Chantier> {
    return this.http.patch<Chantier>(`/api/chantiers/${id}`, modifications, {
      headers: { 'Content-Type': 'application/merge-patch+json' },
    });
  }

  supprimerChantier(id: string): Observable<void> {
    return this.http.delete<void>(`/api/chantiers/${id}`);
  }
}
