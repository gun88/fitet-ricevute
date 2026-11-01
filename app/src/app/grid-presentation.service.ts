import { Injectable, computed, inject } from '@angular/core';
import { AuthService } from './auth.service';
import { gridTheme } from './grid';

@Injectable({providedIn:'root'})
export class GridPresentation {
 readonly auth=inject(AuthService);
 readonly autoHeight=computed(()=>this.auth.gridPreferences().auto_height);
 readonly layout=computed<'autoHeight'|'normal'>(()=>this.autoHeight()?'autoHeight':'normal');
 private readonly compactTheme=gridTheme.withParams({spacing:4});
 readonly theme=computed(()=>this.auth.gridPreferences().density==='compact'?this.compactTheme:gridTheme);
}
