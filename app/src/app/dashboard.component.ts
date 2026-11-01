import { FormsModule } from '@angular/forms';
import { PortalService } from './portal.service';
import { Component, OnInit, OnDestroy, HostListener, ElementRef, ViewChild, inject, signal } from '@angular/core';
import { Router, RouterLink, RouterLinkActive, RouterOutlet, NavigationEnd } from '@angular/router';
import { AuthService, PublicConfig, errorMessage } from './auth.service';
import { DataService } from './data.service';
import { Subscription } from 'rxjs';

@Component({ selector: 'fr-dashboard', imports: [RouterOutlet, RouterLink, RouterLinkActive, FormsModule], templateUrl: './dashboard.component.html' })
export class DashboardComponent implements OnInit, OnDestroy {
  readonly auth = inject(AuthService);
  readonly data = inject(DataService);
  readonly portal = inject(PortalService);
  readonly settings = signal<PublicConfig | null>(null);
  readonly mobileOpen = signal(false);
  readonly userOpen = signal(false);
  private readonly router = inject(Router);
  @ViewChild('appHeader') private header?: ElementRef<HTMLElement>;
  @ViewChild('headerPanel') private panel?: ElementRef<HTMLElement>;
  @ViewChild('menuToggle') private menuToggle?: ElementRef<HTMLButtonElement>;
  @ViewChild('userToggle') private userToggle?: ElementRef<HTMLButtonElement>;
  @ViewChild('userArea') private userArea?: ElementRef<HTMLElement>;
  private readonly navigation: Subscription = this.router.events.subscribe(event => {
    if (event instanceof NavigationEnd) this.closeMenus();
  });
  readonly initial = () => Array.from(this.auth.user()?.display_name.trim() || this.auth.user()?.username || '?')[0].toLocaleUpperCase('it-IT');
  readonly seasonLabel = (season: string) => season.slice(0,4)+'/'+season.slice(-2);
  async ngOnInit(): Promise<void> {
    this.portal.clear(); this.portal.restoreSeason(); this.data.job.set(null);
    this.data.restoreSelection();
    try { this.settings.set(await this.auth.publicConfig()); await this.data.refresh(); await this.portal.load(); }
    catch (error) { this.data.error.set(errorMessage(error)); }
  }
  toggleMobile(): void {
    if (this.mobileOpen()) { this.closeMenus(true); return; }
    this.userOpen.set(false); this.mobileOpen.set(true);
    document.body.classList.add('fr-menu-open');
    setTimeout(() => { if(this.mobileOpen()) this.panel?.nativeElement.querySelector<HTMLElement>('a')?.focus(); });
  }
  closeMenus(restoreFocus = false): void {
    const wasMobile = this.mobileOpen();
    this.mobileOpen.set(false); this.userOpen.set(false); document.body.classList.remove('fr-menu-open');
    if (restoreFocus) (wasMobile?this.menuToggle:this.userToggle)?.nativeElement.focus();
  }
  changeSeason(value: string): void { this.portal.setSeason(value); if(this.mobileOpen()) this.closeMenus(true); }
  @HostListener('document:click', ['$event']) outside(event: MouseEvent): void {
    if(this.userOpen() && !this.userArea?.nativeElement.contains(event.target as Node)) this.userOpen.set(false);
  }
  @HostListener('window:resize') resize(): void { if(window.innerWidth>1100) this.closeMenus(); }
  @HostListener('document:keydown', ['$event']) keydown(event: KeyboardEvent): void {
    if(event.key==='Escape' && (this.mobileOpen()||this.userOpen())) { event.preventDefault(); this.closeMenus(true); }
    if(event.key!=='Tab'||!this.mobileOpen()) return;
    const controls = Array.from(this.header?.nativeElement.querySelectorAll<HTMLElement>('a[href],button:not([disabled]),select')||[]).filter((el): el is HTMLElement => !!el && el.getClientRects().length>0);
    const first=controls[0], last=controls.at(-1);
    if(event.shiftKey && document.activeElement===first) { event.preventDefault(); last?.focus(); }
    else if(!event.shiftKey && document.activeElement===last) { event.preventDefault(); first?.focus(); }
  }
  async logout(): Promise<void> {
    this.closeMenus();
    await this.data.perform(async () => {
      const userId = this.auth.user()?.id;
      await this.auth.logout(); this.data.clear(userId); this.portal.clear(); await this.router.navigateByUrl('/login');
    });
  }
  ngOnDestroy(): void { this.navigation.unsubscribe(); document.body.classList.remove('fr-menu-open'); }
}
