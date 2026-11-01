import { Injectable, inject, signal } from '@angular/core';
import type { GridApi, GridState, StateUpdatedEvent } from 'ag-grid-community';
import { AuthService } from './auth.service';
import { config } from './config';

interface Preferences { schema: 1; grid?: GridState; search?: string; external?: Record<string, string> }
const sections = ['version','filter','sort','columnOrder','columnPinning','columnSizing','columnVisibility','pagination'] as const;

function gridPreferences(state: GridState): GridState {
  const grid: GridState = { partialColumnState: true };
  for (const key of sections) if (state[key] !== undefined) Object.assign(grid, { [key]: state[key] });
  return grid;
}

@Injectable({ providedIn: 'root' })
export class TablePreferences {
  private readonly auth = inject(AuthService);
  private key(table: string): string { return 'fr:tables:v1:' + encodeURIComponent(config.apiUrl) + ':' + this.auth.user()?.id + ':' + table; }
  read(table: string): Preferences {
    if (!this.auth.user()) return { schema: 1 };
    try {
      const raw = localStorage.getItem(this.key(table));
      if (!raw || raw.length > 150000) return { schema: 1 };
      const value = JSON.parse(raw);
      if (value?.schema !== 1 || typeof value !== 'object') return { schema: 1 };
      return {
        schema: 1,
        grid: value.grid && typeof value.grid === 'object' && !Array.isArray(value.grid) ? gridPreferences(value.grid) : undefined,
        search: typeof value.search === 'string' ? value.search.slice(0,1000) : '',
        external: value.external && typeof value.external === 'object' && !Array.isArray(value.external) ? Object.fromEntries(Object.entries(value.external).filter(([key,item]) => key.length < 80 && typeof item === 'string' && item.length < 100)) as Record<string,string> : {},
      };
    } catch { return { schema: 1 }; }
  }
  write(table: string, value: Preferences): void {
    if (!this.auth.user()) return;
    try { localStorage.setItem(this.key(table), JSON.stringify(value)); } catch { /* The app remains usable when browser storage is unavailable. */ }
  }
}

/** Preferences contain presentation only: never session tokens, row selections or receipt data. */
export class TableView {
  private readonly preferences = inject(TablePreferences);
  private current: Preferences;
  initialState?: GridState;
  prepare(): void { this.initialState = structuredClone(this.current.grid); }
  readonly search;
  readonly season;
  constructor(private readonly table: string) {
    this.current = this.preferences.read(table);this.prepare();
    this.search = signal(this.current.search ?? '');
    this.season = signal(this.current.external?.['season'] ?? 'latest');
  }
  restore(api: GridApi): void { if(this.initialState)api.setState(structuredClone(this.initialState)); }
  private save(): void { this.preferences.write(this.table, this.current); }
  setSearch(value: string): void { this.search.set(value.slice(0,1000)); this.current.search = this.search(); this.save(); }
  setSeason(value: string): void {
    if (!/^(?:latest|all|\d{4}\/\d{4})$/.test(value)) return;
    this.season.set(value); this.current.external = { ...this.current.external, season: value }; this.save();
  }
  capture(state: GridState): void { this.current.grid = structuredClone(gridPreferences(state)); this.save(); }
  updated(event: StateUpdatedEvent): void { this.capture(event.state); }
  reset(api?: GridApi): void {
    this.setSearch('');
    api?.setFilterModel(null);
    api?.applyColumnState({ defaultState: { sort: null } });
    if (this.current.external?.['season'] !== undefined) this.setSeason('latest');
  }
}
