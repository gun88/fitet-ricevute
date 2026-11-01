# FITET Ricevute

Plugin WordPress con frontend Angular su `/fitet-ricevute/`. Versione **0.13.0**.

## Pagine del portale

- **Tornei**: una riga per torneo, filtro “Mostra solo tornei con iscrizioni” inizialmente attivo;
  azioni Dettaglio, Gare e Iscrizioni. Le ultime due aprono gli elenchi con il torneo già selezionato.
- **Gare**: elenco delle gare note, filtro con iscrizioni inizialmente attivo, torneo parent cliccabile,
  modifica della quota via API, origine prezzo e confronto con il totale PDF; Dettaglio e Iscrizioni per gara.
  Lasciare vuota una quota rimuove il prezzo manuale e ripristina quello automatico.
- **Iscrizioni**: link al torneo, alla gara e agli atleti; stato della quota e del torneo, aggiunta singola
  o delle righe selezionate alla bozza rimborso. Le quote già rimborsate non sono aggiungibili.
- **Rimborsi Emessi**: registro delle emissioni con data/ora server, importo, emittente e numero iscrizioni.
- **Bozza rimborso**: iscrizioni personali salvate nel database, eliminabili, note modificabili per riga
  e recap per atleta. Il badge nel menu conta tutti gli elementi, anche di altre stagioni.
  L’emissione apre il dettaglio del rimborso; qui si sceglie cosa stampare.

Le pagine dettaglio di torneo, gara, rimborso e giocatore riusano le stesse tabelle e azioni degli elenchi.
Il dettaglio giocatore mostra iscrizioni e rimborsi; è raggiungibile dai nomi degli atleti.
La directory giocatori resta raggiungibile su `#/giocatori`. Il vecchio URL `#/prezzi` reindirizza a Gare; `#/carrello` apre Bozza rimborso.

La select della stagione è unica per il frontend, nell’intestazione desktop o nel menu mobile,
e salvata per sito/account nel browser. Non ha un’etichetta visibile, ma mantiene il nome accessibile.
La voce **- seleziona stagione -** svuota le viste e disabilita il pulsante di sincronizzazione;
anche questa scelta viene conservata. Le etichette delle stagioni sono compatte, per esempio 2026/27.
Le stagioni vanno dal 1° luglio al 30 giugno; “Ultima disponibile” sceglie la più recente nei dati.
La stagione filtra elenchi, dettagli e bozza rimborso. Le emissioni conservano sempre il totale originale:
nel dettaglio l'elenco segue il filtro globale, mentre il PDF riproduce l'emissione completa.

AG Grid Community: testi selezionabili, filtri, ordinamento, ricerca e preferenze per tabella/utente/browser.
100 righe iniziali, con precedenza alle preferenze già salvate. Layout responsive e scorrimento interno.
La barra di sincronizzazione e la data dell'ultimo completamento riuscito restano nella pagina Tornei.

## Intestazione e spazi delle pagine

Su desktop, logo, nome e navigazione sono a sinistra; stagione, nome e avatar dell’utente a destra,
in un’unica barra. Il menu del nome/avatar apre **Profilo utente** e **Log out**.
La pagina `#/utente` mostra nome, username di accesso e pulsante di uscita.

Su mobile e tablet la barra mostra logo/nome e hamburger. Il menu scorre da sinistra e lascia visibile
una parte oscurata della pagina. Contiene navigazione, stagione e due azioni utente affiancate.
Si chiude dopo la scelta di una pagina o stagione, premendo hamburger, Escape o l’area oscurata.
Lo sfondo resta inattivo e la navigazione da tastiera resta nel menu finché è aperto.

Le pagine condividono margini e intestazioni compatti. In Tornei e Gare il filtro con iscrizioni è
accanto alla ricerca su desktop e sopra di essa su mobile. L’ultima sincronizzazione rimane vicina
al pulsante; avanzamento ed errori della sincronizzazione compaiono nella pagina Tornei.
Su mobile gli elenchi sono card Angular; AG Grid rimane attivo su desktop.

## Bozza rimborso, emissione e stampa

