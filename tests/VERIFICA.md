# Verifica versione 0.1.0

## Aggiornamento 0.6.1 — 31 ottobre 2026

- Lettura reale: **80 tornei, 58 territoriali e 22 nazionali; 19 ricevute nel riepilogo partecipazioni**.
  Una sessione, un POST di login, sei richieste HTTP complessive e zero dettagli/frame aperti.
  Nessuna importazione dei dati reali durante questa verifica di lettura.
- Cataloghi persistiti direttamente e separatamente dai link PDF: nessun ANNO ipotizzato e nessun
  download per i tornei senza ricevuta. I PDF del job sono pubblici e richiesti con cookie vuoti.
- **12 controlli mirati cataloghi/ricevute**: creazione dei tornei territoriali e nazionali senza PDF,
  conteggio job limitato ai PDF elencati, un solo login, assenza richieste ai dettagli, download esclusivi,
  idempotenza, collegamento per comitato/ID/data e conservazione prezzi dopo collegamento.
- Superati **24 controlli import**, **27 controlli flusso**, **6 controlli data**, **8 controlli tornei vuoti**,
  **12 controlli rimborsi** e **14 controlli comitati**.
- Build Angular production e lint dei 15 file PHP distribuiti riusciti; restano i warning opzionali jsPDF.
- **43 controlli API/browser** superati: sincronizzazione dal pulsante, data persistente, griglie,
  prezzi, rimborsi e visualizzazione esplicita dei tornei senza ricevuta. Totale mirato: **146 controlli**.

I dati originali dei PDF già importati vengono conservati anche se una ricevuta non compare più
nel riepilogo; l'assenza da un elenco non viene usata per cancellare lo storico.

## Aggiornamento 0.6.0 — 31 ottobre 2026

- Scansione reale dei due cataloghi richiesti: **80 tornei, 58 territoriali e 22 nazionali**.
  Verificati i frame effettivi dei dettagli e i link ricevuta nazionali/territoriali.
- PDF reale territoriale: otto iscrizioni, 120 euro; PDF nazionale reale: zero iscrizioni, zero euro.
  Supportata l'assenza dell'intestazione comitato nei PDF nazionali e la sezione squadre vuota.
  Una sezione squadre contenente iscrizioni richiede un formato dedicato e viene rifiutata esplicitamente.
- **8 controlli sui tornei vuoti**: persistenza, nessuna iscrizione inventata, riconciliazione zero,
  prezzo non modificabile, idempotenza, transizioni vuoto/popolato, conservazione prezzi manuali,
  identità nazionale coerente tra i due endpoint PDF.
- **42 controlli API/browser**: inclusi sync completo simulato sui nuovi cataloghi, prezzi, rimborsi,
  data ultima sincronizzazione e quattro controlli di visualizzazione/modifica/mobile dei tornei vuoti.
- **24 controlli import**, **27 controlli flusso cataloghi**, **6 controlli data ultima sincronizzazione**.
- Ripetuti anche **12 controlli rimborsi/riconciliazione** e **14 controlli comitati**; lint dei 15 file PHP distribuiti superato.
- Build Angular production riuscita, con i warning opzionali jsPDF già documentati.

La verifica reale non ha importato nel database i dati dell'account e non ha scaricato tutti gli 80 PDF.
Le verifiche ordinarie usano fixture e WordPress isolato. I cataloghi disponibili possono cambiare nel tempo.

Aggiornamento 0.5.1 (31 ottobre 2026): aggiunta ultima sincronizzazione riuscita, salvata sul server per società.
Sei controlli backend mirati: valore iniziale assente, avvio senza aggiornamento, completamento,
passo ripetuto idempotente, errore che conserva la data precedente, isolamento tra società.
Verificata compilazione Angular production e sintassi PHP dei metodi modificati.
Superati anche 38 controlli API/browser, inclusi comparsa della data accanto al pulsante,
persistenza dopo reload e assenza di overflow su mobile.

Eseguita il 23 ottobre 2026 su un WordPress 6.6.2 isolato, PHP 8.2.12,
driver ufficiale SQLite solo per l'ambiente di test, Chrome e Playwright.
Frontend compilato con Angular 21.2.25 e Node 24.22.1.

Risultato: **39 controlli di integrazione superati**, nessun errore JavaScript non gestito.

