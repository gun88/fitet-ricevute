import { Component, inject } from '@angular/core';
import { DashboardComponent } from './dashboard.component';
@Component({selector:'fr-user',template:`
 <div class="page-heading"><div><h1>Profilo utente</h1><p class="dashboard-subtitle">Le informazioni del tuo account.</p></div></div>
 <section class="profile-card" aria-label="Informazioni utente">
  <dl class="detail-summary"><dt>Nome utente</dt><dd>{{dashboard.auth.user()?.display_name}}</dd><dt>Username di accesso</dt><dd>{{dashboard.auth.user()?.username}}</dd></dl>
  <button type="button" class="secondary-button profile-logout" [disabled]="dashboard.data.locked()" (click)="dashboard.logout()"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 3H4v18h5M14 7l5 5-5 5M8 12h11"/></svg>Log out</button>
 </section>
`})
export class UserComponent { readonly dashboard=inject(DashboardComponent); }
