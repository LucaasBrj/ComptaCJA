import { Component, input } from '@angular/core';
import { FormGroup, ReactiveFormsModule } from '@angular/forms';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatInputModule } from '@angular/material/input';

/**
 * Groupe de champs d'adresse, partage par la fiche client, le chantier et le
 * fournisseur (l'embeddable Adresse cote API).
 */
@Component({
  selector: 'app-adresse-champs',
  imports: [ReactiveFormsModule, MatFormFieldModule, MatInputModule],
  template: `
    <div [formGroup]="groupe()" class="adresse">
      <mat-form-field class="adresse__ligne1">
        <mat-label>{{ libelleLigne1() }}</mat-label>
        <input matInput formControlName="ligne1" autocomplete="street-address" />
      </mat-form-field>

      <mat-form-field class="adresse__ligne2">
        <mat-label>Complément (bâtiment, étage…)</mat-label>
        <input matInput formControlName="ligne2" />
      </mat-form-field>

      <mat-form-field class="adresse__cp">
        <mat-label>Code postal</mat-label>
        <input matInput formControlName="codePostal" inputmode="numeric" maxlength="5" />
        @if (groupe().controls['codePostal'].hasError('pattern')) {
          <mat-error>5 chiffres attendus.</mat-error>
        }
        @if (groupe().controls['codePostal'].errors?.['serveur']) {
          <mat-error>{{ groupe().controls['codePostal'].errors?.['serveur'] }}</mat-error>
        }
      </mat-form-field>

      <mat-form-field class="adresse__ville">
        <mat-label>Ville</mat-label>
        <input matInput formControlName="ville" />
      </mat-form-field>
    </div>
  `,
  styles: `
    .adresse {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 0.75rem;

      @media (max-width: 899px) {
        grid-template-columns: repeat(2, 1fr);
      }
    }

    .adresse__ligne1,
    .adresse__ligne2 {
      grid-column: span 4;

      @media (max-width: 899px) {
        grid-column: span 2;
      }
    }

    .adresse__cp {
      grid-column: span 1;
    }

    .adresse__ville {
      grid-column: span 3;

      @media (max-width: 899px) {
        grid-column: span 1;
      }
    }
  `,
})
export class AdresseChamps {
  readonly groupe = input.required<FormGroup>();
  readonly libelleLigne1 = input('Adresse');
}
