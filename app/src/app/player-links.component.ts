import { Component } from '@angular/core';
import { RouterLink } from '@angular/router';
import { ICellRendererAngularComp } from 'ag-grid-angular';
import { ICellRendererParams } from 'ag-grid-community';
import type { Row } from './data.service';
import { Player, playerKey } from './players-model';

@Component({ selector:'fr-athlete-links', imports:[RouterLink], template:`<span class="athlete-links">@for (person of people; track person.key; let first = $first) { @if (!first) { <span aria-hidden="true">/</span> }<a [routerLink]="['/giocatori',person.key]" (click)="$event.stopPropagation()">{{ person.name }}</a> }</span>` })
export class AthleteLinksComponent implements ICellRendererAngularComp {
  people: {name:string;key:string}[] = [];
  agInit(params: ICellRendererParams<Row>): void {
    const row = params.data;
    this.people = row ? [{ name:row.athlete1_name,card:row.athlete1_card },{ name:row.athlete2_name,card:row.athlete2_card }].filter(person=>!!person.name).map(person=>({name:person.name!,key:playerKey(person.name!,person.card)})) : [];
  }
  refresh(params: ICellRendererParams<Row>): boolean { this.agInit(params); return true; }
}

@Component({ selector:'fr-player-link', imports:[RouterLink], template:`@if (player) { <a [routerLink]="['/giocatori',player.key]">{{ player.name }}</a> }` })
export class PlayerLinkComponent implements ICellRendererAngularComp {
  player?: Player;
  agInit(params: ICellRendererParams<Player>): void { this.player = params.data; }
  refresh(params: ICellRendererParams<Player>): boolean { this.agInit(params); return true; }
}
