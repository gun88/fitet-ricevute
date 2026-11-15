import { refundStatistics } from './refund-statistics';
import { EditableHeaderComponent } from './editable-header.component';
import { IconComponent } from './icon.component';
import { GridSizingDirective } from './grid-sizing.directive';
import { GridPresentation } from './grid-presentation.service';
import { ClearSearchComponent } from './clear-search.component';
import { ResponsiveService } from './responsive.service';
import { MobileListComponent } from './mobile-list.component';
import { playerKey } from './players-model';
import { Component, Input, OnInit, OnDestroy, OnChanges, SimpleChanges, Injector, inject, effect, runInInjectionContext, signal } from '@angular/core';
import { RouterLink } from '@angular/router';
import { AgGridAngular } from 'ag-grid-angular';
import { ColDef, GridApi, StateUpdatedEvent } from 'ag-grid-community';
import { PortalService } from './portal.service';
import { money, dateLabel, names, originLabel, tournamentType, athleteLabel } from './format';
import { TableView } from './table-preferences';
import { defaultColDef, gridTheme } from './grid';
import { AthleteLinksComponent } from './player-links.component';
interface Link {icon?:'printer';label:string;url:string;query?:Record<string,string>}
@Component({selector:'fr-entity-links',imports:[IconComponent,RouterLink],template:`<span class="row-links">@for(link of links();track link.label){<a [routerLink]="link.url" [queryParams]="link.query" [attr.aria-label]="link.label">@if(link.icon){<fr-icon [name]="link.icon" />}@else{ {{link.label}} }</a>}</span>`})
export class EntityLinksComponent {
 params:any;agInit(p:any){this.params=p;}refresh(p:any){this.params=p;return true;}links():Link[]{return this.params.links(this.params.data);}
}
@Component({selector:'fr-count-link',imports:[RouterLink],template:`<span>{{params.value}} - <a [routerLink]="params.target" [queryParams]="params.scope==='race'?{gara:String(params.data.race_id||params.data.id)}:{torneo:params.data.tournament_key}">visualizza</a></span>`})
export class CountLinkComponent {readonly String=String;params:any;agInit(p:any){this.params=p;}refresh(p:any){this.params=p;return true;}}
@Component({selector:'fr-poster-link',template:`@if(url){<a [href]="url" target="_blank" rel="noopener noreferrer">Visualizza locandina</a>}@else{<span>—</span>}`})
export class PosterLinkComponent {url:string|null=null;agInit(p:any){this.url=p.data?.poster_url||null;}refresh(p:any){this.agInit(p);return true;}}
@Component({selector:'fr-cart-action',imports:[IconComponent],template:`<span class="inline-row-actions"><button type="button" class="quota-toggle" [class.remove]="inCart()" [disabled]="portal.data.locked()||(!params?.cartMode&&row.reimbursed)||row.stale" [attr.aria-label]="params?.add?'Aggiungi al rimborso':inCart()?'Rimuovi dalla bozza':'Aggiungi alla bozza'" (click)="toggle($event)"><fr-icon [name]="inCart()?'minus':'plus'" /></button>@if(params?.cartMode){<button type="button" class="quota-edit" aria-label="Modifica nota rimborso" (click)="editNote($event)"><fr-icon name="edit" /></button>}</span>`})
export class CartActionComponent {
 readonly portal=inject(PortalService);row:any;params:any;
 agInit(p:any){this.params=p;this.row=p.data;}refresh(p:any){this.agInit(p);return true;}
 inCart(){if(this.params?.add)return false;return this.params?.cartMode||(!this.row.reimbursed&&this.portal.cartIds().has(this.row.id));}
 toggle(e:Event){e.stopPropagation();if(this.params?.add){this.params.add(this.row);return;}if(this.params?.cartMode&&this.params?.remove)this.params.remove(this.row);else void this.portal.changeCart([this.row],!this.inCart());}
 editNote(e:Event){e.stopPropagation();this.params.api.ensureColumnVisible('refund_notes');this.params.api.setFocusedCell(this.params.node.rowIndex,'refund_notes');this.params.api.startEditingCell({rowIndex:this.params.node.rowIndex,colKey:'refund_notes'});}
}
@Component({selector:'fr-entity-grid',imports:[IconComponent,GridSizingDirective,AgGridAngular,MobileListComponent,RouterLink,ClearSearchComponent],template:`
 <div class="grid-toolbar"><label class="grid-search">{{responsive.mobile()?'Cerca nell’elenco':'Cerca nella tabella'}}<input type="search" [attr.aria-label]="'Cerca '+label" [value]="view.search()" (input)="view.setSearch($any($event.target).value)"></label><div class="grid-extra-filters"><ng-content select="[tableFilter]" /></div>@if(responsive.mobile()){<fr-search-clear (clear)="view.setSearch('')" />}@else{<div class="grid-toolbar-actions"><button class="text-button" (click)="view.reset(api)">Azzera filtri e ordine</button>
 @if(kind==='entries' && !cartMode && !responsive.mobile()){<button class="secondary-button" [disabled]="!selected().length || portal.data.locked()" (click)="addSelected()">Aggiungi selezionate ({{selected().length}})</button>}</div>}</div>
 @if(responsive.mobile()){
 <fr-mobile-list [rows]="rows" [search]="view.search()" [text]="cardText" [sort]="cardSort" [label]="label">
 <ng-template let-row><article class="mobile-card" [class.fr-invalid-refund-row]="invalidDraftRow(row)">
 <div class="card-heading"><h3>@for(link of cardTitleLinks(row);track $index){@if($index){ / }<a [routerLink]="link.url" [attr.aria-label]="link.label">@if(link.icon){<fr-icon [name]="link.icon" />}@else{ {{link.label}} }</a>}</h3><span class="card-amount">{{kind==='emissions'?money(row.total_cents):kind==='entries'||kind==='races'?money(row.price_cents):row.entry_count+' iscrizioni'}}</span></div>
 <p class="card-subtitle">{{kind==='emissions'?timestamp(row.created_at):dateLabel(row.tournament_date)}} @if(row.tournament_place){ · {{row.tournament_place}}}</p>
 @if(kind==='entries'){<p class="card-context">{{row.tournament_name}} · {{row.race_name}}</p>@if(!cartMode){<span class="card-status">{{portal.state(row)}}</span>}}
 @if(kind==='tournaments'||kind==='races'){<span class="card-status">Rimborsi: {{portal.refundStats(row.tournament_key,kind==='races'?Number(row.race_id||row.id):undefined).count}}</span>}
 @if(kind==='races'){<p class="card-context">{{row.tournament_name}}</p>}
 <details [open]="expanded().has(row.id)"><summary (click)="$event.preventDefault();toggleCard(row.id)"><fr-icon class="disclosure-icon" name="chevron-right" [size]="16" />Informazioni e dettagli</summary><dl class="card-fields">
 @for(field of cardFields(row);track field.label){<dt>{{field.label}}</dt><dd>@if(field.links){@for(link of field.links;track $index){<a [routerLink]="link.url" [queryParams]="link.query" [attr.aria-label]="link.label">@if(link.icon){<fr-icon [name]="link.icon" />}@else{ {{link.label}} }</a> }}@else{ {{field.value}} }</dd>}
 </dl>
 @if(kind==='races'){<form class="card-editor" (submit)="$event.preventDefault();portal.data.price(row,price.value)"><label>Quota (€)<input #price type="text" inputmode="decimal" [value]="row.price_cents==null?'':row.price_cents/100" placeholder="Automatica"></label><button class="secondary-button" [disabled]="portal.data.locked()">Salva quota</button></form>}
 @if(cartMode){<form class="card-editor" (submit)="$event.preventDefault();onNote?onNote(row,notes.value):portal.updateLine(row,notes.value)"><label>Note riga<textarea #notes maxlength="1000" rows="3" [value]="row.refund_notes||''"></textarea></label><button class="secondary-button" [disabled]="portal.data.locked()">Salva note</button></form>}
 </details>
 <div class="card-actions">@if(kind==='tournaments'&&row.poster_url){<a [href]="row.poster_url" target="_blank" rel="noopener noreferrer">Visualizza locandina</a>}@for(link of cardActions(row);track link.label){<a [routerLink]="link.url" [queryParams]="link.query" [attr.aria-label]="link.label">@if(link.icon){<fr-icon [name]="link.icon" />}@else{ {{link.label}} }</a>}
 @if(kind==='entries'){<button class="secondary-button" [disabled]="portal.data.locked()||(!cartMode&&(row.reimbursed||row.stale||(!onAdd&&portal.cartIds().has(row.id))))" (click)="onAdd?onAdd(row):cartMode&&onRemove?onRemove(row):portal.changeCart([row],!cartMode)">@if(onAdd){<fr-icon name="plus" />}{{onAdd?'Aggiungi al rimborso':cartMode?'Rimuovi':portal.cartIds().has(row.id)?'In bozza rimborso':'Metti in bozza'}}</button>}
 </div></article></ng-template></fr-mobile-list>
 }@else{ @if(view){<ag-grid-angular [id]="viewKey+'-grid'" class="main-grid" [theme]="presentation.theme()" [domLayout]="presentation.layout()" [class.auto-height]="presentation.autoHeight()" [rowData]="rows" [rowClassRules]="rowClasses" [columnDefs]="columns" [defaultColDef]="defaults" [getRowId]="rowId" [pagination]="true" [paginationPageSize]="100" [paginationPageSizeSelector]="[25,50,100]" [quickFilterText]="view.search()" [frGridSizing]="view" [initialState]="view.initialState" (stateUpdated)="stateUpdated($event)" (gridReady)="ready($event.api)" (firstDataRendered)="restoreFilters($event.api)" [rowSelection]="selection" [selectionColumnDef]="selectionColumn" (selectionChanged)="selected.set($event.api.getSelectedRows())" [readOnlyEdit]="true" [singleClickEdit]="true" [stopEditingWhenCellsLoseFocus]="true" (cellEditRequest)="edit($event)" />}
 }
`})
export class EntityGridComponent implements OnInit, OnDestroy, OnChanges {
 invalidDraftRow(row:any){return this.cartMode&&!!row&&(!!row.stale||(!this.onRemove&&!!row.reimbursed)||row.price_cents==null);}
 invalidDraftReason(row:any){return row.stale?'Iscrizione non più disponibile: rimuovila dalla bozza.':row.reimbursed&&!this.onRemove?'Quota già rimborsata: rimuovila dalla bozza.':'Quota senza prezzo: definiscilo prima di emettere il rimborso.';}
 readonly rowClasses={'fr-invalid-refund-row':(p:any)=>this.invalidDraftRow(p.data)};
 readonly presentation=inject(GridPresentation);
 @Input() onAdd?: (row:any)=>void;@Input() onRemove?: (row:any)=>void;@Input() onNote?: (row:any,notes:string)=>void;
 @Input() rows:any[]=[];@Input() kind:'tournaments'|'races'|'entries'|'emissions'='entries';@Input() viewKey='entries';@Input() label='iscrizioni';@Input() cartMode=false;
 readonly portal=inject(PortalService);private readonly injector=inject(Injector);
 readonly theme=gridTheme;readonly defaults=defaultColDef;readonly selected=signal<any[]>([]);readonly rowId=(p:any)=>String(p.data.id);
 constructor(){effect(()=>{this.portal.cart();this.portal.data.entries();this.api?.refreshCells({force:true});});effect(()=>{if(this.responsive.mobile()){this.api=undefined;this.selected.set([]);}});}
 readonly expanded=signal<Set<any>>(new Set());toggleCard(id:any){this.expanded.update(current=>{const next=new Set(current);if(next.has(id))next.delete(id);else next.add(id);return next;});}
 readonly responsive=inject(ResponsiveService);readonly Number=Number;readonly money=money;readonly dateLabel=dateLabel;readonly timestamp=(value:string)=>new Date(value).toLocaleString('it-IT');
 readonly cardSort=(a:any,b:any)=>String(b.tournament_date||b.created_at||'').localeCompare(String(a.tournament_date||a.created_at||''))||String(a.id).localeCompare(String(b.id));
 cardTitle(row:any){return this.kind==='entries'?names(row):this.kind==='races'?row.race_name:this.kind==='emissions'?'Rimborso #'+row.id:row.tournament_name;}
 cardTitleLinks(row:any):Link[]{
  if(this.kind==='entries')return [{label:athleteLabel(row.athlete1_name,row.athlete1_card),url:'/giocatori/'+playerKey(row.athlete1_name,row.athlete1_card)},...(row.athlete2_name?[{label:athleteLabel(row.athlete2_name,row.athlete2_card),url:'/giocatori/'+playerKey(row.athlete2_name,row.athlete2_card)}]:[])];
  return [{label:this.cardTitle(row),url:this.kind==='tournaments'?'/tornei/'+row.tournament_key:this.kind==='races'?'/gare/'+(row.race_id||row.id):'/rimborsi/'+row.id}];
 }
 cardActions(row:any):Link[]{if(this.kind==='tournaments')return [{label:'Gare',url:'/gare',query:{torneo:row.tournament_key}},{label:'Iscrizioni',url:'/iscrizioni',query:{torneo:row.tournament_key}}];if(this.kind==='races')return [{label:'Iscrizioni',url:'/iscrizioni',query:{gara:String(row.race_id||row.id)}}];return this.columns.find(col=>col.field==='actions')?.cellRendererParams?.links(row)||[];}
 cardFields(row:any):{label:string;value:any;links?:Link[]}[]{return this.columns.filter(col=>col.field!=='actions'&&col.colId!=='cart'&&col.colId!=='poster').map(col=>{
  let value=col.field?row[col.field]:null;
  if(typeof col.valueGetter==='function')value=col.valueGetter({data:row} as any);
  if(typeof col.valueFormatter==='function')value=col.valueFormatter({data:row,value} as any);
  let links:Link[]|undefined=col.cellRendererParams?.links?.(row);if(col.cellRenderer===CountLinkComponent)links=[{label:String(value)+' - visualizza',url:col.cellRendererParams.target,query:col.cellRendererParams.scope==='race'?{gara:String(row.race_id||row.id)}:{torneo:row.tournament_key}}];
  if(col.colId==='athletes')links=[{label:athleteLabel(row.athlete1_name,row.athlete1_card),url:'/giocatori/'+playerKey(row.athlete1_name,row.athlete1_card)},...(row.athlete2_name?[{label:athleteLabel(row.athlete2_name,row.athlete2_card),url:'/giocatori/'+playerKey(row.athlete2_name,row.athlete2_card)}]:[])];
  return {label:col.headerName||'',value:value??'—',links};
 });}
 readonly cardText=(row:any)=>this.cardFields(row).map(field=>String(field.value)+' '+(field.links?.map(link=>link.label).join(' ')||'')).join(' ');
 readonly selectionColumn:any={pinned:'left',width:80,minWidth:32,maxWidth:240,resizable:false,cellRenderer:CartActionComponent,cellRendererParams:()=>({cartMode:this.cartMode,remove:this.onRemove,add:this.onAdd})};
 private unwatch=()=>{};
 ngOnDestroy(){this.unwatch();}
 view!:TableView;api?:GridApi;columns:ColDef<any>[]=[];selection:any;
 restoreFilters(api:GridApi){if(this.view.initialState?.filter)api.setFilterModel(structuredClone(this.view.initialState.filter.filterModel||{}));}
 ready(api:GridApi){this.api=api;this.view.restore(api);}
 stateUpdated(event:StateUpdatedEvent){if(!this.responsive.mobile())this.view.updated(event);}

