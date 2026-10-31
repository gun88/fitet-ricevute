# Modello dati proposto — da rivedere

La DDL effettivamente usata all'attivazione è in [schema.sql](schema.sql). `{prefix}` viene
sostituito con il prefisso del sito WordPress, senza assumere `wp_`.
Le tre tabelle di origine/prezzi e la tabella operativa dei rimborsi si aggiungono alle due tabelle di autenticazione.

| Tabella | Una riga rappresenta | Origine | Modificabile dall'utente |
| --- | --- | --- | --- |
| `fitet_ricevute_gare` | Una gara di un torneo per la società | Ricevuta FITeT | No |
| `fitet_ricevute_iscrizioni` | Un'iscrizione alla gara; nei doppi una coppia | Ricevuta FITeT | No |
| `fitet_ricevute_prezzi` | La quota associata alla gara | Calcolo automatico / correzione utente | Solo la quota manuale |
| `fitet_ricevute_rimborsi` | Lo stato di una quota/iscrizione | Operatore | Stato rimborsata, importo registrato alla marcatura |

La prima scelta da rivedere è la granularità della prima tabella: **una riga per gara**.
Per mantenere tre tabelle, le informazioni del torneo/ricevuta si ripetono sulle sue gare.
Una normalizzazione completa richiederebbe un'ulteriore tabella per separare tornei e gare.

## Identificativi

Per il link dell'esempio:

```text
TORNEO=8&ANNO=41&COMITATO=4&SOCIETA=718
```

La rappresentazione canonica del torneo è `ANNO=41&COMITATO=4&TORNEO=8`.
Si esclude esclusivamente `SOCIETA`, si ordinano i nomi dei parametri e si normalizzano
gli identificativi numerici. Gli eventuali altri parametri della ricevuta entrano nella chiave.
`tournament_key` è SHA-256 di questa rappresentazione; `tournament_ref` la conserva leggibile.
Protocollo HTTP/HTTPS e ordine dei parametri non cambiano l'identità.

Il codice società resta un campo separato, perché il contenuto della ricevuta dipende dalla società.
Il vincolo univoco di una gara è `(society_code, tournament_key, race_key)`.
`race_key` deriva da tipo singolare/doppio e descrizione della gara normalizzata.
Non c'è un identificativo gara esplicito nel PDF esaminato. Una modifica sostanziale della
descrizione crea una gara nuova: non viene trasferito silenziosamente il prezzo manuale di un'altra gara.

L'iscrizione ha un vincolo univoco `(race_id, entry_key)`. La chiave dell'iscrizione usa
la tessera dell'atleta o le due tessere della coppia. Quando il PDF non riporta la tessera
di un doppio, conserva il nome originale; eventuali omonimie/duplicati richiedono verifica.

## Quote

Tutti gli importi sono interi in centesimi di euro, senza arrotondamenti in virgola mobile.

- `automatic_cents`: risultato calcolato dal plugin, oppure `NULL` se non determinabile.
- `manual_cents`: correzione esplicita dell'utente, oppure `NULL` se assente.
- Prezzo effettivo: `COALESCE(manual_cents, automatic_cents)`. **Zero è un prezzo valido**.
- Le sincronizzazioni aggiornano solo `automatic_cents`, `auto_basis`, `auto_updated_at`.
- Autore e data della modifica manuale restano nella tabella prezzi e non sono modificati dal sync.
- “Ripristina automatico” rimuove il valore manuale: è un'azione esplicita dell'utente.

Se la ricevuta contiene solo singolari, quota = totale / numero iscrizioni.
Se contiene solo doppi, quota = totale / numero coppie, non numero atleti.
Se contiene entrambi, il prezzo automatico rimane `NULL` per tutte le gare della ricevuta.
Se il totale non è divisibile esattamente in centesimi, viene richiesta una verifica.

`receipt_total_cents` resta nella prima tabella perché è un dato originale del PDF.
È il totale dell'intera ricevuta, ripetuto sulle sue gare: **non va sommato per gara**.

La riconciliazione raggruppa per società e chiave del torneo, somma le quote effettive moltiplicate
per gli iscritti attivi di ciascuna gara e confronta il risultato con il totale PDF. I campi API
`calculated_total_cents`, `difference_cents`, `reconciliation` sono derivati, non riscrivono il PDF.
`difference_cents` = somma quote − totale PDF; `reconciliation` vale `match`, `mismatch` o `incomplete`.
Un prezzo mancante rende il confronto incompleto; il prezzo manuale zero resta valido.