La bozza è distinta per utente del portale e società: resiste a logout, cambio browser e riavvio.
Contiene al massimo 1000 quote. Il prezzo è riletto dal server e non congelato dall’aggiunta alla bozza.
Una quota rimossa dalla fonte resta visibile come “Da verificare” e può essere eliminata, ma non emessa.
La colonna **Note riga** è modificabile e salvata via API (massimo 1000 caratteri per iscrizione).
**Note generali** è un campo multilinea, massimo 2000 caratteri.
La società emittente proviene sempre dalla configurazione sul server e non è modificabile nella bozza.
La ripartizione dei doppi rimane selezionabile: metà per atleta, tutta al primo oppure tutta al secondo.

**Emetti rimborsi** registra sul server, in transazione, iscrizioni, importi, note e impostazioni,
segna le quote rimborsate e le rimuove dalla propria bozza. Poi apre il dettaglio del rimborso.
La richiesta è protetta da sessione, CSRF, controllo società, confronto dei prezzi e chiave idempotente;
richieste concorrenti o ripetute non devono produrre due emissioni per lo stesso invio.
Con una stagione specifica vengono emesse solo le quote visibili; le altre restano nella bozza.

Nel dettaglio sono disponibili **Stampa rimborso (PDF)**, **Esporta Excel** e **Scarica PDF + Excel (ZIP)**.
Il PDF è unico: riepilogo di quanto consegnare, dettaglio quote, indice e ricevute originali allegate
una sola volta ciascuna. Non vengono prodotti PDF individuali. L’Excel contiene Quanto consegnare,
Dettaglio quote e Ricevute originali, con importi numerici e totali calcolati dal dettaglio.
I totali generali sono esterni alle tabelle e alle colonne degli importi, così anche sommare un’intera
colonna non duplica gli importi. Le righe di dettaglio non contengono subtotali.

Gli export sono prodotti nel browser. Prezzi, note, firma, timbro e riferimenti delle ricevute delle nuove
emissioni sono snapshot: modifiche successive non cambiano lo storico. Gli originali vengono letti
tramite API autenticate dall’archivio locale e verificati con SHA-256 prima di essere uniti.
Se un originale manca o è illeggibile, il PDF completo non viene prodotto; l’Excel resta disponibile
con il documento segnalato come mancante e senza numeri di pagina. Le emissioni precedenti prive di
riferimenti vengono associate ai documenti correnti del torneo e mostrano una nota che richiede
la verifica della corrispondenza. Nessun PDF viene riscaricato da FITeT durante l’esportazione.

Le marcature “rimborsata” delle versioni precedenti sono conservate, senza date di emissione inventate.
Il registro parte dalle emissioni effettuate con la 0.7.0. Le API precedenti di marcatura operativa
restano compatibili, ma non creano documenti nel registro.

## Sincronizzazione FITeT

Il backend effettua un solo login per leggere:

- `/login/iscrizioni/testa_iscrizioni_reg.asp` (catalogo territoriale)
- `/login/iscrizioni/testa_iscrizioni.asp` (catalogo nazionale)
- `/login/iscrizioni/lista_partecipazione_tornei.asp` (ricevute della società)

I tornei sono salvati dai cataloghi; solo i PDF effettivamente elencati nel riepilogo partecipazioni vengono
scaricati, senza autenticazione/cookie e senza aprire un dettaglio extranet per ogni torneo. Un PDF ricevuta per avanzamento API.
Segue una fase pubblica per le locandine, con una sola pagina HTML per avanzamento.
File in `wp-content/uploads/fitet-ricevute/ricevute-pdf/`. Angular deve restare aperto per proseguire gli avanzamenti.
Prezzi manuali e stato dei rimborsi sono conservati. Le quote dei singolari omogenei si inferiscono dal totale;
le ricevute miste o con divisione non esatta richiedono prezzi manuali. I doppi sono per coppia.

I cataloghi danno i tornei, non l'elenco completo delle gare offerte: vengono mostrate solo le gare note
dalle ricevute, senza inventare gare per i tornei con zero iscrizioni. Il formato di un PDF reale con doppi
resta da verificare; le iscrizioni a squadre non vuote richiedono un parser dedicato.
L’importazione Excel e l’esportazione generale dei dati restano da implementare; l’export Excel dei rimborsi è disponibile.

