import { IconComponent } from './icon.component';
import { Component, EventEmitter, Output } from '@angular/core';
@Component({selector:'fr-search-clear',imports:[IconComponent],template:`<button type="button" class="search-clear" aria-label="Azzera ricerca" title="Azzera ricerca" (click)="clear.emit()"><fr-icon name="close" /></button>`})
export class ClearSearchComponent { @Output() clear=new EventEmitter<void>(); }
