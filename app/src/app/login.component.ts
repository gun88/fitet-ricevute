import { Component, OnInit, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router } from '@angular/router';
import { AuthService, PublicConfig, errorMessage } from './auth.service';

@Component({
  selector: 'fr-login',
  imports: [FormsModule],
  templateUrl: './login.component.html'
})
export class LoginComponent implements OnInit {
  private readonly auth = inject(AuthService);
  private readonly router = inject(Router);
  private readonly route = inject(ActivatedRoute);
  readonly settings = signal<PublicConfig | null>(null);
  readonly loading = signal(true);
  readonly submitting = signal(false);
  readonly error = signal('');
  readonly showPassword = signal(false);
  username = '';
  password = '';
  remember = false;

  async ngOnInit(): Promise<void> {
    this.loading.set(true);
    this.error.set('');
    try {
      this.settings.set(await this.auth.publicConfig());
      if (this.settings()?.enabled && await this.auth.restore()) {
        await this.router.navigateByUrl('/');
      } else if (this.route.snapshot.queryParamMap.has('check')) {
        this.error.set('La verifica della sessione non è riuscita. Puoi riprovare ad accedere.');
      }
    } catch (error) {
      this.error.set(errorMessage(error));
    } finally {
      this.loading.set(false);
    }
  }

  async submit(): Promise<void> {
    if (this.submitting() || this.loading() || !this.settings()?.enabled) return;
    this.submitting.set(true);
    this.error.set('');
    try {
      await this.auth.login(this.username.trim(), this.password, this.remember);
      this.password = '';
      await this.router.navigateByUrl('/');
    } catch (error) {
      this.password = '';
      this.error.set(errorMessage(error));
    } finally {
      this.submitting.set(false);
    }
  }
}