## Installazione e configurazione

WordPress 6.5+, PHP 8.1+ (DOM, mbstring, iconv, zlib, OpenSSL), MySQL/MariaDB supportato da WordPress.
Installazione singolo sito; HTTPS in produzione. Caricare lo ZIP installabile della cartella `release/`.
Le tabelle e la colonna note della bozza vengono aggiornate automaticamente, conservando i dati esistenti.

In **wp-admin → FITET Ricevute → Configurazione** impostare società e codice, account del portale e credenziali
extranet. Gli account del portale sono separati dagli utenti WordPress. Le credenziali FITeT sono cifrate
sul backend e non esposte nel frontend. Le preferenze del browser non contengono password o token.
Un unico pulsante **Salva configurazione** salva impostazioni, credenziali, firma e timbro.
Se un’immagine non è valida, nessuna parte della configurazione viene salvata.
I campi file vuoti conservano le immagini; la password vuota conserva quella esistente.
L’interfaccia wp-admin è responsive. La disinstallazione conserva dati e registro, rimuovendo sessioni e segreti.

## Sorgenti e build

`app/src/app/portal.service.ts`: cart, registro e stagione; `entity-grid.component.ts`: tabelle e azioni comuni;
`portal-pages.component.ts`: elenchi/dettagli; `cart.component.ts`: revisione/emissione.
`includes/class-refund-workflow.php`: bozza e registro; `docs/schema.sql`: DDL.
I vecchi componenti di pagina restano nei sorgenti come riferimento, ma non sono collegati alle nuove rotte.

```sh
cd app
npm ci
npm run build
npm run package
```

La build scrive in `assets/app/`; gli ZIP installabile e sorgenti sono in `release/`.
L'API è sul namespace `fitet-ricevute/v1`; le richieste operative usano cookie di sessione e token CSRF.
Gli endpoint operativi sono `GET/POST/DELETE /carrello`, `PATCH /carrello/{entry_id}`,
`GET/POST /emissioni` e `GET /emissioni/{id}`. Il nome tecnico `/carrello` resta compatibile.

## Test isolati

`python tests/prepare-runtime.py C:/percorso/core-wordpress` prepara solo `tests/.runtime/wordpress`
con il driver ufficiale SQLite per test. Non copia la configurazione o i dati del sito principale.
`php tests/data.php`, `php tests/workflow.php` e `php tests/refunds.php` verificano backend e transazioni.
Per l’interfaccia corrente: generare le immagini con `python tests/signature-fixtures.py`, avviare
il server con `tests/router.php`, eseguire `php tests/admin-ui-fixture.php`, poi `node tests/admin-ui.cjs`.
Al termine `php tests/admin-ui-fixture.php clean` ripristina i dati fittizi.
`php tests/admin-backup.php` verifica salvataggio unico, note e backup/ripristino completo.
Le suite browser `portal.cjs` e `signature.cjs` descrivono i flussi precedenti alla 0.8.1;
la suite corrente per configurazione, backup, bozza e stampa è `admin-ui.cjs`.
`node tests/layout.cjs` verifica intestazione, stagioni, profilo e menu responsive; usa
`portal-fixture.php` per preparare/ripristinare le stagioni fittizie.
`FR_PLAYWRIGHT_MODULE` e `FR_BROWSER_PATH` possono indicare Playwright e Chrome locali.
Nessuna credenziale reale viene salvata nei sorgenti o negli archivi distribuiti.

## Firma e timbro sulle ricevute

In **wp-admin → FITET Ricevute → Configurazione** caricare firma e timbro PNG o JPG,
massimo 1 MB e 4096 × 4096 pixel ciascuno. PNG conserva lo sfondo trasparente.
Anteprima, sostituzione e rimozione usano lo stesso pulsante **Salva configurazione**.
Le immagini vengono adattate senza deformazioni nelle ricevute cumulative e individuali.
Senza firma rimane lo spazio per quella manuale; senza timbro non viene disegnata alcuna immagine.

