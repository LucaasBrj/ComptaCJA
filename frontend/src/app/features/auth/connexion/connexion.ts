import { HttpErrorResponse } from '@angular/common/http';
import { Component, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { MatButtonModule } from '@angular/material/button';
import { MatCardModule } from '@angular/material/card';
import { MatFormFieldModule } from '@angular/material/form-field';
import { MatIconModule } from '@angular/material/icon';
import { MatInputModule } from '@angular/material/input';
import { MatProgressBarModule } from '@angular/material/progress-bar';
import { Router } from '@angular/router';
import { switchMap } from 'rxjs';
import { AuthService } from '../../../core/auth/auth.service';

@Component({
  selector: 'app-connexion',
  imports: [
    ReactiveFormsModule,
    MatCardModule,
    MatFormFieldModule,
    MatInputModule,
    MatButtonModule,
    MatIconModule,
    MatProgressBarModule,
  ],
  templateUrl: './connexion.html',
  styleUrl: './connexion.scss',
})
export class Connexion {
  private readonly auth = inject(AuthService);
  private readonly router = inject(Router);

  protected readonly formulaire = inject(FormBuilder).nonNullable.group({
    email: ['', [Validators.required, Validators.email]],
    motDePasse: ['', [Validators.required]],
  });

  protected readonly enCours = signal(false);
  protected readonly messageErreur = signal<string | null>(null);
  protected readonly motDePasseVisible = signal(false);

  protected soumettre(): void {
    if (this.formulaire.invalid || this.enCours()) {
      this.formulaire.markAllAsTouched();

      return;
    }

    const { email, motDePasse } = this.formulaire.getRawValue();
    this.enCours.set(true);
    this.messageErreur.set(null);

    this.auth
      .connexion(email, motDePasse)
      // Le profil est charge immediatement : la barre superieure affiche le nom
      // de l'artisan des la premiere page.
      .pipe(switchMap(() => this.auth.chargerProfil()))
      .subscribe({
        next: () => {
          const retour = new URLSearchParams(window.location.search).get('retour');
          void this.router.navigateByUrl(retour ?? '/clients');
        },
        error: (erreur: HttpErrorResponse) => {
          this.enCours.set(false);
          this.messageErreur.set(
            erreur.status === 401
              ? 'Email ou mot de passe incorrect.'
              : "Connexion impossible. Vérifiez que l'API est démarrée.",
          );
        },
      });
  }
}
