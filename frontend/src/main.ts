import { bootstrapApplication } from '@angular/platform-browser';
import { appConfig } from './app/app.config';
import { App } from './app/app';
import { restaurerApparence } from './app/core/apparence';

restaurerApparence();

bootstrapApplication(App, appConfig)
  .catch((err) => console.error(err));
