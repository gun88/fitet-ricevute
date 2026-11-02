import { athleteSummary } from './refund-model';
import type { Row } from './data.service';
import type { Allocation } from './refund-model';
export function refundStatistics(emissions:{items:Row[];options:{allocation:Allocation}}[],filter:(rows:Row[])=>Row[]=rows=>rows){
 const players=new Set<string>(),races=new Set<string>();
 for(const emission of emissions){const rows=filter(emission.items);for(const person of athleteSummary(rows,emission.options.allocation))players.add(person.key);for(const row of rows)races.add(Number(row.race_id)>0?'race:'+row.race_id:'race:'+row.tournament_key+':'+row.race_name);}
 return {players:players.size,races:races.size};
}
