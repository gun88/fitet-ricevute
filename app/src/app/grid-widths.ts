export interface WidthInput {id:string;natural:number;min:number;max?:number;fixed?:boolean;pinned?:boolean;manual?:number}
/** The content cap is only an initial sizing rule, never a permanent column maximum. */
export function contentWidths(columns:WidthInput[],available:number,cap=420):{key:string;newWidth:number}[]{
 const widths=columns.map(c=>Math.round(Math.max(c.min,Math.min(c.max??Infinity,c.manual??(c.fixed?c.natural:Math.min(cap,c.natural))))));
 let extra=Math.floor(available-widths.reduce((n,w)=>n+w,0));let eligible=columns.map((c,i)=>i).filter(i=>!columns[i].fixed&&!columns[i].pinned&&columns[i].manual===undefined);
 while(extra>0&&eligible.length){const weight=eligible.reduce((n,i)=>n+widths[i],0);let used=0;const next:number[]=[];for(let n=0;n<eligible.length;n++){const i=eligible[n],room=Math.max(0,(columns[i].max??Infinity)-widths[i]),share=n===eligible.length-1?extra-used:Math.floor(extra*widths[i]/weight),add=Math.min(room,share);widths[i]+=add;used+=add;if(widths[i]<(columns[i].max??Infinity))next.push(i);}if(!used)break;extra-=used;eligible=next;}
 return columns.map((c,i)=>({key:c.id,newWidth:widths[i]}));
}
