import { Directive, ElementRef, Input, OnDestroy, inject, effect } from '@angular/core';
import { AgGridAngular } from 'ag-grid-angular';
import type { Column, GridApi, ColumnResizedEvent } from 'ag-grid-community';
import { TableView } from './table-preferences';
import { GridPresentation } from './grid-presentation.service';
import { contentWidths, WidthInput } from './grid-widths';

@Directive({selector:'ag-grid-angular[frGridSizing]'})
export class GridSizingDirective implements OnDestroy {
 @Input({required:true}) frGridSizing!:TableView;
 private readonly grid=inject(AgGridAngular);private readonly element:HTMLElement=inject(ElementRef).nativeElement;private readonly presentation=inject(GridPresentation);
 private api?:GridApi;private frame=0;private destroyed=false;private initialized=false;private applying=false;private lastWidth=0;
 private readonly canvas=document.createElement('canvas').getContext('2d')!;
 private readonly observer=new ResizeObserver(entries=>{const width=entries[0]?.contentRect.width??0;if(width>0&&Math.abs(width-this.lastWidth)>1){this.lastWidth=width;this.schedule();}});
 private readonly ready=this.grid.gridReady.subscribe(e=>{this.api=e.api;for(const name of ['firstDataRendered','rowDataUpdated','columnVisible','columnPinned','newColumnsLoaded','gridSizeChanged'] as const)e.api.addEventListener(name,this.schedule);e.api.addEventListener('columnResized',this.resized);this.observer.observe(this.element);this.schedule();});
 constructor(){effect(()=>{this.presentation.theme();this.schedule();});}
 private readonly resized=(event:ColumnResizedEvent)=>{if(this.applying||!event.finished)return;if(event.source==='uiColumnResized'||event.source==='uiColumnDragged'||event.source==='autosizeColumns'){for(const col of event.columns||[event.column])if(col&&!this.fixed(col))this.frGridSizing.setManualColumnWidth(col.getColId(),col.getActualWidth());this.schedule();}};
 private fixed(col:Column){return col.getColDef().resizable===false||col.getColId().includes('SelectionColumn');}
 private readonly schedule=()=>{if(this.destroyed)return;cancelAnimationFrame(this.frame);this.frame=requestAnimationFrame(()=>{this.frame=requestAnimationFrame(()=>this.size());});};
 private size(){const api=this.api;if(this.destroyed||!api||api.isDestroyed()||!this.element.clientWidth)return;
  const columns=api.getAllDisplayedColumns();if(!columns.length)return;
  this.applying=true;
  try{
   if(!this.initialized){api.applyColumnState({state:columns.map(c=>({colId:c.getColId(),flex:null}))});this.initialized=true;}
   const root=this.element.querySelector<HTMLElement>('.ag-root')||this.element;
   const cells=new Map<string,HTMLElement[]>();for(const cell of Array.from(this.element.querySelectorAll<HTMLElement>('.ag-cell[col-id]'))){const id=cell.getAttribute('col-id')!;const items=cells.get(id)||[];items.push(cell);cells.set(id,items);}
   const headers=new Map(Array.from(this.element.querySelectorAll<HTMLElement>('.ag-header-cell[col-id]')).map(h=>[h.getAttribute('col-id')!,h]));
   const fallback=this.element.querySelector<HTMLElement>('.ag-cell')||root,base=getComputedStyle(fallback),font=base.font||'12px Arial',padding=(parseFloat(base.paddingLeft)||16)+(parseFloat(base.paddingRight)||16)+2;
   const manual=this.frGridSizing.manualColumnWidths;const specifications:WidthInput[]=columns.map(col=>{const id=col.getColId(),def=col.getColDef(),head=headers.get(id),headerStyle=getComputedStyle(head?.querySelector('.ag-header-cell-text')||head||fallback);this.canvas.font=headerStyle.font||font;const title=api.getDisplayNameForColumn(col,'header')||'';const inlineIcons=Array.from(head?.querySelectorAll('svg')||[]).filter(svg=>!!svg.closest('.ag-header-cell-text')).reduce((n,svg)=>n+svg.getBoundingClientRect().width+6,0);const headerWidth=this.canvas.measureText(title).width+inlineIcons+padding+(def.sortable?18:0)+(def.filter?24:0);return {id,natural:headerWidth,min:Math.min(col.getMinWidth(),420),max:def.maxWidth,fixed:this.fixed(col),pinned:!!col.getPinned(),manual:this.fixed(col)||!Object.hasOwn(manual,id)?undefined:manual[id]};});
   for(const spec of specifications){if(!spec.fixed)continue;const header=headers.get(spec.id),checkbox=header?.querySelector<HTMLElement>('.ag-header-select-all');if(header&&checkbox){const style=getComputedStyle(header);spec.natural=Math.max(spec.natural,checkbox.getBoundingClientRect().width+(parseFloat(style.paddingLeft)||0)+(parseFloat(style.paddingRight)||0)+2);}}
   this.canvas.font=font;const measured=new Map<string,number>(),dataWidths=new Map<string,number>();
   api.forEachNode(node=>{if(!node.data)return;for(let i=0;i<columns.length;i++){const spec=specifications[i];if(spec.fixed||spec.manual!==undefined||spec.natural>=420)continue;const value=api.getCellValue({rowNode:node,colKey:columns[i],useFormatter:true});if(value===null||value===undefined)continue;const text=String(value);let width=measured.get(text);if(width===undefined){width=Math.max(...text.split(/\r?\n/).map(line=>this.canvas.measureText(line).width))+padding;measured.set(text,width);}spec.natural=Math.max(spec.natural,width);dataWidths.set(spec.id,Math.max(dataWidths.get(spec.id)||0,width));}});
   // Measure rendered controls and links in their actual theme, without stretching clones to the column width.
   const measure=document.createElement('div');measure.style.cssText='position:fixed;left:-100000px;top:0;visibility:hidden;pointer-events:none;width:max-content;height:auto;';root.appendChild(measure);
   try{for(const spec of specifications){if(spec.manual!==undefined&&!spec.fixed)continue;let extra=0;for(const cell of (cells.get(spec.id)||[]).slice(0,24)){const clone=cell.cloneNode(true) as HTMLElement;clone.style.cssText='position:static;transform:none;left:auto;width:max-content;min-width:0;max-width:none;height:auto;display:flex;flex:none;';clone.style.font=getComputedStyle(cell).font;for(const child of Array.from(clone.querySelectorAll<HTMLElement>('.ag-cell-wrapper,.ag-cell-value'))){child.style.width='max-content';child.style.flex='none';child.style.overflow='visible';}for(const control of Array.from(clone.querySelectorAll<HTMLElement>('select,input,textarea')))control.style.width='auto';measure.appendChild(clone);const width=Math.ceil(clone.getBoundingClientRect().width)+1;if(width>0)spec.natural=Math.max(spec.natural,width);if(!spec.fixed){const node=api.getRowNode(cell.closest('.ag-row')?.getAttribute('row-id')||'');if(node){const value=api.getCellValue({rowNode:node,colKey:spec.id,useFormatter:true});if(value!==null&&value!==undefined){this.canvas.font=font;const raw=Math.max(...String(value).split(/\r?\n/).map(line=>this.canvas.measureText(line).width))+padding;extra=Math.max(extra,width-raw);}}}clone.remove();}spec.natural=Math.max(spec.natural,(dataWidths.get(spec.id)||0)+extra);}}finally{measure.remove();}
   const viewport=this.element.querySelector<HTMLElement>('.ag-grid-viewport');const available=viewport?.clientWidth||root.clientWidth;
   const sizes=contentWidths(specifications,available);const changed=sizes.filter(size=>Math.abs((api.getColumn(size.key)?.getActualWidth()||0)-size.newWidth)>1);
   if(changed.length)api.setColumnWidths(changed,true,'api');
  }finally{this.applying=false;}
 }
 ngOnDestroy(){this.destroyed=true;cancelAnimationFrame(this.frame);this.observer.disconnect();this.ready.unsubscribe();if(this.api&&!this.api.isDestroyed()){for(const name of ['firstDataRendered','rowDataUpdated','columnVisible','columnPinned','newColumnsLoaded','gridSizeChanged'] as const)this.api.removeEventListener(name,this.schedule);this.api.removeEventListener('columnResized',this.resized);}}
}