Le immagini sono opzioni private senza autoload, non file nella libreria media pubblica.
Le API `/firma` e `/timbro` richiedono autenticazione; i form admin richiedono permessi e nonce.
Ogni emissione conserva le immagini canoniche presenti sul server al momento dell’emissione:
sostituzioni o rimozioni valgono per le emissioni future. Le emissioni precedenti senza immagini
rimangono senza immagini. Il registro trasferisce i riferimenti; il dettaglio legge lo snapshot completo.

## Backup completo

**wp-admin → FITET Ricevute → Importa / esporta dati** esporta un archivio `.json.gz` con tornei/gare,
iscrizioni, prezzi, stati di rimborso, bozze con note, emissioni, impostazioni, firma, timbro,
date di ultima sincronizzazione e PDF originali archiviati. I PDF mancanti vengono segnalati.
Le identità degli account del portale sono incluse per attribuire bozze ed emissioni.
Password, credenziali extranet e sessioni sono escluse. Le preferenze del browser non sono nel backup.

L’importazione verifica il file e mostra un’anteprima. Solo la conferma **Ripristina backup** sostituisce
i dati del plugin e le relative impostazioni. Non modifica altri dati WordPress e conserva password
e credenziali extranet del sito di destinazione. Gli account si associano per username; quelli mancanti
vengono creati disabilitati con password casuale, da impostare prima dell’abilitazione.
Il ripristino termina le sessioni del portale e invalida le sincronizzazioni già avviate:
non utilizzare il portale mentre è in corso.

Limiti attuali: archivio compresso 16 MB (o limite upload PHP inferiore), contenuto non compresso 64 MB,
100.000 righe totali, PDF originali 32 MB complessivi e 3 MB per file.
Il backup non è un file Excel ed è distinto dall’esportazione Excel dei rimborsi. L’importazione Excel rimane da implementare.


## Card mobili e infinite scroll (0.10.0)

Fino a 1100 px gli elenchi, le tabelle nei dettagli, la bozza, il recap per atleta e la directory
Giocatori usano card al posto di AG Grid. Sul desktop rimangono le griglie con filtri per colonna,
ordinamento e preferenze salvate. Le card espongono titolo, data, importo/stato, dettagli espandibili,
link e azioni, modifica delle quote e note della bozza tramite le API esistenti.
La ricerca generale copre tutti i dati, anche le card non ancora visualizzate. Stagione e filtri
con iscrizioni restano condivisi; i filtri per colonna desktop non limitano le card.

L’infinite scroll aggiunge 20 card alla volta avvicinandosi al fondo e mostra il conteggio risultati.
Il rendering è progressivo sui dati già caricati dalle API; non vengono fatte nuove richieste per
ogni scorrimento. Ordine mobile: data più recente prima; giocatori e recap in ordine alfabetico.
La ricerca o il cambio di dataset ripartono dal primo batch.

`tests/mobile-cards.cjs` verifica infinite scroll su 65 tornei simulati, ricerca completa,
preferenze desktop e operazioni mobili. Setup/ripristino con `admin-ui-fixture.php`.
Le vecchie suite che presuppongono una griglia mobile sono storiche.


## Conteggio rimborsi e ricerca mobile (0.10.1)

Tornei e Gare mostrano un numero nella colonna Rimborsi: conteggio delle iscrizioni segnate
come rimborsate della risorsa, indipendente dalla ricerca della tabella. Non conta PDF o emissioni
cumulative e non indica un obbligo di rimborso. Per torneo, una partecipazione a ciascuna gara
è una quota distinta; per gara si contano solo le proprie iscrizioni. I doppi conservano la loro unità
originale di iscrizione, senza cambiare la ripartizione delle ricevute.
Solo i dettagli mostrano a fianco del conteggio nessun/parziale/totale rimborso e la percentuale
sul totale delle iscrizioni, con al massimo un decimale. Zero iscrizioni restituisce 0 e 0%.

