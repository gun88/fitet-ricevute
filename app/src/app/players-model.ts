import type { Row } from './data.service';

export interface Player { key: string; name: string; card: string; tournaments: number; entries: number; reimbursed: number; last_date: string; rows: Row[] }
export const playerKey = (name: string, card: string | null | undefined) => card ? 'card:' + card : 'name:' + name.trim().toLocaleUpperCase('it-IT');

export function seasonFor(date: string): string {
  if (!/^\d{4}-\d{2}-\d{2}$/.test(date)) return '';
  const year = Number(date.slice(0,4)) - (Number(date.slice(5,7)) < 7 ? 1 : 0);
  return year + '/' + (year+1);
}
export function seasonsFor(rows: Row[]): string[] { return [...new Set(rows.map(row => seasonFor(row.tournament_date)).filter(Boolean))].sort().reverse(); }
export function inSeason(rows: Row[], selected: string, latest: string): Row[] {
  return selected === 'all' ? rows : rows.filter(row => seasonFor(row.tournament_date) === (selected === 'latest' ? latest : selected));
}
export function playersFor(rows: Row[]): Player[] {
  const map = new Map<string, Player>();
  for (const row of rows) {
    const seen = new Set<string>();
    for (const person of [{ name: row.athlete1_name, card: row.athlete1_card }, { name: row.athlete2_name, card: row.athlete2_card }]) {
      if (!person.name) continue;
      const key = playerKey(person.name, person.card);
      if (seen.has(key)) continue;
      seen.add(key);
      const player = map.get(key) ?? { key, name: person.name, card: person.card || '—', tournaments:0, entries:0, reimbursed:0, last_date:'', rows:[] };
      player.entries++; player.reimbursed += row.reimbursed ? 1 : 0;
      if (row.tournament_date > player.last_date) player.last_date = row.tournament_date;
      player.rows.push(row); map.set(key,player);
    }
  }
  for (const player of map.values()) player.tournaments = new Set(player.rows.map(row => row.society_code + ':' + row.tournament_key)).size;
  return [...map.values()].sort((a,b) => a.name.localeCompare(b.name,'it'));
}
