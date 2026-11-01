import { jsPDF } from 'jspdf';
import { autoTable } from 'jspdf-autotable';
import { zipSync } from 'fflate';
import type { Row } from './data.service';
import { dateLabel, names } from './format';
import { Allocation, athleteSummary } from './refund-model';

import type { SignatureImage } from './portal.service';

interface Options { signature?: SignatureImage | null; stamp?: SignatureImage | null; issuer: string; date: string; notes: string; format: 'total' | 'athletes' | 'both'; allocation: Allocation }
const euros = (cents: number) => new Intl.NumberFormat('it-IT', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(cents / 100) + ' EUR';
const filename = (value: string) => value.normalize('NFKD').replace(/[\u0300-\u036f]/g, '').replace(/[^a-zA-Z0-9_-]+/g, '-').replace(/^-|-$/g, '').slice(0, 90) || 'atleta';
let fontBase64 = '';

export function configurePdfFont(value: string): void { fontBase64 = value; }
async function loadFont(): Promise<void> {
  if (fontBase64) return;
  const { config } = await import('./config');
  const url = new URL('fonts/NotoSans-Regular.ttf', config.assetsUrl || new URL('.', import.meta.url).href);
  const response = await fetch(url);
  if (!response.ok) throw new Error('Impossibile caricare il carattere per le ricevute PDF. Riprova.');
  const bytes = new Uint8Array(await response.arrayBuffer());
  let binary = '';
  for (let offset=0;offset<bytes.length;offset+=8192) binary += String.fromCharCode(...bytes.subarray(offset,offset+8192));
  configurePdfFont(btoa(binary));
}

function documentPdf(rows: string[][], total: number, options: Options, beneficiary?: string, hasDoubles = false): Uint8Array {
  const doc = new jsPDF({ unit: 'mm', format: 'a4', compress: true, putOnlyUsedFonts: true });
  if (!fontBase64) throw new Error('Carattere PDF non disponibile.');
  doc.addFileToVFS('NotoSans-Regular.ttf',fontBase64);
  doc.addFont('NotoSans-Regular.ttf','NotoSans','normal');
  doc.setProperties({ title: beneficiary ? 'Rimborso - ' + beneficiary : 'Rimborso cumulativo', author: options.issuer, creator: 'FITET Ricevute' });
  doc.setFont('NotoSans', 'normal'); doc.setFontSize(10);
  const issuerLines = doc.splitTextToSize(options.issuer, 174) as string[];
  const beneficiaryLines = beneficiary ? doc.splitTextToSize('Beneficiario: ' + beneficiary, 174) as string[] : ['Riepilogo cumulativo dei beneficiari'];
  const top = 42 + issuerLines.length * 5 + beneficiaryLines.length * 5 + 11;
  const header = () => {
    doc.setFont('NotoSans', 'normal'); doc.setTextColor(36,86,61); doc.setFontSize(10); doc.text('FITET RICEVUTE',18,18);
    doc.setTextColor(31,49,38); doc.setFontSize(17); doc.text('Rimborso quote di iscrizione',18,30);
    doc.setFont('NotoSans','normal'); doc.setFontSize(10); doc.text(issuerLines,18,42);
    doc.text(beneficiaryLines,18,42+issuerLines.length*5+5);
    doc.setFontSize(9); doc.setTextColor(93,109,98); doc.text('Data ricevuta: '+dateLabel(options.date),18,top-4);
  };
  autoTable(doc, {
    head: [beneficiary ? ['Data gara','Torneo / Gara','Quota'] : ['Data gara','Torneo / Gara','Atleta / Coppia','Quota']],
    body: rows, foot: [beneficiary ? ['', 'TOTALE RIMBORSO', euros(total)] : ['', '', 'TOTALE RIMBORSO', euros(total)]],
    margin: { top, right:18, bottom:22, left:18 }, startY: top, showFoot: 'lastPage',
    styles: { font:'NotoSans', fontStyle:'normal', fontSize:8, cellPadding:3, overflow:'linebreak', valign:'top', textColor:[31,49,38], lineColor:[225,233,224] },
    headStyles: { fillColor:[36,86,61], textColor:[255,255,255], fontStyle:'normal' },
    footStyles: { fillColor:[234,242,231], textColor:[36,86,61], fontStyle:'normal' },
    alternateRowStyles: { fillColor:[247,249,245] },
    columnStyles: beneficiary ? { 0:{cellWidth:23},1:{cellWidth:120},2:{cellWidth:31,halign:'right'} } : { 0:{cellWidth:23},1:{cellWidth:65},2:{cellWidth:57},3:{cellWidth:29,halign:'right'} },
    didDrawPage: header,
  });
  let y = (doc as jsPDF & { lastAutoTable: { finalY: number } }).lastAutoTable.finalY + 10;
  const note = [options.notes, beneficiary && hasDoubles && options.allocation === 'half' ? 'Doppi: quota ripartita tra i due atleti; eventuale centesimo dispari al primo atleta.' : ''].filter(Boolean).join('\n');
  doc.setFont('NotoSans','normal'); doc.setFontSize(9);
  const lines = note ? doc.splitTextToSize(note,174) as string[] : [];
  const signatureSpace = options.stamp ? 40 : 28;
  const required = lines.length * 4.5 + (lines.length ? 9 : 0) + signatureSpace;
  const nextPage = () => {
    doc.addPage(); header(); y = top + 8;
    doc.setFont('NotoSans','normal'); doc.setFontSize(9); doc.setTextColor(75,92,79);
  };
  if (required <= 272 - top - 8 && y + required > 272) nextPage();
  doc.setTextColor(75,92,79);
  for (const line of lines) {
    if (y + 4.5 > 272) nextPage();
    doc.text(line,18,y); y += 4.5;
  }
  if (lines.length) y += 9;
  if (y + signatureSpace > 272) nextPage();
  doc.text('Firma per ricevuta',18,y+4); doc.setDrawColor(155,171,158); doc.line(18,y+20,90,y+20);
  if (options.signature) {
    const signature=options.signature;
    const scale=Math.min(68/signature.width,13/signature.height);
    const width=signature.width*scale,height=signature.height*scale;
    doc.addImage(signature.data_url,signature.format,20+(68-width)/2,y+6+(13-height)/2,width,height);
  }
  if (options.stamp) {
    const stamp=options.stamp; const scale=Math.min(55/stamp.width,26/stamp.height);
    const width=stamp.width*scale,height=stamp.height*scale;
    doc.text('Timbro',112,y+4);
    doc.addImage(stamp.data_url,stamp.format,112+(55-width)/2,y+6,width,height);
  }
  const pages = doc.getNumberOfPages();
  for (let page=1;page<=pages;page++) {
    doc.setPage(page); doc.setFont('NotoSans','normal'); doc.setFontSize(8); doc.setTextColor(110,125,113);
    doc.text('Quote tornei FITeT - '+dateLabel(options.date),18,286);
    doc.text('Pagina '+page+' / '+pages,192,286,{align:'right'});
  }
  return new Uint8Array(doc.output('arraybuffer'));
}

export function buildRefundFiles(rows: Row[], options: Options): Record<string, Uint8Array> {
  if (!rows.length || rows.some(row => row.reimbursed || row.price_cents === null)) throw new Error('Selezione non valida per l’emissione.');
  if (!options.issuer || !/^\d{4}-\d{2}-\d{2}$/.test(options.date)) throw new Error('Indica società e data della ricevuta.');
  const files: Record<string, Uint8Array> = {};
  if (options.format === 'total' || options.format === 'both') {
    const lines = rows.map(row => [dateLabel(row.tournament_date),row.tournament_name+'\n'+row.race_name+'\n'+row.tournament_place+(row.refund_notes?'\nNote: '+row.refund_notes:''),names(row),euros(row.price_cents!)]);
    files['rimborso-cumulativo-'+options.date+'.pdf'] = documentPdf(lines,rows.reduce((sum,row)=>sum+row.price_cents!,0),options);
  }
  if (options.format === 'athletes' || options.format === 'both') {
    athleteSummary(rows,options.allocation).forEach((athlete,index) => {
      const lines = athlete.lines.map(line => [dateLabel(line.entry.tournament_date),line.entry.tournament_name+'\n'+line.entry.race_name+'\n'+line.entry.tournament_place+(line.entry.refund_notes?'\nNote: '+line.entry.refund_notes:''),euros(line.cents!)]);
      files[String(index+1).padStart(3,'0')+'-rimborso-'+filename(athlete.name)+'-'+options.date+'.pdf'] = documentPdf(lines,athlete.pending_cents!,options,athlete.name+(athlete.card !== '—' ? ' - tessera '+athlete.card : ''),athlete.lines.some(line=>line.entry.race_kind==='double'));
    });
  }
  return files;
}

export async function prepareRefundDownload(rows: Row[], options: Options): Promise<() => void> {
  await loadFont();
  const files = buildRefundFiles(rows,options);
  let content: Uint8Array; let name: string; let type: string;
  if (options.format === 'total') { name = Object.keys(files)[0]; content = files[name]; type = 'application/pdf'; }
  else { name = 'rimborsi-'+options.date+'.zip'; content = zipSync(files,{level:6}); type = 'application/zip'; }
  const blob = new Blob([content.slice().buffer as ArrayBuffer],{type});
  return () => {
  const url = URL.createObjectURL(blob); const link = document.createElement('a');
  link.href=url; link.download=name; document.body.append(link); link.click(); link.remove();
  setTimeout(()=>URL.revokeObjectURL(url),60000);
  };
}
export async function downloadRefunds(rows: Row[], options: Options): Promise<void> {
  (await prepareRefundDownload(rows, options))();
}
