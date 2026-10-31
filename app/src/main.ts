import { bootstrapApplication } from '@angular/platform-browser';
import { provideHttpClient, withInterceptors } from '@angular/common/http';
import { provideRouter, withHashLocation } from '@angular/router';
import { Component } from '@angular/core';
import { RouterOutlet } from '@angular/router';
import { authInterceptor } from './app/auth.interceptor';
import { authGuard } from './app/auth.guard';
import { LoginComponent } from './app/login.component';
import { DashboardComponent } from './app/dashboard.component';

@Component({ selector: 'fr-root', imports: [RouterOutlet], template: '<router-outlet />' })
class AppComponent {}

bootstrapApplication(AppComponent, {
  providers: [
    provideHttpClient(withInterceptors([authInterceptor])),
    provideRouter([
      { path: 'login', component: LoginComponent, title: 'Accedi · FITET Ricevute' },
      { path: '', component: DashboardComponent, canActivate: [authGuard], children: [
        { path: '', pathMatch: 'full', redirectTo: 'prezzi' },
        { path: 'prezzi', loadComponent: () => import('./app/prices.component').then(m => m.PricesComponent), title: 'Prezzi tornei · FITET Ricevute' },
        { path: 'iscrizioni', loadComponent: () => import('./app/entries.component').then(m => m.EntriesComponent), title: 'Iscrizioni · FITET Ricevute' },
        { path: 'giocatori', loadComponent: () => import('./app/players.component').then(m => m.PlayersComponent), title: 'Giocatori · FITET Ricevute' },
        { path: 'giocatori/:key', loadComponent: () => import('./app/player.component').then(m => m.PlayerComponent), title: 'Giocatore · FITET Ricevute' },
        { path: 'rimborsi', loadComponent: () => import('./app/refunds.component').then(m => m.RefundsComponent), title: 'Emissione rimborso · FITET Ricevute' }
      ] },
      { path: '**', redirectTo: '' }
    ], withHashLocation())
  ]
}).catch(() => {
  const root = document.querySelector('fr-root');
  if (root) root.textContent = 'Impossibile avviare il portale. Ricarica la pagina.';
});