## Stato dei rimborsi

`rimborsi.entry_id` collega lo stato operativo alla quota originale, con relazione uno a uno.
La sincronizzazione non aggiorna questa tabella e l'identità stabile dell'iscrizione conserva la marcatura.
`paid_cents` è la quota al momento della marcatura, con `updated_by` e `updated_at`.
Marcare di nuovo una quota già rimborsata non sostituisce quell'importo con una nuova tariffa.
La riapertura imposta `reimbursed = 0` e `paid_cents = NULL`; non è previsto uno storico dei cambi di stato.
Le modifiche multiple sono atomiche: ID non disponibili, società diverse o quote indefinite non
producono aggiornamenti parziali. Le iscrizioni non attive non sono disponibili per nuove emissioni.

Il recap per atleta ripartisce le quote di doppio come scelto dall'operatore. Il totale cumulativo
conteggia ogni coppia una sola volta. L'eventuale centesimo dispari va al primo atleta.
La marcatura riguarda tutta la coppia; non sono gestiti pagamenti parziali per uno solo dei due atleti.
I PDF sono generati sul client; non sono archiviati sul server e non cambiano automaticamente lo stato.

## Sincronizzazione e conservazione

1. Nuova sessione ASP con GET HTTPS `/login/login.asp`, poi POST a `/login/autentica.asp?COMIT=30`
   con `login`, `password`, `paginaOriginale` e gli eventuali campi nascosti del modulo.
   Vengono inviati Origin/Referer e seguiti i redirect consentiti: `/login/gestione_user.asp`
   imposta anche il contesto società. Cookie di sessione e di società sono conservati insieme,
   solo in memoria per la durata della ricerca. Le credenziali configurate rimangono cifrate in wp-admin.
2. GET dei due cataloghi disponibili: `/login/iscrizioni/testa_iscrizioni_reg.asp` e
   `/login/iscrizioni/testa_iscrizioni.asp`. Sono raccolti i link osservati a `iscrizioni_torneo_reg.asp`
   e `iscrizioni_torneo.asp`, deduplicati anche rispetto all'ordine dei parametri.
3. Nella stessa sessione GET di `/login/iscrizioni/lista_partecipazione_tornei.asp`: si raccolgono solo
   i link PDF osservati della società configurata. Nessuna pagina dettaglio o frame viene aperta.
   Cataloghi e ricevute sono deduplicati separatamente; eventuale paginazione è seguita sugli stessi elenchi.
4. I tornei vengono salvati direttamente dal catalogo. Il job conserva solo i link PDF del riepilogo
   partecipazioni. Ogni avanzamento scarica un PDF pubblico senza autenticazione, cookie o nuovi login,
   poi lo salva in `wp-content/uploads/fitet-ricevute/ricevute-pdf/` e lo importa.
5. Nome file: hash torneo + codice società + SHA-256 del PDF. Le copie identiche non generano altri file;
   le versioni diverse vengono conservate con nomi distinti.
6. Estrazione delle celle dalle posizioni del testo, per gestire anche le descrizioni che si sovrappongono alla tessera.
7. Parsing completo e controlli prima dell'importazione; note sui rimborsi escluse.
8. Transazione per ricevuta: upsert gare e iscrizioni; elementi scomparsi impostati `active = 0`.
   Dati precedenti e prezzi restano collegati, senza cancellazioni fisiche automatiche.
9. L'importazione aggiorna i prezzi automatici, preservando i manuali.

Le richieste di avanzamento usano API WordPress autenticate. Angular mostra avanzamento ed errori;
non scarica i PDF FITeT e non esegue il parsing PDF.

La ricerca usa una sessione per i tre elenchi consentiti, HTML massimo 1 MB e massimo 12 pagine.
I link ad azioni di gestione, dettagli e frame non vengono seguiti. Un PDF non valido genera un errore
per quella ricevuta; la data di ultima sincronizzazione riuscita si aggiorna solo senza errori.
La scomparsa da un elenco non cancella lo storico delle ricevute già importate.

