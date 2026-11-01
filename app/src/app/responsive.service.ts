import { Injectable, signal } from '@angular/core';
import type { GridApi } from 'ag-grid-community';
import { TableView } from './table-preferences';
@Injectable({providedIn:'root'})
export class ResponsiveService {
 private readonly query=window.matchMedia('(max-width: 1100px)');private readonly listeners=new Set<()=>void>();
 readonly mobile=signal(this.query.matches);
 constructor(){this.query.addEventListener('change',event=>{this.listeners.forEach(listener=>listener());this.mobile.set(event.matches);});}
 preserve(getApi:()=>GridApi|undefined,view:TableView){const listener=()=>{const api=getApi();if(api&&!api.isDestroyed())view.capture(api.getState());view.prepare();};this.listeners.add(listener);return ()=>this.listeners.delete(listener);}
}