I titoli delle card linkano al dettaglio di torneo, gara, rimborso o giocatore; sulle iscrizioni,
i nomi degli atleti sono collegamenti ai rispettivi giocatori. Anche i recap hanno titoli cliccabili.
Ricerca mobile e pulsante di azzeramento con icona sono affiancati, con nome accessibile e
un’area di tocco da 44 px. L’azzeramento mobile riguarda la ricerca generale e conserva i filtri desktop.


## Locandine esterne (0.11.0)

La tabella Tornei, le card e il dettaglio mostrano **Visualizza locandina**, in una nuova scheda
con `noopener noreferrer`. Le locandine non vengono scaricate, archiviate, incorporate o controllate
con richieste HEAD. Il browser apre direttamente la risorsa FITeT solo dopo il clic; se il documento
non esiste più, viene mostrata la risposta del sito esterno. I tornei nazionali (comitato 30) non
prevedono questo collegamento. Per un riferimento sconosciuto compare “Non disponibile”.

Alla prossima sincronizzazione il plugin cerca i riferimenti negli HTML pubblici:
- stagione corrente: `/risultati/tornei/elenco_gare.php?IDT=...&COM=...`;
- archivio: `/risultati/archivio/elenco_gare_tornei.asp?ANNO=...&COM=...&IDT=...`.

Vengono salvati `poster_ref` (comitato_torneo_idIncrementale) e `poster_year` (ANNO osservato),
conservati nelle risincronizzazioni e nei backup. La parte incrementale non viene calcolata né cercata
per tentativi. Il percorso esterno è `tabelloni/riferimento.pdf` per la stagione corrente e
`tabelloni/ANNO/riferimento.pdf` per le precedenti. Il cambio stagione è quello già usato nell’app,
al 1° luglio; l’identificativo federale ANNO viene osservato nei dati, mai ricavato dal calendario.

I tornei senza ricevuta sono inclusi nella ricerca. Se la pagina non riporta ANNO, viene usato solo
un identificativo corrente univoco già osservato nei dati della società; in assenza di questa informazione
si conserva comunque il riferimento e lo si rende utilizzabile nella stagione corrente. Per costruire
il percorso storico occorre conoscere ANNO. Gli storici senza riferimento vengono cercati nell’archivio
quando ANNO è disponibile. Un errore HTTP o un link mancante non cancella un riferimento già salvato.

La ricerca locandine è progressiva, dopo le ricevute, senza login/cookie e senza seguire redirect.
L’avanzamento distingue la fase locandine da quella delle ricevute; Angular deve restare aperto.
Non serve un’ulteriore autenticazione FITeT per questa fase. I backup precedenti restano importabili,
con i nuovi campi inizialmente vuoti.


## Esportazione rimborsi 0.12.0

- Una riga di dettaglio per beneficiario e quota attribuita; per i doppi si conserva metà/primo/secondo,
  compreso il centesimo dispari al primo. Atleta/Coppia conserva l’iscrizione originale.
- Riferimenti R01, R02… univoci per originale, note per quota e note generali. Il PDF riporta un indice
  con le pagine fisiche degli allegati; l’Excel riusa gli stessi riferimenti e intervalli.
- Foglio Quanto consegnare: una riga per beneficiario e formule SUMIFS dal dettaglio. Dettaglio quote:
  tabella nativa ordinabile/filtrabile, date e importi numerici, senza subtotali. Ricevute originali:
  indice, stato, pagine del PDF completo, hash e URL origine.
- Le tabelle Excel hanno esplicitamente disattivate le righe totali. Le formule includono risultati
  memorizzati e ricalcolo all’apertura; la modifica di una quota aggiorna il riepilogo. I nuovi
  beneficiari aggiunti manualmente al dettaglio richiedono una corrispondente riga nel riepilogo.
- Formule e metadati Excel vengono verificati anche con un motore indipendente; merge PDF senza
  rasterizzare gli originali. Firma e timbro sono applicati al recap e non alle ricevute originali.

API nuove: `GET /emissioni/{id}/ricevute` (manifesto senza nomi fisici dell’archivio) e
`GET /emissioni/{id}/ricevute/{sha256}` (PDF binario autenticato e non memorizzabile in cache).
`options_json.original_receipts` conserva file/hash e metadati canonici sul server; gli snapshot
iscrizione conservano `receipt_id`. Il backup completo include i riferimenti e i file archiviati.

