import { HttpClient, HttpErrorResponse } from '@angular/common/http';
import { Injectable, inject, signal, computed } from '@angular/core';
import { firstValueFrom } from 'rxjs';
import { apiUrl } from './config';

export interface User { id: number; username: string; display_name: string }
export interface GridPreferences { payment_method?:'transfer'|'cash'; auto_height: boolean; density: 'standard'|'compact' }
export interface Session { grid_preferences?: GridPreferences; user: User; expires_at: number; csrf_token: string }
export interface PublicConfig { committees:{id:string;name:string}[]; app_name: string; society_name: string; login_message: string; enabled: boolean; manual_receipt_kb:number; manual_poster_kb:number }

@Injectable({ providedIn: 'root' })
export class AuthService {
  private readonly http = inject(HttpClient);
  private readonly session = signal<Session | null>(null);
  readonly user = () => this.session()?.user ?? null;
  readonly gridPreferences = computed<GridPreferences>(() => this.session()?.grid_preferences ?? {auto_height:false,density:'standard'});
  async saveGridPreferences(value: GridPreferences): Promise<void> {
    const owner = this.user()?.id;
    const saved = await firstValueFrom(this.http.put<GridPreferences>(apiUrl('profilo/tabelle'), value));
    this.session.update(session => session && session.user.id === owner ? {...session,grid_preferences:saved} : session);
  }
  readonly csrf = () => this.session()?.csrf_token ?? '';

  publicConfig(): Promise<PublicConfig> {
    return firstValueFrom(this.http.get<PublicConfig>(apiUrl('config')));
  }

  async restore(): Promise<boolean> {
    try {
      this.session.set(await firstValueFrom(this.http.get<Session>(apiUrl('auth/me'))));
      return true;
    } catch (error) {
      this.session.set(null);
      // Only an unauthenticated response is treated as a normal logged-out state.
      if (error instanceof HttpErrorResponse && error.status === 401) return false;
      throw error;
    }
  }

  async login(username: string, password: string, remember: boolean): Promise<void> {
    this.session.set(await firstValueFrom(this.http.post<Session>(apiUrl('auth/login'), { username, password, remember })));
  }

  async logout(): Promise<void> {
    await firstValueFrom(this.http.post(apiUrl('auth/logout'), {}));
    this.clear();
  }

  async changePassword(current_password:string,new_password:string,confirm_password:string):Promise<void>{await firstValueFrom(this.http.post(apiUrl('auth/password'),{current_password,new_password,confirm_password}));this.clear();}
  clear(): void { this.session.set(null); }
}

export function errorMessage(error: unknown): string {
  if (error instanceof HttpErrorResponse) {
    if (error.status === 0) return 'Connessione non disponibile. Controlla la rete e riprova.';
    if (error.status === 429) return 'Troppi tentativi. Riprova tra 15 minuti.';
    if (error.status === 503) return 'Il portale è temporaneamente disabilitato.';
    if (typeof error.error?.message === 'string') return error.error.message;
  }
  return 'Non è stato possibile completare l’operazione. Riprova.';
}
