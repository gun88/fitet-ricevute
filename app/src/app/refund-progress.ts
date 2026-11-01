export function refundProgress(rows:readonly {reimbursed:boolean}[]) {
 const total=rows.length,count=rows.filter(row=>row.reimbursed).length;
 const percent=count===0?0:count===total?100:Math.min(99.9,Math.round(count/total*1000)/10);
 const status=count===0?'nessun rimborso emesso':count===total?'totalmente rimborsato':'parzialmente rimborsato';
 return {count,total,percent,label:status+' - '+new Intl.NumberFormat('it-IT',{maximumFractionDigits:1}).format(percent)+'%'};
}