Suite: `tests/refund-documents.php`, `tests/refund-export.cjs`, `tests/refund-export-ui.cjs`.
Setup/ripristino browser con `tests/export-ui-fixture.php` e relativo comando `clean`.

## Tabelle e profilo 0.12.1

Nel profilo ogni utente sceglie altezza automatica (on/off) e spaziatura Standard/Compatta per tutte le griglie desktop. Le preferenze sono salvate sul server per l’account del portale; non cambiano le card mobile. I filtri di colonna si aprono dalle icone nelle intestazioni, senza riga aggiuntiva; le colonne Azioni non sono filtrabili. Eliminati i link ridondanti nelle intestazioni della bozza e dei dettagli torneo/gara, e il filtro delle sole gare con iscrizioni.

## Reset di sviluppo 0.12.2

In Configurazione, solo per amministratori e quando sia l’URL configurato del sito sia l’host richiesto sono localhost/127.0.0.1/::1, è disponibile “Azzera dati di sviluppo”. Richiede conferma esplicita e nonce WordPress. Cancella le sei tabelle dei dati (inclusi prezzi manuali, bozze ed emissioni), PDF nella sola cartella ricevute-pdf e stato/data di sincronizzazione. Conserva configurazioni, credenziali, firma/timbro, account e preferenze personali. Revoca le sessioni del portale: è necessario un nuovo accesso. Il reset è bloccato durante un ripristino o un passo attivo della sincronizzazione. Eventuali errori di rimozione PDF vengono segnalati; nessuna cancellazione ricorsiva delle cartelle uploads.

## Messaggi e bozza 0.12.3

Le conferme delle operazioni scompaiono dopo cinque secondi e al cambio pagina. Il timer viene rinnovato quando cambia il messaggio. Nella bozza, Società è un input in sola lettura con etichetta, allineato agli altri campi.

## Rimborsi modificabili, rettifiche e tornei manuali 0.13.0

- Modifica dal dettaglio rimborso: stesso modulo della bozza, dati separati dalla bozza ordinaria. Salvataggio atomico, controllo revisione contro modifiche concorrenti, storico dei precedenti snapshot in options_json. Le quote mantenute conservano il prezzo congelato; quelle rimosse tornano disponibili. Le sole quote selezionate vengono tolte dalla bozza dell’operatore, conservando le altre.
- Ogni iscrizione può essere rimborsata oppure detratta perché già pagata dalla società. Contributi/trattenute hanno beneficiario, causale e importo firmato. Il saldo di ogni beneficiario deve essere non negativo. La scorciatoia “Imposta contributo complessivo” aggiunge il contributo e imposta le relative iscrizioni come detrazioni (nei doppi il trattamento è per coppia).
- Bonifico/contanti per beneficiario, con predefinito personale dell’operatore (bonifico inizialmente). La scelta nel riepilogo per atleta vale per tutte le sue iscrizioni, anche nei doppi. PDF ed Excel mostrano la modalità di ciascun atleta e i subtotali distinti non nulli. I rimborsi precedenti conservano la modalità globale originaria come fallback.
- PDF: Riepilogo rimborsi con numero gare; Dettaglio quote in verticale con torneo/gara/località, note e coppia; se presenti, contributi/trattenute in una sezione dedicata. Unico documento con originali. Excel: Riepilogo rimborsi, Dettaglio quote, Rettifiche, Ricevute originali. Totali separati dalle colonne analitiche.
- Aggiungi torneo: avviso contro duplicati extranet, dati manuali, gare/prezzi, atleti con suggerimenti nome/tessera. Ricevuta obbligatoria e locandina facoltativa (PDF/PNG/JPG); le immagini sono convertite dal browser in PDF. Limiti separati nelle config admin, default 200 KB e massimo 3072 KB, verificati anche sul server sul PDF finale. Documenti privati nella cartella ricevute-pdf; locandina servita tramite API autenticata. Provenienza manuale conservata in source_json, distinta dai tornei sincronizzati. Non è richiesta una modifica DDL.
- Tipo: Nazionale o Territoriale (nome comitato). Link di navigazione sui conteggi numerici, preservando sorting e filtro. Atleta e tessera uniti. Azioni +/− e modifica nota nella colonna checkbox. Stampa con icona. Progress sincronizzazione chiudibile al termine. Larghezze automatiche condivise su tutte le griglie: misura dei dati caricati (anche non renderizzati), limite iniziale 420px per i contenuti lunghi, espansione proporzionale per riempire lo spazio disponibile anche oltre tale limite. Checkbox e comandi vengono misurati separatamente e non espansi. Nessun limite massimo aggiunto alle colonne normali; le larghezze scelte manualmente vengono memorizzate per utente/tabella e restano prioritarie.

