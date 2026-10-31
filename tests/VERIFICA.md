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
