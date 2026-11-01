import { Component, Input } from '@angular/core';
import { LucideDynamicIcon } from '@lucide/angular';
import { appIcons, AppIconName } from './icons';
@Component({selector:'fr-icon',imports:[LucideDynamicIcon],host:{'aria-hidden':'true'},styles:':host{display:inline-flex;align-items:center;justify-content:center;flex-shrink:0;vertical-align:middle;line-height:0}svg{display:block;fill:none!important}',template:'<svg [lucideIcon]="icons[name]" [size]="size" [strokeWidth]="2" aria-hidden="true"></svg>'})
export class IconComponent { @Input({required:true}) name!:AppIconName;@Input() size=20;readonly icons=appIcons;}