- Configurazione pubblica limitata ai campi previsti, risposte non memorizzabili in cache.
- API con URL REST sia riscritti sia `?rest_route=`.
- Rifiuto di utenti anonimi, credenziali errate e richieste malformate/cross-origin.
- Login, cookie HttpOnly/SameSite, scadenza e persistenza con “Ricordami”.
- Ripristino della sessione, token CSRF e logout con revoca del token sul server.
- Rifiuto del riutilizzo di token revocati e scaduti.
- Revoca dopo disabilitazione, riabilitazione o reset password dell'utente.
- Portale disabilitato e logout ancora consentito per le sessioni esistenti.
- Limite dei tentativi falliti, risposta 429 con Retry-After.
- Login Angular, errori, password visibile/nascosta, accesso alla dashboard, refresh e logout.
- Conservazione dell'URL `/fitet-ricevute/` durante la navigazione.
- Tre moduli predisposti, con pulsanti disabilitati e stato esplicito.
- Nessuna credenziale in localStorage; impaginazione mobile senza overflow orizzontale.
- Salvataggio configurazione e creazione account da wp-admin.
- Autenticazione via API dell'account effettivamente creato da wp-admin.

Superati anche compilazione Angular production, lint `php -l` di tutti gli otto file PHP
distribuiti e controllo d'integrità dello ZIP installabile.

Limiti della verifica: nessuna installazione sul sito reale, nessun collegamento al portale
FITeT e nessun test di sincronizzazione/PDF/Excel/rimborsi, non implementati in questa versione.
Il database di produzione previsto resta MySQL/MariaDB di WordPress.

## Aggiornamento 0.1.1 — credenziali extranet FITeT

Superati **24 nuovi controlli mirati** (13 backend e 11 browser): salvataggio cifrato,
lettura esatta dei caratteri della password, conservazione con campo vuoto, sostituzione,
rimozione esplicita, rifiuto di dati malformati o cifrati alterati, opzione senza autoload,
assenza del segreto nell'HTML e nell'API pubblica, protezione tramite nonce e accesso admin.
Verificata anche l'indipendenza dal salvataggio delle impostazioni generali.

Compilazione Angular production riuscita e `php -l` superato per i nove file PHP distribuiti.
Le credenziali usate nei test sono fittizie. Non è stato eseguito alcun login all'extranet reale.

## Aggiornamento 0.2.0 — 24 ottobre 2026

- **18 PDF reali, 96 iscrizioni** estratte dal parser PHP: le 16 ricevute già disponibili,
  il PDF originale allegato e il PDF pubblico del link fornito. Le annotazioni sui rimborsi sono escluse.
- **24 controlli backend** superati: identità del torneo indipendente da società/ordine parametri,
  parsing della ricevuta con celle visivamente sovrapposte, calcolo delle quote, schema/relazioni,
  idempotenza, variazioni iscritti, prezzi manuali conservati (anche zero), ripristino automatico,
  rollback e download nella cartella richiesta. Login, navigazione ASP, cookie e scoperta ricevute
  sono verificati con una fixture controllata che non effettua accessi al vero portale.
- **39 controlli di regressione autenticazione/admin** superati sulla nuova versione.
- **15 controlli API/browser** superati: protezione letture/prezzi, aggiornamento delle due tabelle,
  conservazione della quota dopo sincronizzazione completa simulata, reset esplicito con `null`,
  rifiuto del campo omesso, filtri e layout mobile. Nessun errore JavaScript non gestito.
- Compilazione Angular production e lint di tutti i file PHP distribuiti superati.

La navigazione autenticata della società nell'extranet reale e una ricevuta reale contenente
doppi richiedono ancora una verifica sul formato effettivo. Nessun login reale è stato tentato.
La DDL è una proposta da rivedere, documentata in `docs/DATI.md` e usata in `docs/schema.sql`.

## Aggiornamento 0.3.0 — 24 ottobre 2026

Verificato sul WordPress isolato, senza modificare il sito reale o effettuare login all'extranet reale.
Totale: **124 controlli automatici superati**, oltre a build, lint PHP e verifica visiva dei PDF.

- **39 controlli di regressione autenticazione/admin** superati.
- **34 controlli API/browser** superati: AG Grid Community 36.2 su due pagine distinte,
  modifica della quota con salvataggio immediato, origine prezzo e scostamento dal totale PDF,
  aggiornamento delle iscrizioni, filtri e ordinamento, selezione checkbox, recap cumulativo e per atleta,
  selezione conservata dopo refresh, tre formati di download, marcatura e riapertura da entrambe le pagine,
  protezione CSRF e rifiuto delle selezioni non valide. Nessun errore JavaScript non gestito.
- **24 controlli backend di import/sincronizzazione** e **12 nuovi controlli di riconciliazione/rimborsi**:
  totali della ricevuta contati una sola volta, prezzi zero e mancanti, stato conservato al sync,
  importo già rimborsato indipendente dalle nuove tariffe, idempotenza, isolamento per società e rollback.
- **11 controlli di calcolo/PDF**: ripartizione esatta dei centesimi nei doppi, assegnazione a un solo atleta,
  raggruppamento delle partecipazioni, esclusione delle quote pagate, blocco dei prezzi mancanti,
  generazione cumulativa/individuale e ricevuta di 80 quote su dieci pagine.
