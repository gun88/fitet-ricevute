import { Component, Input, ContentChild, TemplateRef, ViewChild, ElementRef, AfterViewInit, OnChanges, OnDestroy, signal } from '@angular/core';
import { NgTemplateOutlet } from '@angular/common';
@Component({selector:'fr-mobile-list',imports:[NgTemplateOutlet],template:`
 <div class="mobile-card-list" [attr.aria-label]="label">
 @for(row of visible();track row.id??row.key??$index){<ng-container [ngTemplateOutlet]="item" [ngTemplateOutletContext]="{$implicit:row}" />}
 @if(!filtered.length){<p class="empty-state">Nessun risultato da mostrare.</p>}
 <div #sentinel class="infinite-sentinel" role="status">{{visible().length}} di {{filtered.length}} elementi @if(visible().length<filtered.length){<span> · Scorri per continuare</span>}</div>
 </div>
`})
export class MobileListComponent implements AfterViewInit,OnChanges,OnDestroy {
 @Input() rows:any[]=[];@Input() search='';@Input() label='Elenco';@Input() text:(row:any)=>string=row=>Object.values(row).join(' ');@Input() sort:(a:any,b:any)=>number=()=>0;
 @ContentChild(TemplateRef) item!:TemplateRef<any>;@ViewChild('sentinel') sentinel!:ElementRef<HTMLElement>;
 readonly visible=signal<any[]>([]);filtered:any[]=[];private limit=20;private observer?:IntersectionObserver;private timer?:ReturnType<typeof setTimeout>;
 private normal(value:string){return value.normalize('NFD').replace(/[\u0300-\u036f]/g,'').toLocaleLowerCase('it-IT');}
 ngOnChanges(){const terms=this.normal(this.search).split(/\s+/).filter(Boolean);this.filtered=this.rows.filter(row=>{const text=this.normal(this.text(row));return terms.every(term=>text.includes(term));}).slice().sort(this.sort);this.limit=20;this.update();}
 private update(){this.visible.set(this.filtered.slice(0,this.limit));clearTimeout(this.timer);this.timer=setTimeout(()=>{if(this.observer&&this.sentinel){this.observer.unobserve(this.sentinel.nativeElement);this.observer.observe(this.sentinel.nativeElement);}});}
 ngAfterViewInit(){this.observer=new IntersectionObserver(entries=>{if(entries.some(entry=>entry.isIntersecting)&&this.limit<this.filtered.length){this.limit+=20;this.update();}},{rootMargin:'200px'});this.observer.observe(this.sentinel.nativeElement);}
 ngOnDestroy(){clearTimeout(this.timer);this.observer?.disconnect();}
}
