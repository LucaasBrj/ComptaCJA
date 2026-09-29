import { Component, inject } from '@angular/core';
import { MatSlideToggleModule } from '@angular/material/slide-toggle';
import { ApparenceService } from '../../core/apparence';

@Component({
  selector: 'app-interrupteur-apparence',
  imports: [MatSlideToggleModule],
  template: `
    <mat-slide-toggle
      [checked]="apparence.sombre()"
      (change)="apparence.definir($event.checked)"
    >
      Mode sombre
    </mat-slide-toggle>
  `,
})
export class InterrupteurApparence {
  protected readonly apparence = inject(ApparenceService);
}