- **4 controlli mirati su 51 righe**: caricamento di tutte le pagine API, filtro su una riga oltre le prime 50,
  conservazione della selezione nascosta da un filtro e azzeramento completo delle checkbox.
- PDF generati sia dal browser sia dal test del generatore; testo/importi/paginazione verificati con pypdf,
  rendering controllato con PDFium. Accenti e nome greco preservati dal font Noto Sans (SIL OFL inclusa).
- Compilazione Angular production e lint dei 14 file PHP distribuiti superati.
  La build segnala warning CommonJS delle dipendenze opzionali HTML/SVG di jsPDF; non sono errori di compilazione.

La tabella operativa `rimborsi` conserva lo stato corrente, importo, autore e data della marcatura.
Non è ancora previsto uno storico delle emissioni PDF o dei cambi di stato. I PDF non sono archiviati sul server.
Restano da collaudare il flusso extranet con un account reale e il parsing di una ricevuta reale con doppi.

## Aggiornamento 0.4.0 — 24 ottobre 2026

**157 controlli automatici superati**, oltre a compilazione, lint PHP e controllo visivo di frontend e wp-admin.

- **21 controlli browser** su schede giocatore e preferenze: accesso dal secondo atleta di un doppio,
  annata iniziale più recente, accesso alle annate precedenti, filtri e ricerca conservati dopo refresh,
  navigazione, logout e nuova sessione del browser; isolamento tra account nello stesso browser.
  Verificati anche filtri indipendenti dei due recap rimborsi e dell'elenco giocatori.
- Layout controllato a **390 e 320 px**; configurazione wp-admin salvata e nuovo utente creato dal form mobile.
  Moduli a tutta larghezza, elenco utenti disposto in schede, navigazione principale su due righe e checkbox fisse.
- **5 controlli touch**: apertura della modifica quota con un tocco, aggiornamento degli importi delle iscrizioni,
  checkbox selezionabile con la tabella scorsa, assenza di overflow e nessuna preferenza creata per utenti anonimi al logout.
- **11 controlli del modello giocatore**: confine 30 giugno/1 luglio, cambio dell'anno solare,
  tornei distinti rispetto alle gare, stato dei doppi su entrambi gli atleti, identità per tessera e omonimie.
- **7 controlli backend**: COMITATO 30 nazionale (anche normalizzato), altri codici territoriali,
  codice assente esplicito e metadati coerenti tra gare, iscrizioni e anteprima rimborsi.
- Ripetuti **39 controlli autenticazione/admin**, **34 controlli API/browser del flusso prezzi/rimborsi**,
  **24 controlli import/sincronizzazione**, **12 controlli di riconciliazione/rimborso** e
  **4 controlli di paginazione/selezione su 51 righe**, tutti superati.
- Build Angular production riuscita e lint dei 14 file PHP distribuiti superato; restano i warning
  CommonJS delle dipendenze opzionali di jsPDF già documentati nella versione precedente.

Le preferenze contengono impostazioni di visualizzazione e valori dei filtri, senza credenziali,
dataset o selezioni per emissione. La scelta di un'annata salvata prevale sul valore iniziale.
La mappa dei nomi dei comitati territoriali non è ancora stata fornita: la tabella mostra i codici originali.
PDF originali scaricati dai link pubblici, senza cookie di login; credenziali usate dalla sola ricerca extranet.

## Aggiornamento 0.5.0 — 24 ottobre 2026

- **Login reale riuscito**, con sessione ASP nuova e cookie società ricevuto dal redirect a gestione_user.asp.
  I cookie incollati nella richiesta dell'utente non sono stati riutilizzati.
- **20 ricevute trovate**, confronto esatto con i 20 link dell'HTML fornito: nessuna mancante o aggiuntiva.
- **20 PDF pubblici scaricati e analizzati**, 84 iscrizioni di singolare, zero errori.
  Nessun nuovo login durante questa verifica e nessuna importazione dei dati reali nel database.
- **29 controlli mirati del flusso extranet**: sequenza degli endpoint, rotazione e conservazione dei cookie,
  pulizia della sessione in memoria, deduplicazione, paginazione, credenziali errate, sessione scaduta,
  rifiuto dei redirect esterni o verso altre azioni, HTML troppo grande, limite pagine ed elenco vuoto.
  I fallimenti della ricerca non modificano i dati; il job non contiene credenziali o cookie ASP.
- **24 controlli backend di import/sincronizzazione** superati con la nuova fixture del flusso osservato.
- **14 controlli comitati**, compresi nomi dal menu pubblico, normalizzazione dei codici,
  fallback e conservazione del nome originale del PDF in entrambe le liste e nell'anteprima.