I tornei presenti solo in catalogo hanno un segnaposto `race_kind=empty`, senza iscrizioni né prezzo.
`pdf_file`, `pdf_sha256` e `source_year` restano vuoti; l'API restituisce `has_receipt=false` e
`reconciliation=not_available`. Il frontend indica “Nessuna ricevuta” e non presenta uno zero come totale PDF.
La chiave catalogo usa comitato, numero torneo e data. Quando arriva il PDF, il segnaposto corrispondente
viene disattivato in transazione; la chiave autorevole del torneo resta quella dei parametri della ricevuta.
Il catalogo non sovrascrive snapshot PDF attivi, prezzi manuali o stato dei rimborsi.
I PDF effettivamente vuoti continuano a essere supportati come prima, con `has_receipt=true` e totale zero.

Verifica reale del 31 ottobre 2026: 80 tornei (58 territoriali e 22 nazionali), 19 link ricevuta,
un login e sei richieste di lettura complessive, zero pagine dettaglio. Download pubblico e persistenza
sono verificati con fixture; il nuovo controllo reale legge gli elenchi senza scaricare tutti i PDF.

Il doppio è supportato per righe con tre celle (gara, atleta 1, atleta 2), ma il formato non è stato
ancora verificato su una ricevuta reale contenente doppi. Un formato diverso viene rifiutato,
evitando importazioni parziali.

## Pagine e API

- `GET /gare`: prima tabella con `LEFT JOIN prezzi`; include numero iscrizioni attive e prezzo effettivo.
- `GET /iscrizioni`: seconda tabella con `JOIN gare` e `LEFT JOIN prezzi`.
- `PATCH /prezzi/{race_id}`: `{manual_cents: intero|null}`; richiede sessione e CSRF.
- `POST /rimborsi/anteprima`: `{ids: interi[]}`; verifica e restituisce da 1 a 1000 quote attive.
- `PATCH /rimborsi/stato`: `{ids: interi[], reimbursed: boolean}`; stato e importo, in transazione.
- `POST /sincronizzazione`: accesso extranet e scoperta automatica, restituisce un job.
- `GET /sincronizzazione/stato`: sessione richiesta; `last_sync_at` UTC ISO 8601 oppure `null`.
  La data è salvata sul server per società solo dopo il completamento senza errori di tutte le ricevute.
  Il browser la mostra accanto al pulsante in formato italiano, nel proprio fuso orario.
- `POST /sincronizzazione/{job_id}/passo`: download/parsing/import di una ricevuta del job.

Le API sono paginate a 50 righe; Angular carica tutte le pagine prima di applicare ricerca,
filtri, ordinamento e paginazione AG Grid. Prezzi e Iscrizioni sono pagine distinte.
Le API non restituiscono
credenziali extranet, cookie ASP, testo completo del PDF o percorsi fisici dei file.

Le liste e l'anteprima riportano `source_committee`, `committee_name` originale, `committee_label` e `tournament_scope`:
`national` per COMITATO 30, `territorial` per gli altri codici presenti, `unknown` se il codice manca.
Questa classificazione è derivata e non richiede nuove colonne o modifiche ai dati del PDF.
`committee_label` deriva dalla mappa `includes/committees.php`, estratta dai link REG del
[menu pubblico FITeT](https://portale.fitet.org/menu.php) il 24 ottobre 2026;
verificata anche la corrispondenza REG=4 / COMIT=4 nel menu tornei Lombardia.
Comprende 21 comitati territoriali e il nazionale 30, con fallback “Comitato N” per codici sconosciuti.
Il nome originale del PDF non viene sostituito.

Le schede giocatore sono costruite sul client usando tutte le pagine autenticate di `GET /iscrizioni`.
La tessera identifica il giocatore; in sua assenza si usa il nome normalizzato, con i limiti delle omonimie.
Il recap conta il torneo una sola volta anche con più gare; lo stato di una quota di doppio è quello della coppia.
Le annate si ricavano dalla data (1 luglio–30 giugno). Il valore iniziale è l'ultima annata nell'intero dataset,
salvo una preferenza già salvata. Il parametro numerico originale `ANNO` resta conservato senza deduzioni arbitrarie.

Filtri, ordinamento, ricerca e disposizione delle colonne sono conservati in localStorage per sito/account/tabella.
Lo stato di selezione per emissione resta separato e non viene incluso nelle preferenze persistenti.

Il file `.htaccess` blocca l'accesso diretto ai PDF su Apache; `web.config` lo blocca su IIS.
Su Nginx va applicato nel server block un `deny all` alla location
`/wp-content/uploads/fitet-ricevute/ricevute-pdf/` (adattandola a eventuali percorsi uploads personalizzati).
Il plugin non offre per ora un download dei PDF originali dal frontend.
