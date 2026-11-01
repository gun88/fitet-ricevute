import { Component } from '@angular/core';
import { IconComponent } from './icon.component';
@Component({selector:'fr-editable-header',imports:[IconComponent],template:'<span class="editable-header">{{params.displayName}} <fr-icon name="edit" [size]="14" /></span>'})
export class EditableHeaderComponent {params:any;agInit(params:any){this.params=params;}refresh(params:any){this.params=params;return true;}}