- **36 controlli API/browser** superati: sincronizzazione completa simulata dal pulsante Angular,
  conservazione delle tariffe manuali, nome comitato nella griglia, modifica prezzi, rimborsi e download PDF.
  Nessun errore JavaScript non gestito; layout mobile senza overflow della pagina.
- Build Angular production riuscita, lint dei 15 file PHP distribuiti e dei quattro helper PHP modificati superato.
  Restano i warning CommonJS delle dipendenze opzionali di jsPDF già documentati.

Totale della verifica mirata 0.5.0: **103 controlli automatici**, oltre a login reale, confronto dell'elenco,
parsing dei 20 PDF pubblici, build, lint e verifica degli archivi. La suite completa delle versioni precedenti
non è stata ripetuta per questa modifica.

Le credenziali reali sono state lette solo in memoria da un file fornito per la verifica esplicita;
non sono state salvate nelle impostazioni di test, nel codice o negli archivi distribuiti.
I test ordinari usano credenziali fittizie. I metodi CLI `live-discovery.php` e `live-pdfs.php`
richiedono esecuzione esplicita e non fanno parte della suite ordinaria.
Il formato di una ricevuta reale con doppi rimane da verificare; i 20 PDF controllati contengono solo singolari.

## Aggiornamento 0.6.2 — 31 ottobre 2026

Modifiche nella nuova cartella D:/tpdev/fitet-ricevute-plugin/wp-content/plugins/fitet-ricevute.
Build Angular production riuscita. Superati 11 controlli browser mirati:
valore iniziale 100 nelle viste prezzi, iscrizioni e giocatori; barra indeterminata nella ricerca;
0%, 47% e 100% corrispondenti al job; permanenza della barra e risultato finale nello stesso riquadro;
layout mobile senza overflow; preferenza salvata 25 rispettata; nessun errore JavaScript non gestito.
Impostato 100 anche nella scheda giocatore e nei due recap rimborsi. Nessuna modifica al backend
operativo: il test controlla l'avanzamento con risposte API simulate e non accede al portale FITeT.

## Aggiornamento 0.7.0 — 1 novembre 2026

Ristrutturati elenchi/dettagli con cinque voci principali, stagione globale e tabelle condivise.
Aggiunte tabelle operative carrello ed emissioni, separate dalle fonti FITeT e dal registro operativo.
Totale mirato: **102 controlli automatici**, oltre a build, lint e integrità degli archivi.

- **22 controlli workflow backend**: carrello persistente, isolamento account/società, aggiunta idempotente,
  lock concorrenti per carrello/emissioni, blocco quote pagate/inattive, confronto prezzi e rollback anche
  dopo l'aggiornamento della prima quota, timestamp UTC, snapshot importi immutabili, svuotamento e retry idempotente.
- **33 controlli browser/API**: login/CSRF, menu, raggruppamento tornei, checkbox con iscritti e persistenza,
  stagione globale, collegamenti con filtri parent, dettagli torneo/gara/giocatore, modifica quota,
  aggiunta singola e multipla al carrello, rimozione, logout e ripristino in un nuovo contesto browser,
  emissione cumulativa e individuale con PDF ZIP validi, registro/dettaglio/timestamp,
  importi invariati dopo cambio prezzo, riscaricamento, storico giocatore, mobile e selezione del testo.
- **24 controlli import/sincronizzazione** precedenti superati sul nuovo schema.
- **12 controlli riconciliazione/rimborsi** precedenti: inclusa conservazione dello stato dopo resync.
- **11 controlli modello/PDF**: doppi, centesimi dispari, quote zero/mancanti, output cumulativo/individuale,
  blocco delle quote già pagate e ricevuta su più pagine.
- Build Angular production riuscita e lint dei 16 file PHP distribuiti; restano i warning opzionali jsPDF.
- Controllati screenshot di registro desktop e scheda giocatore mobile. Scorrimento nativo della griglia
  su `.ag-grid-viewport`, con azioni delle colonne finali raggiungibili anche a 390 px.

Test su WordPress 6.6.2 isolato con SQLite: dati fittizi ripristinati al termine, nessun accesso FITeT
reale e nessuna emissione nel sito principale. MySQL/MariaDB resta il database di produzione previsto.
La suite delle vecchie rotte UI non è stata ripetuta perché sostituita da `tests/portal.cjs`.
Le vecchie marcature rimborsata restano operative, senza generare date di emissione retroattive.

## Aggiornamento 0.7.1 — 1 novembre 2026

Firma configurabile via upload PNG/JPEG, anteprima, sostituzione e rimozione.
- **15 controlli backend**: facoltatività, dimensioni/MIME, dati esatti, opzione senza autoload,
  assenza nel config pubblico, conservazione con campo vuoto, file troppo grandi/SVG/nonimmagine rifiutati,
  JPEG accettato, firma cambiata prima del commit rifiutata senza svuotare il carrello,
  copia canonica nell'emissione e conservazione dopo rimozione della configurazione.
