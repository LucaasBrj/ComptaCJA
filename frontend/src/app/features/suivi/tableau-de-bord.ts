import { CurrencyPipe } from '@angular/common';
import { HttpErrorResponse } from '@angular/common/http';
import { Component, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { MatButtonModule } from '@angular/material/button';
import { MatCardModule } from '@angular/material/card';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatIconModule } from '@angular/material/icon';
import { MatInputModule } from '@angular/material/input';
import { MatProgressBarModule } from '@angular/material/progress-bar';
import { RouterLink } from '@angular/router';
import { messageErreur } from '../../core/http/violation';
import { Compteur, SuiviApiService, TableauDeBord } from '../../core/http/suivi-api.service';
import { NotificationService } from '../../core/notification.service';

@Component({
  selector: 'app-tableau-de-bord',
  imports: [
    CurrencyPipe,
    ReactiveFormsModule,
    RouterLink,
    MatCardModule,
    MatButtonModule,
    MatIconModule,
    MatFormFieldModule,
    MatInputModule,
    MatProgressBarModule,
  ],
  templateUrl: './tableau-de-bord.html',
  styleUrl: './tableau-de-bord.scss',
})
export class TableauDeBordPage {
  private readonly api = inject(SuiviApiService);
  private readonly fb = inject(FormBuilder);
  private readonly notifications = inject(NotificationService);

  protected readonly chargement = signal(true);
  protected readonly synthese = signal<TableauDeBord | null>(null);
  protected readonly cartes: readonly {
    cle: 'enAttente' | 'accepte' | 'paye' | 'enRetard';
    libelle: string;
    icone: string;
    lien: Record<string, string>;
  }[] = [
    { cle: 'enAttente', libelle: 'En attente', icone: 'schedule', lien: { statut: 'ENVOYE' } },
    { cle: 'accepte', libelle: 'Accepté', icone: 'thumb_up', lien: { statut: 'ACCEPTE' } },
    { cle: 'paye', libelle: 'Payé', icone: 'payments', lien: { statut: 'PAYE' } },
    { cle: 'enRetard', libelle: 'En retard', icone: 'warning', lien: { enRetard: '1' } },
  ];

  protected readonly exportForm = this.fb.group({
    du: this.fb.nonNullable.control(this.debutMois(), Validators.required),
    au: this.fb.nonNullable.control(this.aujourdhui(), Validators.required),
  });

  constructor() {
    this.api.tableauDeBord().subscribe({
      next: (synthese) => {
        this.synthese.set(synthese);
        this.chargement.set(false);
      },
      error: () => this.chargement.set(false),
    });
  }

  protected compteur(cle: 'enAttente' | 'accepte' | 'paye' | 'enRetard'): Compteur {
    return this.synthese()?.[cle] ?? { nombre: 0, montantTtc: '0.00' };
  }

  protected telecharger(): void {
    if (this.exportForm.invalid) {
      this.exportForm.markAllAsTouched();
      return;
    }

    const { du, au } = this.exportForm.getRawValue();
    this.api.exportComptable(du, au).subscribe({
      next: (blob) => {
        const url = URL.createObjectURL(blob);
        const lien = document.createElement('a');
        lien.href = url;
        lien.download = `export-comptable-${du}-${au}.csv`;
        lien.click();
        URL.revokeObjectURL(url);
      },
      error: (erreur: HttpErrorResponse) => this.notifications.erreur(messageErreur(erreur)),
    });
  }

  private aujourdhui(): string {
    return this.formater(new Date());
  }

  private debutMois(): string {
    const date = new Date();

    return this.formater(new Date(date.getFullYear(), date.getMonth(), 1));
  }

  private formater(date: Date): string {
    const mois = `${date.getMonth() + 1}`.padStart(2, '0');
    const jour = `${date.getDate()}`.padStart(2, '0');

    return `${date.getFullYear()}-${mois}-${jour}`;
  }
}
