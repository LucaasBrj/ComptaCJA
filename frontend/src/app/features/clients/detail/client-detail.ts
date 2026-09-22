import { CurrencyPipe, DatePipe } from '@angular/common';
import { Component, inject, input, signal } from '@angular/core';
import { MatButtonModule } from '@angular/material/button';
import { MatCardModule } from '@angular/material/card';
import { MatChipsModule } from '@angular/material/chips';
import { MatDialog, MatDialogModule } from '@angular/material/dialog';
import { MatDividerModule } from '@angular/material/divider';
import { MatIconModule } from '@angular/material/icon';
import { MatListModule } from '@angular/material/list';
import { MatMenuModule } from '@angular/material/menu';
import { MatProgressBarModule } from '@angular/material/progress-bar';
import { MatSlideToggleModule } from '@angular/material/slide-toggle';
import { MatTableModule } from '@angular/material/table';
import { MatTabsModule } from '@angular/material/tabs';
import { MatTooltipModule } from '@angular/material/tooltip';
import { Router, RouterLink } from '@angular/router';
import { ConfirmationDialog } from '../../../shared/confirmation-dialog/confirmation-dialog';
import { ClientApiService } from '../../../core/http/client-api.service';
import { identifiantDepuisIri } from '../../../core/iri';
import { NotificationService } from '../../../core/notification.service';
import {
  Chantier,
  Client,
  Document as DocumentHistorique,
  LIBELLES_STATUT_DOCUMENT,
  LIBELLES_TYPE_DOCUMENT,
  TypeDocument,
  adresseEnUneLigne,
} from '../../../core/models/client.model';
import { ChantierDialog, ResultatChantierDialog } from '../chantier-dialog/chantier-dialog';

@Component({
  selector: 'app-client-detail',
  imports: [
    RouterLink,
    DatePipe,
    CurrencyPipe,
    MatCardModule,
    MatTabsModule,
    MatListModule,
    MatTableModule,
    MatButtonModule,
    MatIconModule,
    MatChipsModule,
    MatDividerModule,
    MatMenuModule,
    MatDialogModule,
    MatProgressBarModule,
    MatSlideToggleModule,
    MatTooltipModule,
  ],
  templateUrl: './client-detail.html',
  styleUrl: './client-detail.scss',
})
export class ClientDetail {
  private readonly api = inject(ClientApiService);
  private readonly dialog = inject(MatDialog);
  private readonly router = inject(Router);
  private readonly notifications = inject(NotificationService);

  readonly id = input.required<string>();

  protected readonly client = signal<Client | null>(null);
  protected readonly chargement = signal(true);

  protected readonly colonnesDocuments = ['numero', 'type', 'dateEmission', 'montantTtc', 'statut'];
  protected readonly adresseEnUneLigne = adresseEnUneLigne;
  protected readonly identifiant = identifiantDepuisIri;

  protected readonly typesRecap: readonly TypeDocument[] = [
    'DEVIS',
    'FACTURE',
    'FACTURE_ACOMPTE',
    'ANNEXE_DEBOURS',
  ];

  protected libelleType(document: DocumentHistorique): string {
    return LIBELLES_TYPE_DOCUMENT[document.type];
  }

  protected libelleTypeCode(type: TypeDocument): string {
    return LIBELLES_TYPE_DOCUMENT[type];
  }

  protected nombreParType(documents: readonly DocumentHistorique[], type: TypeDocument): number {
    return documents.filter((document) => document.type === type).length;
  }

  protected ttcParType(documents: readonly DocumentHistorique[], type: TypeDocument): number {
    return documents
      .filter((document) => document.type === type)
      .reduce((somme, document) => somme + Number(document.montantTtc), 0);
  }

  protected enRetard(document: DocumentHistorique): boolean {
    if (!document.dateEcheance || ['PAYE', 'ANNULE', 'REFUSE', 'BROUILLON'].includes(document.statut)) {
      return false;
    }

    const [annee, mois, jour] = document.dateEcheance.slice(0, 10).split('-').map(Number);
    const echeance = new Date(annee, mois - 1, jour);
    const aujourdhui = new Date();
    aujourdhui.setHours(0, 0, 0, 0);

    return echeance < aujourdhui;
  }

  protected libelleStatut(document: DocumentHistorique): string {
    return LIBELLES_STATUT_DOCUMENT[document.statut];
  }

  ngOnInit(): void {
    this.charger();
  }

  protected ajouterChantier(): void {
    this.ouvrirDialogChantier(null);
  }

  protected modifierChantier(chantier: Chantier): void {
    this.ouvrirDialogChantier(chantier);
  }

  protected basculerActivite(chantier: Chantier, actif: boolean): void {
    this.api.modifierChantier(this.identifiant(chantier), { actif }).subscribe(() => {
      this.notifications.succes(actif ? 'Chantier réactivé.' : 'Chantier désactivé.');
      this.charger();
    });
  }

  protected supprimerChantier(chantier: Chantier): void {
    this.dialog
      .open(ConfirmationDialog, {
        data: {
          titre: 'Supprimer ce chantier ?',
          message: `« ${chantier.libelle} » sera définitivement retiré de la fiche client.`,
          libelleConfirmation: 'Supprimer',
          destructif: true,
        },
      })
      .afterClosed()
      .subscribe((confirme) => {
        if (confirme !== true) {
          return;
        }

        this.api.supprimerChantier(this.identifiant(chantier)).subscribe(() => {
          this.notifications.succes('Chantier supprimé.');
          this.charger();
        });
      });
  }

  protected supprimerClient(): void {
    const client = this.client();

    if (client === null) {
      return;
    }

    this.dialog
      .open(ConfirmationDialog, {
        data: {
          titre: 'Supprimer ce client ?',
          message: `La fiche ${client.numeroClient} et ses ${client.chantiers?.length ?? 0} chantier(s) seront supprimés. Le numéro de client reste consommé dans la séquence.`,
          libelleConfirmation: 'Supprimer',
          destructif: true,
        },
      })
      .afterClosed()
      .subscribe((confirme) => {
        if (confirme !== true) {
          return;
        }

        this.api.supprimer(this.id()).subscribe(() => {
          this.notifications.succes('Client supprimé.');
          void this.router.navigate(['/clients']);
        });
      });
  }

  private ouvrirDialogChantier(chantier: Chantier | null): void {
    const client = this.client();

    if (client === null) {
      return;
    }

    this.dialog
      .open(ChantierDialog, {
        data: { chantier, iriClient: client['@id'] },
        width: '40rem',
        maxWidth: '95vw',
      })
      .afterClosed()
      .subscribe((resultat: ResultatChantierDialog | undefined) => {
        if (resultat?.enregistre === true) {
          this.charger();
        }
      });
  }

  private charger(): void {
    this.chargement.set(true);

    this.api.recuperer(this.id()).subscribe({
      next: (client) => {
        this.client.set(client);
        this.chargement.set(false);
      },
      error: () => {
        this.chargement.set(false);
        void this.router.navigate(['/clients']);
      },
    });
  }
}