- **15 controlli browser/API**: upload dal form wp-admin, anteprima, lettura autenticata,
  config anonimo privo della firma, nonce/capability, salvataggio impostazioni generali indipendente,
  emissione PDF firmata, registro leggero senza ripetere l'immagine per ogni riga, sostituzione,
  ristampa con la firma originale, rimozione e formato non consentito, nessun errore JavaScript.
- **11 controlli modello/PDF** precedenti ripetuti: importi e ripartizione dei doppi invariati.
- Verificati PDF cumulativo e individuali con firma PNG, PDF con firma JPEG, PDF senza firma e
  PDF di dieci pagine con firma disegnata una sola volta sull'ultima pagina.
  Pixel della firma della prima emissione e della ristampa identici nonostante sostituzione della configurazione.
- Rendering Poppler e controllo visivo: firma proporzionata sopra la riga, senza sovrapposizioni con note o footer.
- Build Angular production riuscita e lint PHP; immagini e dati usati nei test interamente fittizi,
  salvati solo nel WordPress isolato. Nessun caricamento della firma sul sito principale.

Per rigenerare le immagini di prova: `python tests/signature-fixtures.py`; quindi `php tests/signature.php`.
La suite browser è `node tests/signature.cjs` (usa il setup/ripristino di `portal-fixture.php`).
La verifica PDF del modello è `node tests/signature-pdf.cjs`; richiede pypdf/Poppler per l'ispezione successiva.


## Aggiornamento 0.8.1 — 1 novembre 2026

- **31 controlli backend configurazione/bozze/backup**: salvataggio unico e annullamento su immagine
  invalida, password/immagini mantenute coi campi vuoti, note per riga e proprietà della bozza,
  emittente preso dal server, doppi invariati, snapshot di note/firma/timbro, backup completo gzip,
  rifiuto di colonne/riferimenti/percorsi/hash/immagini/totali incoerenti, rollback su errore database
  con pulizia dei PDF nuovi, ripristino bozze e storico, credenziali di destinazione preservate,
  creazione disabilitata degli account mancanti.
- **29 controlli browser/API**: unico form e pulsante di salvataggio, immagini e impostazioni salvate
  insieme, upload invalido senza modifiche parziali, nonce, export riservato, anteprima senza modifiche,
  conferma ripristino e logout delle sessioni, mantenimento password/credenziali destinazione,
  note editabili in griglia, textarea generale, doppi ancora selezionabili, emissione senza download
  e passaggio al dettaglio, stampa nelle tre modalità, immagini storiche dopo rimozione dal config,
  layout mobile e nessun errore JavaScript non gestito.
- Ripetuti **24 controlli dati/importazione**, **27 flusso sincronizzazione**, **13 crittografia credenziali**,
  **22 workflow rimborsi** e **11 modello/PDF**, inclusi centesimi dispari dei doppi.
- Build Angular production riuscita; lint dei **19 file PHP distribuiti** riuscito.
  Restano i warning di ottimizzazione delle dipendenze opzionali jsPDF.
- PDF cumulativo/individuale e ristampa verificati con pypdf: note riga e generali presenti,
  emittente configurato, firma e timbro disegnati una volta ciascuno. Le ristampe conservano le immagini
  originali. La nota sulla divisione dei doppi non appare nelle ricevute individuali con soli singolari.
- Note generali di **100 righe** paginate su tre pagine, testo interamente presente entro i margini,
  firma/timbro una sola volta alla fine. Rendering Poppler dell’ultima pagina controllato visivamente.

Test nel WordPress isolato con SQLite e dati fittizi, ripristinati a fine prova. Nessun accesso FITeT
reale, nessuna emissione sul sito principale. Il database di produzione previsto resta MySQL/MariaDB;
questo ambiente non è stato usato per le verifiche.

Suite attuali: `admin-backup.php` e `admin-ui.cjs`, con `admin-ui-fixture.php` per setup/ripristino.
Le suite UI storiche `portal.cjs` e `signature.cjs` mantengono i flussi precedenti alla 0.8.1.
`signature-fixtures.py` genera firma e timbro sintetici; `signature-pdf.cjs` genera anche il PDF
con 100 righe di note per ispezione con pypdf/Poppler.


## Aggiornamento 0.9.0 — 1 novembre 2026

