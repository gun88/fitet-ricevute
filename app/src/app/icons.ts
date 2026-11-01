import { LucidePlus, LucideMinus, LucideSquarePen, LucidePrinter, LucideX, LucideEllipsisVertical, LucideMenu, LucideLogOut, LucideUserRound, LucideChevronDown, LucideChevronRight, LucideChevronLeft, LucideChevronsLeft, LucideChevronsRight, LucideArrowUp, LucideArrowDown, LucideArrowUpDown, LucideFilter } from '@lucide/angular';
import type { LucideIconNode } from '@lucide/angular';
export const appIcons={plus:LucidePlus.icon,minus:LucideMinus.icon,edit:LucideSquarePen.icon,printer:LucidePrinter.icon,close:LucideX.icon,more:LucideEllipsisVertical.icon,menu:LucideMenu.icon,logout:LucideLogOut.icon,user:LucideUserRound.icon,'chevron-down':LucideChevronDown.icon,'chevron-right':LucideChevronRight.icon,'chevron-left':LucideChevronLeft.icon,first:LucideChevronsLeft.icon,last:LucideChevronsRight.icon,'arrow-up':LucideArrowUp.icon,'arrow-down':LucideArrowDown.icon,sort:LucideArrowUpDown.icon,filter:LucideFilter.icon};
export type AppIconName=keyof typeof appIcons;
/** Trusted package data only: AG Grid requires an SVG string rather than an Angular component. */
export function iconSvg(name:AppIconName,size=16):string {
 const escape=(value:unknown)=>String(value).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;');
 const nodes=(items:LucideIconNode[]):string=>items.map(([tag,attrs,children])=>'<'+tag+' '+Object.entries(attrs).filter(([key])=>key!=='key').map(([key,value])=>key+'="'+escape(value)+'"').join(' ')+'>'+nodes(children||[])+'</'+tag+'>').join('');
 return '<svg xmlns="http://www.w3.org/2000/svg" class="lucide fr-grid-icon" width="'+size+'" height="'+size+'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'+nodes(appIcons[name].node)+'</svg>';
}