 async addSelected(){if(this.onAdd){const rows=this.selected();for(const row of rows)this.onAdd(row);}else await this.portal.changeCart(this.selected(),true);this.api?.deselectAll();this.selected.set([]);}
 ngOnChanges(changes:SimpleChanges){if(changes['rows']){this.selected.set([]);this.api?.deselectAll();}}
 edit(event:any){if(this.cartMode&&event.colDef.field==='refund_notes'){if(this.onNote)this.onNote(event.data,String(event.newValue??''));else void this.portal.updateLine(event.data,String(event.newValue??''));}else void this.portal.data.price(event.data,event.newValue);}
 private link(field:string,title:string,links:(row:any)=>Link[],width=240):ColDef<any>{return {field,headerName:title,width,...(field==='actions'?{pinned:'right' as const,filter:false,sortable:false}:{}),cellRenderer:EntityLinksComponent,cellRendererParams:{links}};}
 ngOnInit(){
  this.view=runInInjectionContext(this.injector,()=>new TableView('portal-'+this.viewKey));this.unwatch=this.responsive.preserve(()=>this.api,this.view);
  const tournament=(row:any):Link[]=>[{label:row.tournament_name,url:'/tornei/'+row.tournament_key}];
  const race=(row:any):Link[]=>[{label:row.race_name,url:'/gare/'+(row.race_id||row.id)}];
  const date:ColDef<any>={field:'tournament_date',headerName:'Data torneo',width:140,valueFormatter:p=>dateLabel(p.value),sort:'desc'};
  if(this.kind==='tournaments')this.columns=[date,this.link('tournament_name','Torneo',tournament,290),{field:'tournament_place',headerName:'Località'},{colId:'committee_label',headerName:'Tipo',valueGetter:p=>tournamentType(p.data)},{field:'entry_count',headerName:'Iscrizioni',filter:'agNumberColumnFilter',cellRenderer:CountLinkComponent,cellRendererParams:{target:'/iscrizioni'}},{field:'race_count',headerName:'Gare',filter:'agNumberColumnFilter',cellRenderer:CountLinkComponent,cellRendererParams:{target:'/gare'}},{colId:'refund_count',headerName:'Rimborsi',filter:'agNumberColumnFilter',cellDataType:'number',valueGetter:p=>this.portal.refundStats(p.data.tournament_key).count},{colId:'poster',headerName:'Locandina',width:210,sortable:false,filter:false,cellRenderer:PosterLinkComponent}];
  if(this.kind==='races')this.columns=[date,this.link('tournament_name','Torneo',tournament,260),this.link('race_name','Gara',race,250),{field:'entry_count',headerName:'Iscrizioni',filter:'agNumberColumnFilter',cellDataType:'number',valueGetter:p=>Number(p.data.entry_count),cellRenderer:CountLinkComponent,cellRendererParams:{target:'/iscrizioni',scope:'race'}},
   {colId:'refund_count',headerName:'Rimborsi',filter:'agNumberColumnFilter',cellDataType:'number',valueGetter:p=>this.portal.refundStats(p.data.tournament_key,Number(p.data.race_id||p.data.id)).count},
   {colId:'quota',headerName:'Quota (€)',headerTooltip:'Quota modificabile',headerComponentParams:{innerHeaderComponent:EditableHeaderComponent},width:150,filter:'agNumberColumnFilter',valueGetter:p=>p.data.price_cents==null?null:Number(p.data.price_cents)/100,valueFormatter:p=>p.value==null?'Da definire':money(Math.round(p.value*100)),editable:()=>!this.portal.data.locked(),cellEditor:'agTextCellEditor',cellDataType:false},
   {colId:'origin',headerName:'Origine prezzo',valueGetter:p=>originLabel(p.data)},
   {field:'receipt_total_cents',headerName:'Totale PDF',valueFormatter:p=>money(Number(p.value))},
   {field:'reconciliation',headerName:'Verifica PDF',valueFormatter:p=>p.value==='match'?'Coincide':p.value==='mismatch'?'Non coincide':'Da completare'},
   {field:'difference_cents',headerName:'Scostamento',valueFormatter:p=>money(p.value==null?null:Number(p.value))}];
  if(this.kind==='entries'){
   this.selection={mode:'multiRow',checkboxes:true,headerCheckbox:true,selectAll:'filtered',enableClickSelection:false,isRowSelectable:(node:any)=>this.cartMode||(!node.data?.reimbursed&&!node.data?.stale&&(!!this.onAdd||!this.portal.cartIds().has(node.data?.id))),hideDisabledCheckboxes:false};
   this.columns=[date,this.link('tournament_name','Torneo',tournament,260),this.link('race_name','Gara',race,230),{colId:'athletes',headerName:'Atleta / Coppia',width:265,valueGetter:p=>[athleteLabel(p.data.athlete1_name,p.data.athlete1_card),athleteLabel(p.data.athlete2_name,p.data.athlete2_card)].filter(Boolean).join(' / '),cellRenderer:AthleteLinksComponent},
    
    {field:'price_cents',headerName:'Quota',valueFormatter:p=>money(p.value==null?null:Number(p.value))},
    {colId:'status',headerName:'Stato quota',valueGetter:p=>this.portal.state(p.data)},
    {field:'refund_notes',headerName:'Note riga',width:270,editable:()=>this.cartMode&&!this.portal.data.locked(),cellEditor:'agLargeTextCellEditor',cellEditorPopup:true,cellEditorParams:{maxLength:1000},wrapText:true,autoHeight:true},
    {field:'paid_cents',headerName:'Rimborsato',valueFormatter:p=>money(p.value==null?null:Number(p.value))}];
   if(!this.cartMode)this.columns=this.columns.filter(col=>col.field!=='refund_notes');
   if(this.cartMode)this.columns=this.columns.filter(col=>!['status','tournament_refund_count'].includes(col.colId||'')&&col.field!=='paid_cents');
  }
  if(this.kind==='emissions')this.columns=[this.link('id','Rimborso',r=>[{label:'Rimborso #'+r.id,url:'/rimborsi/'+r.id}],180),{field:'created_at',headerName:'Data e ora emissione',width:240,sort:'desc',valueFormatter:p=>new Date(p.value).toLocaleString('it-IT')},{field:'total_cents',headerName:'Importo emesso',valueFormatter:p=>money(Number(p.value))},{colId:'tournaments',headerName:'Tornei',filter:'agNumberColumnFilter',cellDataType:'number',valueGetter:p=>new Set(p.data.items.map((r:any)=>r.tournament_key)).size},{colId:'races',headerName:'Gare',filter:'agNumberColumnFilter',cellDataType:'number',valueGetter:p=>refundStatistics([p.data]).races},{colId:'players',headerName:'Giocatori',filter:'agNumberColumnFilter',cellDataType:'number',valueGetter:p=>refundStatistics([p.data]).players},{colId:'count',headerName:'Iscrizioni',filter:'agNumberColumnFilter',cellDataType:'number',valueGetter:p=>p.data.items.length}];
 }
}