Layout riorganizzato sui cinque mockup locali, conservando colori e caratteri dell’app.
- **43 controlli browser del layout**: barra desktop unica, ricerca/checkbox affiancate, stagione
  vuota senza tornei e sincronizzazione disabilitata, scelta conservata al reload, menu utente e
  profilo, intestazione condivisa, vista mobile compatta, drawer con sfondo inattivo, azioni utente
  affiancate, focus tastiera trattenuto nel menu, chiusura via hamburger/Escape/sfondo/navigazione/
  selezione stagione, logout, nessun overflow delle pagine a 390 px e verifica a 320/1280 px.
- **3 controlli mirati delle preferenze**: prima visita da 320 px, azioni scorrevoli su mobile,
  ripristino colonne fisse sul desktop e conservazione della ricerca dopo reload.
- **29 controlli configurazione/backup/bozze/stampa** ripetuti: note, firma/timbro storici,
  emissione, stampa nelle tre modalità, backup completo e conservazione delle credenziali.
- Corretta l’assegnazione della società nella bozza usando uno stato reattivo: il pulsante di
  emissione si aggiorna al termine del caricamento, senza richiedere un’altra interazione utente.
- Build Angular production e lint del bootstrap PHP riusciti; restano i warning di ottimizzazione
  delle dipendenze opzionali jsPDF. Nessuna modifica al modello dati o alle API backend.
- Controllate visivamente le anteprime di Tornei desktop/mobile e del menu mobile; su mobile
  le colonne Azioni non coprono più il nome del torneo. Gli screenshot sono in tests/screenshots,
  esclusi dai pacchetti distribuiti.

Suite: `node tests/layout.cjs` e `node tests/layout-pinning.cjs`; per le stagioni fittizie usare
`php tests/portal-fixture.php` e ripristinare con `php tests/portal-fixture.php clean`.
Per configurazioni ed emissioni resta `admin-ui.cjs` con setup/ripristino `admin-ui-fixture.php`.
Verifiche nel WordPress isolato con SQLite, account e immagini fittizi; dati di prova ripristinati.


## Aggiornamento 0.10.0 — card mobili

- **24 controlli browser**: nessuna istanza AG Grid in vista mobile, card a batch di 20,
  infinite scroll su 65 tornei simulati, ricerca oltre il batch visualizzato, dettagli espandibili,
  ripristino griglia desktop e dei filtri per colonna, ricerca condivisa e filtri desktop esclusi
  dalla vista mobile, aggiunta/rimozione bozza, note persistite e modifica quota tramite API,
  recap per atleta a card, dettaglio torneo con elenchi a card, directory giocatori, assenza
  di overflow e di errori JavaScript. Prezzi e bozze modificati solo nel sito di prova isolato.
- **29 controlli configurazione/backup/emissione/stampa** ripetuti con AG Grid desktop:
  emissioni, note, firma/timbro storici, formati PDF e ripristino backup funzionanti.
- Build Angular production completata; lint del bootstrap PHP riuscito. Nessuna modifica a DDL
  o API backend. Restano i warning di ottimizzazione delle dipendenze opzionali jsPDF.
- Anteprime delle card Tornei, Gare e Bozza controllate visivamente. I file di prova non entrano
  nei pacchetti. AG Grid resta installato e attivo sul desktop; fino a 1100 px non viene montato.

Suite corrente: `mobile-cards.cjs`, setup e pulizia con `admin-ui-fixture.php`. La fixture dei
65 tornei viene simulata solo nella risposta browser, senza inserire record nel database.
I dati operativi del sito isolato vengono ripristinati alla fine delle verifiche.


## Aggiornamento 0.10.1 — conteggi rimborsi e ricerca mobile

30 controlli superati in `tests/refund-counts-ui.cjs`: modello vuoto/zero/parziale/totale,
colonne numeriche Tornei e Gare, dettagli con 0%, 16,7%, 33,3% e 100% e denominatori diversi
per torneo/gara; card con conteggi senza percentuali, titoli collegati per torneo/gara/iscrizione/
rimborso/giocatore/recap, navigazione al dettaglio, ricerca e pulsante a icona affiancati,
azzeramento effettivo, nessun overflow mobile o errore JavaScript.

Dati di rimborso, emissione e bozza simulati nelle risposte del browser, senza scritture al database.
Verifiche su WordPress isolato; build Angular production e lint del bootstrap PHP riusciti.
Anteprima mobile controllata visivamente. Nessuna modifica alle API o alle tabelle.


## Aggiornamento 0.11.0 — locandine

- Verificati gli HTML pubblici FITeT: esempio corrente `4_1_281.pdf` ed esempio storico
  `40/4_7_774.pdf` trovati nei link, senza scaricare i documenti.
- **26 controlli backend** (`posters.php`): estrazione/persistenza, ANNO osservato, cambio stagione,
  cataloghi privi di ANNO, host/identità/riferimenti ambigui rifiutati, nazionali esclusi, mancanze
  opzionali, nessun cookie/redirect, tornei senza iscrizioni, trasferimento catalogo-ricevuta,
  risincronizzazioni, backup nuovi/precedenti, coda progressiva e nessuna richiesta ai PDF locandina.
