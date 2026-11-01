import { Component, Input, OnInit, OnChanges, SimpleChanges, Injector, inject, effect, runInInjectionContext, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { AgGridAngular } from 'ag-grid-angular';
import { ColDef, GridApi } from 'ag-grid-community';
import { PortalService } from './portal.service';
import { money, dateLabel, names, originLabel } from './format';
import { TableView } from './table-preferences';
import { defaultColDef, gridTheme } from './grid';
import { AthleteLinksComponent } from './player-links.component';
interface Link {label:string;url:string;query?:Record<string,string>}
@Component({selector:'fr-entity-links',imports:[RouterLink],template:`<span class="row-links">@for(link of links();track link.label){<a [routerLink]="link.url" [queryParams]="link.query">{{link.label}}</a>}</span>`})
export class EntityLinksComponent {
 params:any;agInit(p:any){this.params=p;}refresh(p:any){this.params=p;return true;}links():Link[]{return this.params.links(this.params.data);}
}
@Component({selector:'fr-cart-action',template:`<button class="grid-action" [disabled]="portal.data.locked() || (!remove && (row.reimbursed || portal.cartIds().has(row.id) || row.stale))" (click)="portal.changeCart([row],!remove)">{{remove?'Rimuovi':portal.cartIds().has(row.id)?'Nel carrello':'Aggiungi al carrello'}}</button>`})
export class CartActionComponent {
 readonly portal=inject(PortalService);row:any;remove=false;
 agInit(p:any){this.row=p.data;this.remove=!!p.remove;}refresh(p:any){this.agInit(p);return true;}
}
@Component({selector:'fr-entity-grid',imports:[AgGridAngular],template:`
 <div class="grid-toolbar"><label>Cerca nella tabella<input type="search" [attr.aria-label]="'Cerca '+label" [value]="view.search()" (input)="view.setSearch($any($event.target).value)"></label><button class="text-button" (click)="view.reset(api)">Azzera filtri e ordine</button>
 @if(kind==='entries' && !cartMode){<button class="secondary-button" [disabled]="!selected().length || portal.data.locked()" (click)="addSelected()">Aggiungi selezionate ({{selected().length}})</button>}</div>
 @if(view){<ag-grid-angular [id]="viewKey+'-grid'" class="main-grid" [theme]="theme" [rowData]="rows" [columnDefs]="columns" [defaultColDef]="defaults" [getRowId]="rowId" [pagination]="true" [paginationPageSize]="100" [paginationPageSizeSelector]="[25,50,100]" [quickFilterText]="view.search()" [initialState]="view.initialState" (stateUpdated)="view.updated($event)" (gridReady)="api=$event.api" [rowSelection]="selection" [selectionColumnDef]="selectionColumn" (selectionChanged)="selected.set($event.api.getSelectedRows())" [readOnlyEdit]="true" [singleClickEdit]="true" [stopEditingWhenCellsLoseFocus]="true" (cellEditRequest)="portal.data.price($event.data,$event.newValue)" />}
`})
export class EntityGridComponent implements OnInit, OnChanges {
 @Input() rows:any[]=[];@Input() kind:'tournaments'|'races'|'entries'|'emissions'='entries';@Input() viewKey='entries';@Input() label='iscrizioni';@Input() cartMode=false;
 readonly portal=inject(PortalService);private readonly injector=inject(Injector);
 readonly theme=gridTheme;readonly defaults=defaultColDef;readonly selected=signal<any[]>([]);readonly rowId=(p:any)=>String(p.data.id);
 constructor(){effect(()=>{this.portal.cart();this.portal.data.entries();this.api?.refreshCells({force:true});});}
 readonly selectionColumn:any={pinned:'left',width:48,minWidth:48,maxWidth:48,resizable:false};
 view!:TableView;api?:GridApi;columns:ColDef<any>[]=[];selection:any;
 async addSelected(){await this.portal.changeCart(this.selected(),true);this.api?.deselectAll();this.selected.set([]);}
 ngOnChanges(changes:SimpleChanges){if(changes['rows']){this.selected.set([]);this.api?.deselectAll();}}
 private link(field:string,title:string,links:(row:any)=>Link[],width=240):ColDef<any>{return {field,headerName:title,width,...(field==='actions'?{pinned:'right' as const}:{}),cellRenderer:EntityLinksComponent,cellRendererParams:{links}};}
 ngOnInit(){
  this.view=runInInjectionContext(this.injector,()=>new TableView('portal-'+this.viewKey));
  const tournament=(row:any):Link[]=>[{label:row.tournament_name,url:'/tornei/'+row.tournament_key}];
  const race=(row:any):Link[]=>[{label:row.race_name,url:'/gare/'+(row.race_id||row.id)}];
  const date:ColDef<any>={field:'tournament_date',headerName:'Data torneo',width:140,valueFormatter:p=>dateLabel(p.value),sort:'desc'};
  if(this.kind==='tournaments')this.columns=[date,this.link('tournament_name','Torneo',tournament,290),{field:'tournament_place',headerName:'Località'},{field:'committee_label',headerName:'Comitato'},{field:'entry_count',headerName:'Iscrizioni',filter:'agNumberColumnFilter'},{field:'race_count',headerName:'Gare',filter:'agNumberColumnFilter'},{colId:'status',headerName:'Rimborsi',valueGetter:p=>this.portal.tournamentState(p.data.tournament_key)},this.link('actions','Azioni',r=>[{label:'Dettaglio',url:'/tornei/'+r.tournament_key},{label:'Gare',url:'/gare',query:{torneo:r.tournament_key}},{label:'Iscrizioni',url:'/iscrizioni',query:{torneo:r.tournament_key}}],280)];
  if(this.kind==='races')this.columns=[date,this.link('tournament_name','Torneo',tournament,260),this.link('race_name','Gara',race,250),{field:'entry_count',headerName:'Iscrizioni',filter:'agNumberColumnFilter'},
   {colId:'quota',headerName:'Quota (€) ✎',width:150,filter:'agNumberColumnFilter',valueGetter:p=>p.data.price_cents==null?null:Number(p.data.price_cents)/100,valueFormatter:p=>p.value==null?'Da definire':money(Math.round(p.value*100)),editable:()=>!this.portal.data.locked(),cellEditor:'agTextCellEditor',cellDataType:false},
   {colId:'origin',headerName:'Origine prezzo',valueGetter:p=>originLabel(p.data)},
   {field:'receipt_total_cents',headerName:'Totale PDF',valueFormatter:p=>money(Number(p.value))},
   {field:'reconciliation',headerName:'Verifica PDF',valueFormatter:p=>p.value==='match'?'Coincide':p.value==='mismatch'?'Non coincide':'Da completare'},
   {field:'difference_cents',headerName:'Scostamento',valueFormatter:p=>money(p.value==null?null:Number(p.value))},
   this.link('actions','Azioni',r=>[{label:'Dettaglio',url:'/gare/'+r.id},{label:'Iscrizioni',url:'/iscrizioni',query:{gara:String(r.id)}}],230)];
  if(this.kind==='entries'){
   this.selection={mode:'multiRow',checkboxes:true,headerCheckbox:true,selectAll:'filtered',enableClickSelection:false,isRowSelectable:(node:any)=>!node.data?.reimbursed&&!node.data?.stale&&!this.portal.cartIds().has(node.data?.id)};
   this.columns=[date,this.link('tournament_name','Torneo',tournament,260),this.link('race_name','Gara',race,230),{colId:'athletes',headerName:'Atleta / Coppia',width:265,valueGetter:p=>names(p.data),cellRenderer:AthleteLinksComponent},
    {colId:'cards',headerName:'Tessera',valueGetter:p=>[p.data.athlete1_card,p.data.athlete2_card].filter(Boolean).join(' / ')},
    {field:'price_cents',headerName:'Quota',valueFormatter:p=>money(p.value==null?null:Number(p.value))},
    {colId:'status',headerName:'Stato quota',valueGetter:p=>this.portal.state(p.data)},
    {colId:'tournament_status',headerName:'Stato torneo',valueGetter:p=>this.portal.tournamentState(p.data.tournament_key)},
    {field:'paid_cents',headerName:'Rimborsato',valueFormatter:p=>money(p.value==null?null:Number(p.value))},
    {colId:'cart',headerName:'Carrello',width:190,pinned:'right',sortable:false,filter:false,cellRenderer:CartActionComponent,cellRendererParams:{remove:this.cartMode}}];
  }
  if(this.kind==='emissions')this.columns=[this.link('id','Rimborso',r=>[{label:'Rimborso #'+r.id,url:'/rimborsi/'+r.id}],180),{field:'created_at',headerName:'Data e ora emissione',width:240,sort:'desc',valueFormatter:p=>new Date(p.value).toLocaleString('it-IT')},{field:'total_cents',headerName:'Importo emesso',valueFormatter:p=>money(Number(p.value))},{colId:'count',headerName:'Iscrizioni',valueGetter:p=>p.data.items.length},{colId:'issuer',headerName:'Emittente',valueGetter:p=>p.data.options.issuer}];
 }
}
