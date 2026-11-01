import { FormsModule } from '@angular/forms';
import { errorMessage, GridPreferences } from './auth.service';
import { Component, inject, signal } from '@angular/core';
import { DashboardComponent } from './dashboard.component';
@Component({selector:'fr-user',imports:[FormsModule],template:`
 <div class="page-heading"><div><h1>Profilo utente</h1><p class="dashboard-subtitle">Le informazioni del tuo account.</p></div></div>
 <section class="profile-card" aria-label="Informazioni utente">
  <dl class="detail-summary"><dt>Nome utente</dt><dd>{{dashboard.auth.user()?.display_name}}</dd><dt>Username di accesso</dt><dd>{{dashboard.auth.user()?.username}}</dd></dl>
  <fieldset class="profile-grid-preferences" [disabled]="saving()">
   <legend>Tabelle desktop</legend>
   <label class="check-field"><input type="checkbox" role="switch" [checked]="dashboard.auth.gridPreferences().auto_height" (change)="save({auto_height:$any($event.target).checked})">Altezza automatica</label>
   <p class="grid-help">Adatta la tabella alle righe della pagina. Disattiva per usare lo scorrimento interno.</p>
   <label>Spaziatura <select aria-label="Spaziatura tabelle" [ngModel]="dashboard.auth.gridPreferences().density" (ngModelChange)="save({density:$event})"><option value="standard">Standard</option><option value="compact">Compatta</option></select></label>
   <p class="grid-help">Preferenze personali salvate sul tuo account, valide per tutte le tabelle desktop.</p>
   <p role="status">{{saving()?'Salvataggio…':message()}}</p>
  </fieldset>
  <button type="button" class="secondary-button profile-logout" [disabled]="dashboard.data.locked()" (click)="dashboard.logout()"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 3H4v18h5M14 7l5 5-5 5M8 12h11"/></svg>Log out</button>
 </section>
`})
export class UserComponent {
 readonly dashboard=inject(DashboardComponent);readonly saving=signal(false);readonly message=signal('');
 async save(change:Partial<GridPreferences>){this.saving.set(true);this.message.set('');try{await this.dashboard.auth.saveGridPreferences({...this.dashboard.auth.gridPreferences(),...change});this.message.set('Preferenze salvate.');}catch(error){this.message.set(errorMessage(error));}finally{this.saving.set(false);}}
}