- **19 controlli browser/API** (`posters-ui.cjs`): link su tabella/card/dettaglio, apertura in una nuova
  scheda dell’URL atteso, attributi di sicurezza, stagioni passate, nazionali e riferimenti assenti,
  tornei senza iscrizioni, layout mobile senza overflow, nessun errore JavaScript. Gli URL esterni
  delle nuove schede sono intercettati dal browser di prova: nessun PDF viene scaricato nei test UI.
- Ripetuti **24 controlli import/dati**, **27 flusso extranet**, **31 configurazione/bozze/backup**.
- Build Angular production e lint PHP superati. Dati operativi fittizi ripristinati dopo le prove.
  Screenshot mobile controllato visivamente. Verifiche database sul WordPress isolato con SQLite.

Per la suite UI: `php tests/poster-ui-fixture.php`, server con `tests/router.php`,
`node tests/posters-ui.cjs`, infine `php tests/poster-ui-fixture.php clean`.


## Aggiornamento 0.12.0 — PDF completo ed Excel rimborsi

- **19 controlli modello/export** in `refund-export.cjs`: importi emessi, esempio utente da 96 EUR
  con beneficiari da 42/27/15/12, doppi e centesimi dispari, deduplicazione originali, pagine fisiche
  degli allegati, tre fogli Excel, tabelle native senza subtotali, importi/date numerici, formule e
  risultati memorizzati, somma dell’intera colonna senza doppio totale, originali mancanti,
  importi incoerenti rifiutati e documenti multipagina con note lunghe.
- **12 controlli backend** in `refund-documents.php`: snapshot canonico, riferimenti per quota,
  lettura PDF verificata, isolamento per società/emissione, file e percorsi non esposti, preservazione
  dopo modifica del torneo, hash e percorsi invalidi rifiutati, legacy dichiarato e backup.
- **17 controlli browser/API** in `refund-export-ui.cjs`: emissione senza download automatico,
  eliminazione modalità individuale, manifesto, PDF binario, accesso anonimo rifiutato, documento
  estraneo rifiutato, PDF unico, Excel e ZIP con due file, importi storici dopo cambio prezzi,
  documento mancante senza PDF parziale e con Excel segnalato, mobile e assenza di errori JavaScript.
- Ripetuti **31 controlli backend configurazione/bozze/backup** e **29 controlli UI** aggiornati
  al nuovo export, incluse firma/timbro storici dopo rimozione dalla configurazione.
- Importato l’XLSX prodotto dal plugin in un motore indipendente: ricalcolo da 37,01 a 47,01 dopo
  modifica di una quota da 15 a 25, entrambi i beneficiari inclusi, nessun errore formula.
  Corretti i metadati ExcelJS totalsRowShown/totalsRowCount per dichiarare esplicitamente zero
  righe totali nelle tabelle, mantenendo i totali generali esterni alle colonne degli importi.
- Renderizzati e controllati tutti e tre i fogli; PDF con riepilogo, dettaglio e indice controllati
  visivamente. pypdf/pdfplumber verificano testi originali presenti una sola volta, riferimenti
  corretti e testo entro le pagine anche nel documento di 27 pagine. Nessuna rasterizzazione.
- Build Angular production e lint dei 21 file PHP distribuiti riusciti. Restano avvisi di ottimizzazione
  delle dipendenze CommonJS. Test su WordPress isolato con SQLite, dati e immagini fittizi ripristinati.
  Le emissioni legacy dichiarano che gli originali sono ricostruiti dall’archivio corrente.

Fixture browser: `php tests/export-ui-fixture.php`, server con `tests/router.php`,
`node tests/refund-export-ui.cjs`, poi `php tests/export-ui-fixture.php clean`.
Le verifiche della suite admin usano il proprio setup/ripristino `admin-ui-fixture.php`.
Le suite storiche dei PDF individuali restano riferite al vecchio generatore, fuori dalle rotte attive.

## Aggiornamento 0.12.1 — Profilo e filtri tabella

`tests/table-profile-ui.cjs` verifica accesso, salvataggio delle preferenze sul server, validazione della densità, protezione CSRF e rifiuto anonimi, ripristino con nuovo browser, switch autoHeight e ripristino altezza fissa, spaziatura compatta, applicazione alla pagina giocatori, filtri dalle intestazioni e ordinamento, assenza dei filtri Azioni e della riga aggiuntiva, rimozione dei link richiesti e della checkbox Gare, conservazione delle card mobile e assenza di overflow/errori browser. Il test ripristina le preferenze iniziali.