La build del sito non viene aggiornata automaticamente da queste modifiche ai sorgenti. Per aggiornarla: `cd app` e `npm run build`.

In modifica rimborso, “Aggiungi iscrizione” è un accordion inizialmente chiuso. Contiene la griglia delle quote non rimborsate della stagione selezionata, escluse quelle già nel rimborso in modifica. Filtri e ordinamento sono personali; il + (o l’aggiunta delle selezionate) aggiorna solo il rimborso locale fino al salvataggio. Su mobile usa le card esistenti.

### Importazione dei dati di un torneo manuale

In Aggiungi torneo: Esempio JSON, Importa JSON, Esempio Excel, Importa Excel. L’importazione avviene nel browser e compila soltanto il modulo; non crea dati sul server. Modelli statici in app/src/import-templates, inclusi nella build Angular. I modelli contengono un singolo e un doppio con iscrizioni d’esempio.

JSON: format=fitet-ricevute-torneo, version=1, tournament (name, date YYYY-MM-DD, place, committee, committee_name, total_euros), races (id univoco, name, kind single/double, euros, entries). Ogni entry contiene name1/card1 e, per un doppio, name2/card2. Tessere come stringhe per conservare gli zeri iniziali. Gli allegati e l’approvazione del disclaimer non vengono importati.

Excel: Istruzioni, Torneo (una riga), Gare, Iscrizioni. Non cambiare le intestazioni. ID gara collega ogni iscrizione alla propria gara; tipo Singolo/Doppio, importi numerici in euro e tessere di testo. Sono ammesse date Excel oppure ISO e DD/MM/YYYY. Non sono ammesse formule. Limiti: JSON 1 MB, XLSX 2 MB e 20 MB non compressi, 100 gare, 1000 iscrizioni.

Lettura e validazione precedono qualsiasi modifica al modulo. Errori lasciano intatti campi e allegati. Un’importazione riuscita sostituisce i dati, azzera la conferma del disclaimer e rimuove gli eventuali allegati selezionati, da caricare nuovamente a mano. La ricevuta resta obbligatoria, la locandina facoltativa.

Nel form Aggiungi torneo il comitato si seleziona dalla mappa unica includes/committees.php (21 territoriali più Nazionale 30), esposta dalla configurazione pubblica. Nome e codice vengono mantenuti coerenti automaticamente; il backend ricava il nome dalla mappa e rifiuta codici sconosciuti. Import JSON/Excel seleziona il comitato tramite ID; eventuali codici non riconosciuti richiedono una scelta valida prima del salvataggio.

### Icone Lucide

Le icone dell’interfaccia e i comandi AG Grid usano il pacchetto ufficiale @lucide/angular, fissato alla versione 1.66.0. Registro limitato alle icone utilizzate in app/src/app/icons.ts e componente condiviso fr-icon. Include aggiunta/rimozione, modifica, stampa, menu, logout/profilo, chiusura, disclosure e filtri/ordinamento/paginazione AG Grid. Nessuna richiesta a CDN o font esterni. Icone decorative nascoste agli screen reader; nomi accessibili sui comandi conservati. Licenza inclusa in docs/LICENSE-LUCIDE.txt. Gli avatar con iniziali e il marchio dell’app restano elementi dell’identità visiva.
