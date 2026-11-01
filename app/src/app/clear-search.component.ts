import { Component, EventEmitter, Output } from '@angular/core';
@Component({selector:'fr-search-clear',template:`<button type="button" class="search-clear" aria-label="Azzera ricerca" title="Azzera ricerca" (click)="clear.emit()"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18"/></svg></button>`})
export class ClearSearchComponent { @Output() clear=new EventEmitter<void>(); }
