import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';
import { ChampsImport, SessionImport, TypeImport } from '../models/import.model';

@Injectable({ providedIn: 'root' })
export class ImportApiService {
  private readonly http = inject(HttpClient);

  champs(type: TypeImport): Observable<ChampsImport> {
    return this.http.get<ChampsImport>(`/api/imports/champs/${type}`);
  }

  /** Etape 1 : televersement et detection des colonnes. */
  demarrer(fichier: File, type: TypeImport): Observable<SessionImport> {
    const corps = new FormData();
    corps.append('fichier', fichier);
    corps.append('type', type);

    // Aucun Content-Type explicite : le navigateur doit poser la frontiere multipart.
    return this.http.post<SessionImport>('/api/imports', corps);
  }

  /** Etape 2 : association des colonnes du fichier aux champs metier. */
  definirMapping(id: string, mapping: Record<string, string>): Observable<SessionImport> {
    return this.http.post<SessionImport>(`/api/imports/${id}/mapping`, { mapping });
  }

  /** Etape 3 : simulation puis import reel. */
  executer(id: string, aBlanc: boolean): Observable<SessionImport> {
    return this.http.post<SessionImport>(
      `/api/imports/${id}/execution?aBlanc=${aBlanc}`,
      {},
    );
  }
}
