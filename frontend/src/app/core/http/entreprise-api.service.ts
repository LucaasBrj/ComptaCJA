import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';
import { Entreprise } from '../models/document.model';

@Injectable({ providedIn: 'root' })
export class EntrepriseApiService {
  private readonly http = inject(HttpClient);

  lire(): Observable<Entreprise> {
    return this.http.get<Entreprise>('/api/entreprise');
  }

  modifier(entreprise: Partial<Entreprise>): Observable<Entreprise> {
    return this.http.patch<Entreprise>('/api/entreprise', entreprise, {
      headers: { 'Content-Type': 'application/merge-patch+json' },
    });
  }
}
