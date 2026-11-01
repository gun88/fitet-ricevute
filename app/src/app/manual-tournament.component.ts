import { config } from './config';
import { Entry, Race, importManualJson, importManualExcel } from './manual-tournament-import';
import { Component, inject, signal, computed, ViewChild, ElementRef } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { PortalService } from './portal.service';
import { AuthService, errorMessage } from './auth.service';
@Component({selector:'fr-manual-tournament',imports:[FormsModule,RouterLink],template:`
 <div class="page-heading"><h1>Aggiungi torneo</h1></div>
 <p class="notice">Attenzione: usa questa funzione solo per tornei non presenti nell’extranet, ad esempio provinciali. Non aggiungere tornei già disponibili o che dovrebbero comparire sul portale FITeT: verifica prima l’extranet per evitare duplicati.</p>
 <section class="manual-import-section" aria-label="Importa dati torneo"><div class="manual-import-actions">
 <button type="button" class="secondary-button" [disabled]="busy()||importing()" (click)="downloadExample('json')">Esempio JSON</button>
 <button type="button" class="secondary-button" [disabled]="busy()||importing()" (click)="jsonInput.click()">Importa JSON</button>
 <button type="button" class="secondary-button" [disabled]="busy()||importing()" (click)="downloadExample('xlsx')">Esempio Excel</button>
 <button type="button" class="secondary-button" [disabled]="busy()||importing()" (click)="excelInput.click()">Importa Excel</button>
 <input #jsonInput hidden class="manual-import-file" type="file" accept=".json,application/json" aria-label="File import JSON" (change)="importFile(jsonInput,'json')">
 <input #excelInput hidden class="manual-import-file" type="file" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" aria-label="File import Excel" (change)="importFile(excelInput,'xlsx')"></div>
 <p class="grid-help">Il file sostituisce i dati del modulo, senza salvare il torneo. Ricevuta e locandina non vengono importate: caricale manualmente dopo l’importazione. JSON massimo 1 MB, Excel massimo 2 MB.</p>
 @if(importing()){<p role="status">Lettura del file…</p>}@if(importNotice()){<p class="notice" role="status">{{importNotice()}}</p>}
 </section>
 <form (ngSubmit)="save()"><fieldset [disabled]="busy()||importing()" class="manual-tournament-form">
 <section class="refund-settings"><label>Nome torneo<input name="name" [(ngModel)]="name" maxlength="500" required></label><label>Data<input name="date" type="date" [(ngModel)]="date" required></label><label>Località<input name="place" [(ngModel)]="place" maxlength="255" required></label><label>Comitato<select aria-label="Comitato" name="committee" [ngModel]="committee" (ngModelChange)="setCommittee($event)" required><option value="" disabled>— Seleziona comitato —</option>@if(committee&&!selectedCommittee){<option [value]="committee" disabled>Comitato {{committee}} non riconosciuto</option>}@for(item of committees();track item.id){<option [value]="item.id">{{item.name}} ({{item.id}})</option>}</select></label><label>Totale ricevuta (€)<input name="total" type="number" step="0.01" min="0" [(ngModel)]="total" required></label>
 <label>Ricevuta obbligatoria (PDF, PNG o JPG; max {{receiptLimit}} KB)<input #receiptUpload type="file" accept="application/pdf,image/png,image/jpeg" (change)="receipt=$any($event.target).files[0]" required></label><label>Locandina facoltativa (PDF, PNG o JPG; max {{posterLimit}} KB)<input #posterUpload type="file" accept="application/pdf,image/png,image/jpeg" (change)="poster=$any($event.target).files[0]"></label></section>
 <datalist id="manual-athletes">@for(p of people();track p.card){<option [value]="p.name+' ('+p.card+')'"></option>}</datalist>
 @for(race of races;track $index;let ri=$index){<section class="refund-settings"><h2 class="wide-field">Gara {{ri+1}}</h2><label>Nome gara<input [name]="'race'+ri" [(ngModel)]="race.name" maxlength="500" required></label><label>Tipo<select [name]="'kind'+ri" [(ngModel)]="race.kind"><option value="single">Singolo</option><option value="double">Doppio</option></select></label><label>Quota (€)<input [name]="'price'+ri" type="number" step="0.01" min="0" [(ngModel)]="race.euros" required></label><button class="text-button" type="button" (click)="races.splice(ri,1)">Rimuovi gara</button>
 @for(entry of race.entries;track $index;let ei=$index){<div class="manual-entry wide-field"><label>Atleta<input list="manual-athletes" [name]="'name'+ri+'-'+ei" [(ngModel)]="entry.name1" (change)="suggest(entry,1)" maxlength="255" required></label><label>Tessera<input [name]="'card'+ri+'-'+ei" [(ngModel)]="entry.card1" pattern="[0-9]+" maxlength="32" required></label>@if(race.kind==='double'){<label>Secondo atleta<input list="manual-athletes" [name]="'name2'+ri+'-'+ei" [(ngModel)]="entry.name2" (change)="suggest(entry,2)" maxlength="255" required></label><label>Tessera secondo atleta<input [name]="'card2'+ri+'-'+ei" [(ngModel)]="entry.card2" pattern="[0-9]+" maxlength="32" required></label>}<button class="text-button" type="button" (click)="race.entries.splice(ei,1)">Rimuovi iscrizione</button></div>}
 <button class="secondary-button" type="button" (click)="addEntry(race)">Aggiungi iscrizione</button></section>}
 <button class="secondary-button" type="button" (click)="addRace()">Aggiungi gara</button>
 <p><label class="check-field"><input type="checkbox" name="confirmed" [(ngModel)]="confirmed" required>Ho verificato che questo torneo non sia già disponibile o atteso nell’extranet.</label></p>
 <p class="grid-help">Le immagini sono convertite in PDF dal browser. Il limite si applica anche al PDF finale archiviato.</p>
 @if(error()){<p class="error-box" role="alert">{{error()}}</p>}
 <button class="sync-button" type="submit" [disabled]="!races.length||!confirmed||!receipt||!selectedCommittee">{{busy()?'Salvataggio…':'Salva torneo'}}</button> <a routerLink="/tornei">Annulla</a>
 </fieldset></form>
`})
export class ManualTournamentComponent {
 readonly portal=inject(PortalService);private readonly auth=inject(AuthService);private readonly router=inject(Router);
 name='';date='';place='';committee='';committeeName='';total=0;confirmed=false;receipt?:File;poster?:File;receiptLimit=200;posterLimit=200;races:Race[]=[];readonly committees=signal<{id:string;name:string}[]>([]);
 @ViewChild('receiptUpload') private receiptUpload?:ElementRef<HTMLInputElement>;
 @ViewChild('posterUpload') private posterUpload?:ElementRef<HTMLInputElement>;
 readonly importing=signal(false);readonly importNotice=signal('');
 async downloadExample(format:'json'|'xlsx'){if(this.busy()||this.importing())return;this.error.set('');try{const url=new URL('import-templates/esempio-torneo.'+format,config.assetsUrl||new URL('.',import.meta.url).href);const response=await fetch(url);if(!response.ok)throw new Error('Modello non disponibile. Aggiorna la build del frontend.');const blob=await response.blob(),download=URL.createObjectURL(blob),link=document.createElement('a');link.href=download;link.download='esempio-torneo.'+format;link.click();setTimeout(()=>URL.revokeObjectURL(download),1000);}catch(e){this.error.set(e instanceof Error?e.message:errorMessage(e));}}
 async importFile(input:HTMLInputElement,format:'json'|'xlsx'){const file=input.files?.[0];input.value='';if(!file||this.busy()||this.importing())return;this.importing.set(true);this.error.set('');this.importNotice.set('');try{if(!file.name.toLowerCase().endsWith('.'+format)||file.size>(format==='json'?1:2)*1024*1024)throw new Error('Seleziona un file .'+format+' entro '+(format==='json'?1:2)+' MB.');const data=format==='json'?importManualJson(await file.text()):await importManualExcel(new Uint8Array(await file.arrayBuffer()));this.name=data.name;this.date=data.date;this.place=data.place;this.setCommittee(data.committee);this.total=data.total;this.races=data.races;this.confirmed=false;this.receipt=undefined;this.poster=undefined;if(this.receiptUpload)this.receiptUpload.nativeElement.value='';if(this.posterUpload)this.posterUpload.nativeElement.value='';this.importNotice.set('Modulo compilato: '+data.races.length+' gare e '+data.races.reduce((n,r)=>n+r.entries.length,0)+' iscrizioni. Verifica i dati e carica la ricevuta obbligatoria e, se desideri, la locandina.');}catch(e){this.error.set(e instanceof Error?e.message:errorMessage(e));}finally{this.importing.set(false);}}
 readonly busy=signal(false);readonly error=signal('');
 readonly people=computed(()=>{const map=new Map<string,{name:string;card:string}>();for(const e of this.portal.data.entries())for(const p of [{name:e.athlete1_name,card:e.athlete1_card},{name:e.athlete2_name,card:e.athlete2_card}])if(p.name&&p.card)map.set(p.card,{name:p.name,card:p.card});return [...map.values()].sort((a,b)=>a.name.localeCompare(b.name,'it'));});
 constructor(){this.addRace();void this.auth.publicConfig().then(c=>{this.receiptLimit=c.manual_receipt_kb||200;this.posterLimit=c.manual_poster_kb||200;this.committees.set(c.committees||[]);if(this.committee)this.setCommittee(this.committee);}).catch(e=>this.error.set(errorMessage(e)));}
 get selectedCommittee(){return this.committees().find(c=>c.id===this.committee);}
 setCommittee(id:string){this.committee=String(id).replace(/^0+(?=\d)/,'');this.committeeName=this.selectedCommittee?.name||'';}
 addRace(){this.races.push({name:'',kind:'single',euros:0,entries:[]});}
 addEntry(r:Race){r.entries.push({name1:'',card1:'',name2:'',card2:''});}
 suggest(e:Entry,index:1|2){const name=index===1?e.name1:e.name2;const p=this.people().find(p=>p.name+' ('+p.card+')'===name);if(p){if(index===1){e.name1=p.name;e.card1=p.card;}else{e.name2=p.name;e.card2=p.card;}}}
 private async document(file:File,limit:number):Promise<File>{
  if(file.size>limit*1024)throw new Error(file.name+': limite '+limit+' KB superato.');
  if(file.type==='application/pdf')return file;
  if(!['image/jpeg','image/png'].includes(file.type))throw new Error('Sono ammessi PDF, PNG o JPG.');
  const url=URL.createObjectURL(file);try{const image=new Image();image.src=url;await image.decode();if(image.width>8192||image.height>8192)throw new Error('Immagine troppo grande: massimo 8192 pixel per lato.');const {jsPDF}=await import('jspdf');const doc=new jsPDF({compress:true});const factor=Math.min(190/image.width,277/image.height);doc.addImage(image,file.type==='image/png'?'PNG':'JPEG',10,10,image.width*factor,image.height*factor);const blob=doc.output('blob');if(blob.size>limit*1024)throw new Error('Il PDF ricavato da '+file.name+' supera '+limit+' KB. Riduci l’immagine.');return new File([blob],file.name+'.pdf',{type:'application/pdf'});}finally{URL.revokeObjectURL(url);}
 }
 async save(){if(this.busy())return;this.busy.set(true);this.error.set('');try{if(!this.selectedCommittee)throw new Error('Seleziona un comitato valido.');if(!this.receipt)throw new Error('Carica la ricevuta.');const receipt=await this.document(this.receipt,this.receiptLimit),poster=this.poster?await this.document(this.poster,this.posterLimit):undefined;const data={name:this.name,date:this.date,place:this.place,committee:this.committee,committee_name:this.committeeName,total_cents:Math.round(this.total*100),confirmed:this.confirmed,races:this.races.map(r=>({...r,cents:Math.round(r.euros*100)}))};const result=await this.portal.manualTournament(data,receipt,poster);await this.portal.data.refresh();this.portal.setSeason('all');await this.router.navigateByUrl('/tornei/'+result.tournament_key);}catch(e){this.error.set(e instanceof Error?e.message:errorMessage(e));}finally{this.busy.set(false);}}
}
