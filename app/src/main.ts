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
        { path: '', pathMatch: 'full', redirectTo: 'tornei' },
        { path: 'utente', loadComponent:()=>import('./app/user.component').then(m=>m.UserComponent), title:'Profilo utente · FITET Ricevute' },
        { path: 'prezzi', pathMatch:'full', redirectTo:'gare' },
        { path: 'tornei', data:{kind:'tournaments'}, loadComponent:()=>import('./app/portal-pages.component').then(m=>m.PortalPageComponent), title:'Tornei · FITET Ricevute' },
        { path: 'gare', data:{kind:'races'}, loadComponent:()=>import('./app/portal-pages.component').then(m=>m.PortalPageComponent), title:'Gare · FITET Ricevute' },
        { path: 'iscrizioni', data:{kind:'entries'}, loadComponent:()=>import('./app/portal-pages.component').then(m=>m.PortalPageComponent), title:'Iscrizioni · FITET Ricevute' },
        { path: 'rimborsi', data:{kind:'emissions'}, loadComponent:()=>import('./app/portal-pages.component').then(m=>m.PortalPageComponent), title:'Rimborsi Emessi · FITET Ricevute' },
        { path: 'carrello', pathMatch:'full', redirectTo:'bozza-rimborso' },
        { path: 'bozza-rimborso', loadComponent:()=>import('./app/cart.component').then(m=>m.CartComponent), title:'Bozza rimborso · FITET Ricevute' },
        { path: 'tornei/:id', data:{kind:'tournament'}, loadComponent:()=>import('./app/portal-pages.component').then(m=>m.PortalDetailComponent), title:'Torneo · FITET Ricevute' },
        { path: 'gare/:id', data:{kind:'race'}, loadComponent:()=>import('./app/portal-pages.component').then(m=>m.PortalDetailComponent), title:'Gara · FITET Ricevute' },
        { path: 'rimborsi/:id', data:{kind:'emission'}, loadComponent:()=>import('./app/portal-pages.component').then(m=>m.PortalDetailComponent), title:'Rimborso · FITET Ricevute' },
        { path: 'giocatori', loadComponent:()=>import('./app/players.component').then(m=>m.PlayersComponent), title:'Giocatori · FITET Ricevute' },
        { path: 'giocatori/:key', data:{kind:'player'}, loadComponent:()=>import('./app/portal-pages.component').then(m=>m.PortalDetailComponent), title:'Giocatore · FITET Ricevute' }
      ] },
      { path: '**', redirectTo: '' }
    ], withHashLocation())
  ]
}).catch(() => {
  const root = document.querySelector('fr-root');
  if (root) root.textContent = 'Impossibile avviare il portale. Ricarica la pagina.';
});
