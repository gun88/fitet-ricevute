import type { Row } from './data.service';

export const money = (cents: number | null | undefined) => cents == null ? 'Da definire' : new Intl.NumberFormat('it-IT', { style: 'currency', currency: 'EUR' }).format(cents / 100);
export const dateLabel = (value: string | null | undefined) => value ? value.split('-').reverse().join('/') : '—';
export const names = (row: Row) => [row.athlete1_name, row.athlete2_name].filter(Boolean).join(' / ');
export const originLabel = (row: Row) => row.race_kind === 'empty' ? 'Non applicabile' : row.manual_cents !== null ? 'Sovrascritto' : row.price_cents === null ? 'Da definire' : 'Calcolato';
export const scopeLabel = (row: Row) => row.tournament_scope === 'national' ? 'Nazionale' : row.tournament_scope === 'territorial' ? 'Territoriale' : 'Non indicato';

export const athleteLabel = (name:string|null|undefined,card:string|null|undefined) => name ? name+' ('+(card||'—')+')' : '';
export const tournamentType = (row:Row) => String(row.source_committee)==='30'||row.tournament_scope==='national'?'Nazionale':'Territoriale ('+(row.committee_name||row.committee_label||'Non indicato')+')';
