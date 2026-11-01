import { Component, inject, signal, computed } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { PortalService } from './portal.service';
import { AuthService, errorMessage } from './auth.service';
interface Entry {name1:string;card1:string;name2:string;card2:string}
interface Race {name:string;kind:'single'|'double';euros:number;entries:Entry[]}
@Component({selector:'fr-manual-tournament',imports:[FormsModule,RouterLink],template:`
 <div class="page-heading"><h1>Aggiungi torneo</h1></div>
 <p class="notice">Attenzione: usa questa funzione solo per tornei non presenti nell’extranet, ad esempio provinciali. Non aggiungere tornei già disponibili o che dovrebbero comparire sul portale FITeT: verifica prima l’extranet per evitare duplicati.</p>
 <form (ngSubmit)="save()"><fieldset [disabled]="busy()" class="manual-tournament-form">
 <section class="refund-settings"><label>Nome torneo<input name="name" [(ngModel)]="name" maxlength="500" required></label><label>Data<input name="date" type="date" [(ngModel)]="date" required></label><label>Località<input name="place" [(ngModel)]="place" maxlength="255" required></label><label>ID comitato (30 = nazionale)<input name="committee" [(ngModel)]="committee" pattern="[0-9]+" required></label><label>Nome comitato<input name="committeeName" [(ngModel)]="committeeName" maxlength="255" required></label><label>Totale ricevuta (€)<input name="total" type="number" step="0.01" min="0" [(ngModel)]="total" required></label>
 <label>Ricevuta obbligatoria (PDF, PNG o JPG; max {{receiptLimit}} KB)<input type="file" accept="application/pdf,image/png,image/jpeg" (change)="receipt=$any($event.target).files[0]" required></label><label>Locandina facoltativa (PDF, PNG o JPG; max {{posterLimit}} KB)<input type="file" accept="application/pdf,image/png,image/jpeg" (change)="poster=$any($event.target).files[0]"></label></section>
 <datalist id="manual-athletes">@for(p of people();track p.card){<option [value]="p.name+' ('+p.card+')'"></option>}</datalist>
 @for(race of races;track $index;let ri=$index){<section class="refund-settings"><h2 class="wide-field">Gara {{ri+1}}</h2><label>Nome gara<input [name]="'race'+ri" [(ngModel)]="race.name" maxlength="500" required></label><label>Tipo<select [name]="'kind'+ri" [(ngModel)]="race.kind"><option value="single">Singolo</option><option value="double">Doppio</option></select></label><label>Quota (€)<input [name]="'price'+ri" type="number" step="0.01" min="0" [(ngModel)]="race.euros" required></label><button class="text-button" type="button" (click)="races.splice(ri,1)">Rimuovi gara</button>
 @for(entry of race.entries;track $index;let ei=$index){<div class="manual-entry wide-field"><label>Atleta<input list="manual-athletes" [name]="'name'+ri+'-'+ei" [(ngModel)]="entry.name1" (change)="suggest(entry,1)" maxlength="255" required></label><label>Tessera<input [name]="'card'+ri+'-'+ei" [(ngModel)]="entry.card1" pattern="[0-9]+" maxlength="32" required></label>@if(race.kind==='double'){<label>Secondo atleta<input list="manual-athletes" [name]="'name2'+ri+'-'+ei" [(ngModel)]="entry.name2" (change)="suggest(entry,2)" maxlength="255" required></label><label>Tessera secondo atleta<input [name]="'card2'+ri+'-'+ei" [(ngModel)]="entry.card2" pattern="[0-9]+" maxlength="32" required></label>}<button class="text-button" type="button" (click)="race.entries.splice(ei,1)">Rimuovi iscrizione</button></div>}
 <button class="secondary-button" type="button" (click)="addEntry(race)">Aggiungi iscrizione</button></section>}
 <button class="secondary-button" type="button" (click)="addRace()">Aggiungi gara</button>
 <p><label class="check-field"><input type="checkbox" name="confirmed" [(ngModel)]="confirmed" required>Ho verificato che questo torneo non sia già disponibile o atteso nell’extranet.</label></p>
 <p class="grid-help">Le immagini sono convertite in PDF dal browser. Il limite si applica anche al PDF finale archiviato.</p>
 @if(error()){<p class="error-box" role="alert">{{error()}}</p>}
 <button class="sync-button" type="submit" [disabled]="!races.length||!confirmed||!receipt">{{busy()?'Salvataggio…':'Salva torneo'}}</button> <a routerLink="/tornei">Annulla</a>
 </fieldset></form>
`})
export class ManualTournamentComponent {
 readonly portal=inject(PortalService);private readonly auth=inject(AuthService);private readonly router=inject(Router);
 name='';date='';place='';committee='';committeeName='';total=0;confirmed=false;receipt?:File;poster?:File;receiptLimit=200;posterLimit=200;races:Race[]=[];
 readonly busy=signal(false);readonly error=signal('');
 readonly people=computed(()=>{const map=new Map<string,{name:string;card:string}>();for(const e of this.portal.data.entries())for(const p of [{name:e.athlete1_name,card:e.athlete1_card},{name:e.athlete2_name,card:e.athlete2_card}])if(p.name&&p.card)map.set(p.card,{name:p.name,card:p.card});return [...map.values()].sort((a,b)=>a.name.localeCompare(b.name,'it'));});
 constructor(){this.addRace();void this.auth.publicConfig().then(c=>{this.receiptLimit=c.manual_receipt_kb||200;this.posterLimit=c.manual_poster_kb||200;}).catch(e=>this.error.set(errorMessage(e)));}
 addRace(){this.races.push({name:'',kind:'single',euros:0,entries:[]});}
 addEntry(r:Race){r.entries.push({name1:'',card1:'',name2:'',card2:''});}
 suggest(e:Entry,index:1|2){const name=index===1?e.name1:e.name2;const p=this.people().find(p=>p.name+' ('+p.card+')'===name);if(p){if(index===1){e.name1=p.name;e.card1=p.card;}else{e.name2=p.name;e.card2=p.card;}}}
 private async document(file:File,limit:number):Promise<File>{
  if(file.size>limit*1024)throw new Error(file.name+': limite '+limit+' KB superato.');
  if(file.type==='application/pdf')return file;
  if(!['image/jpeg','image/png'].includes(file.type))throw new Error('Sono ammessi PDF, PNG o JPG.');
  const url=URL.createObjectURL(file);try{const image=new Image();image.src=url;await image.decode();if(image.width>8192||image.height>8192)throw new Error('Immagine troppo grande: massimo 8192 pixel per lato.');const {jsPDF}=await import('jspdf');const doc=new jsPDF({compress:true});const factor=Math.min(190/image.width,277/image.height);doc.addImage(image,file.type==='image/png'?'PNG':'JPEG',10,10,image.width*factor,image.height*factor);const blob=doc.output('blob');if(blob.size>limit*1024)throw new Error('Il PDF ricavato da '+file.name+' supera '+limit+' KB. Riduci l’immagine.');return new File([blob],file.name+'.pdf',{type:'application/pdf'});}finally{URL.revokeObjectURL(url);}
 }
 async save(){if(this.busy())return;this.busy.set(true);this.error.set('');try{if(!this.receipt)throw new Error('Carica la ricevuta.');const receipt=await this.document(this.receipt,this.receiptLimit),poster=this.poster?await this.document(this.poster,this.posterLimit):undefined;const data={name:this.name,date:this.date,place:this.place,committee:this.committee,committee_name:this.committeeName,total_cents:Math.round(this.total*100),confirmed:this.confirmed,races:this.races.map(r=>({...r,cents:Math.round(r.euros*100)}))};const result=await this.portal.manualTournament(data,receipt,poster);await this.portal.data.refresh();this.portal.setSeason('all');await this.router.navigateByUrl('/tornei/'+result.tournament_key);}catch(e){this.error.set(e instanceof Error?e.message:errorMessage(e));}finally{this.busy.set(false);}}
}