## Reset di sviluppo 0.12.2

`php tests/development-reset.php`: 25 controlli sull’installazione isolata. Verifica blocco host esterni, sito configurato esterno anche con host locale, utenti senza privilegi, lock ripristino/sincronizzazione, cancellazione delle sei tabelle/PDF/stato sincronizzazione, conservazione impostazioni/account/credenziali/immagini/preferenze/file estranei e rilascio del lock. Ripristina il backup al termine.

## Funzionalità 0.13.0

- `php tests/refund-revision.php`: 18 controlli su rimborso ridotto, contributo con detrazioni, modifica atomica, congelamento prezzi, rilascio/aggiunta quote, storico, conflitti, isolamento bozza e backup.
- `php tests/refund-documents.php`: 12 controlli su integrità e appartenenza degli originali, compatibilità legacy e backup.
- `node tests/refund-export.cjs`: 19 controlli su ripartizione, tabelle Excel e PDF multipagina; struttura aggiornata a quattro fogli.
- `node tests/settlement-export.cjs`: 10 controlli PDF/Excel su riduzione di 5 EUR e contributo da 80 EUR con detrazione di 15 EUR, totali e formato verticale.
- Flusso browser in `tests/new-workflow-ui.cjs`: torneo manuale con ricevuta PNG convertita e locandina PDF, suggerimenti atleti, link numerici, Tipo, azioni checkbox, penna note, default contanti e modifica rimborso. Verificati 21 controlli; la chiusura del pannello sincronizzazione viene verificata anche separatamente da `tests/sync-dismiss-ui.cjs` (2 controlli).
- Motore spreadsheet indipendente: ricalcolo corretto senza rettifiche e con contributo, e aggiornamento del totale da 65 a 75 EUR modificando il contributo da 80 a 90 EUR. `IFERROR` protegge la somma delle rettifiche quando la relativa tabella è vuota.
- Regressioni: 31 controlli admin/bozze/backup, 25 reset, 26 locandine e 27 sincronizzazione superati.
- Controllo visivo: PDF riepilogo e dettaglio verticale e foglio Excel riepilogo renderizzati; pagina Tornei verificata a 390 px senza overflow.

Compilazione di verifica esclusivamente in `tests/.runtime/qa-app`; nessuna sostituzione della build distribuita in `assets/app`.

### Pagamenti misti per atleta

`tests/athlete-payments.php`: 9 controlli (metodi distinti, aggregazione più iscrizioni per atleta, fallback legacy, doppio con centesimo dispari, metodo non valido e saldo nullo). `tests/refund-revision.php`: 19 controlli, incluso cambio modalità di un beneficiario senza variazione dell’importo e backup dello storico. `tests/athlete-payments-export.cjs`: PDF/Excel con tre atleti, due pagamenti cash e uno transfer, quote ripetute aggregate e subtotali esatti. `tests/athlete-payments-ui.cjs`: 8 controlli su default operatore, select desktop e card mobile, subtotali reattivi, esclusione degli zeri e assenza di overflow/errori. Superati anche i 19 controlli export e 10 sulle rettifiche. Ricalcolo indipendente dei subtotali Excel verificato modificando la modalità di un atleta. Riepilogo PDF e card mobile verificati visivamente. Build del sito invariata, compilazione solo nell’ambiente di test.

### Accordion iscrizioni disponibili in modifica rimborso

`tests/refund-available-ui.cjs`: 10 controlli browser su apertura inizialmente chiusa, montaggio della griglia solo dopo espansione, esclusione quote rimborsate/già incluse, filtro, aggiunta dal + desktop, disponibilità di quote eventualmente presenti nella bozza ordinaria, card mobile, aggiunta dell’ultima quota e assenza di richieste di modifica alla bozza ordinaria/errori browser. Dati simulati nel browser, nessuna emissione o modifica al database. Compilazione solo nella copia di test.

### Import JSON/Excel di un torneo manuale

`tests/manual-import.cjs`: 17 controlli su equivalenza dei modelli JSON/XLSX, singolo/doppio, tessere con zeri, euro numerici, date/versioni/importi non validi, ID e iscrizioni duplicate, doppio incompleto, numeri tessera non sicuri, esclusione allegati/conferme, formule Excel, gara inesistente e secondo torneo nel file. `tests/manual-import-ui.cjs`: 15 controlli browser su quattro azioni, download dei modelli, compilazione dei campi e degli atleti, obbligo della ricevuta manuale, reset degli allegati, errore senza perdita dati, nessuna chiamata al salvataggio e layout mobile. Modello XLSX creato con artifact-tool, renderizzato e riletto con il parser dell’app; il lettore normalizza in memoria i namespace OOXML prefissati per compatibilità con ExcelJS. Build solo nella copia isolata, assets del sito invariati.
