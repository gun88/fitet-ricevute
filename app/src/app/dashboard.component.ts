import { FormsModule } from '@angular/forms';
import { PortalService } from './portal.service';
import { Component, OnInit, inject, signal } from '@angular/core';
import { Router, RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';
import { AuthService, PublicConfig, errorMessage } from './auth.service';
import { DataService } from './data.service';

@Component({ selector: 'fr-dashboard', imports: [RouterOutlet, RouterLink, RouterLinkActive, FormsModule], templateUrl: './dashboard.component.html' })
export class DashboardComponent implements OnInit {
  readonly auth = inject(AuthService);
  readonly data = inject(DataService);
  readonly portal = inject(PortalService);
  readonly settings = signal<PublicConfig | null>(null);
  private readonly router = inject(Router);
  async ngOnInit(): Promise<void> {
    this.portal.clear(); this.data.job.set(null);
    this.data.restoreSelection();
    try { this.settings.set(await this.auth.publicConfig()); await this.data.refresh(); await this.portal.load(); }
    catch (error) { this.data.error.set(errorMessage(error)); }
  }
  async logout(): Promise<void> {
    await this.data.perform(async () => {
      const userId = this.auth.user()?.id;
      await this.auth.logout(); this.data.clear(userId); this.portal.clear(); await this.router.navigateByUrl('/login');
    });
  }
}
