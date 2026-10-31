# FITET Ricevute

Plugin WordPress con frontend Angular per la gestione delle ricevute dei tornei FITeT.
Versione **0.6.2**: 100 righe iniziali per tutte le griglie e barra di avanzamento della sincronizzazione.

## Cosa funziona

- Portale autonomo, indipendente dal tema, su `/fitet-ricevute/`.
- Login con username e password, “Ricordami”, ripristino sessione dopo refresh e logout.
- Configurazione in **wp-admin → FITET Ricevute → Configurazione**.
- Sezione **Extranet FITeT** per username e password della società, riservata agli amministratori.
- Gestione degli utenti del portale: creazione, modifica, disabilitazione e reset password.
- API WordPress per configurazione pubblica, login, sessione e logout.
- Interfaccia responsive in italiano, con messaggi di errore e stati di caricamento.
- Tre tabelle di origine/prezzi e una tabella operativa per lo stato dei rimborsi; [DDL da rivedere](docs/schema.sql).
- Due pagine distinte, **Prezzi tornei** e **Iscrizioni**, con AG Grid Community, filtri per colonna,
  ricerca su tutte le colonne, ordinamento e paginazione su tutti i dati caricati dalle API.
- Modifica della quota nella cella: Invio o uscita dalla cella salva tramite API e aggiorna entrambe le viste.
- Colonne origine prezzo (calcolato/sovrascritto/da definire), verifica del totale PDF e scostamento.
- Selezione tramite checkbox e pagina di emissione con recap cumulativo e per atleta.
- PDF cumulativo, ricevute individuali in ZIP oppure entrambi, generati localmente dal browser.
- Marcatura e riapertura delle quote rimborsate da elenco e recap, conservate dopo le sincronizzazioni.
- Elenco **Giocatori** e scheda di ogni atleta con tornei, gare e stato dei rimborsi, accessibile anche dai nomi nelle iscrizioni.
- Scheda giocatore filtrata inizialmente sull'ultima annata disponibile, con scelta delle annate precedenti o di tutte.
- Preferenze per ogni tabella conservate nel browser: filtri di colonna, ricerca, ordinamento, posizione/larghezza delle colonne e righe per pagina.
- Colonne **Ambito torneo** e **Comitato**: comitato 30 nazionale; gli altri territoriali, nomi dal menu pubblico FITeT.
- Frontend con comandi touch e wp-admin con moduli e elenco utenti adattati agli schermi piccoli.
- Quote automatiche per ricevute omogenee e correzioni manuali che sopravvivono alle sincronizzazioni.
- Download e parsing PDF sul backend, nella cartella `uploads/fitet-ricevute/ricevute-pdf/`.

Il flusso di sincronizzazione effettua login, ricerca dei link, download, parsing e importazione.
**Flusso 0.6.1:** con una sola sessione extranet legge i cataloghi territoriali e nazionali e il riepilogo
partecipazioni/ricevute. Salva i tornei dai testi e dai link dei cataloghi, senza aprire i dettagli.
Solo i link PDF effettivamente presenti nel riepilogo partecipazioni diventano download da elaborare.
Ogni avanzamento scarica un PDF pubblico senza autenticazione o cookie, poi lo analizza e importa come prima.
I tornei senza ricevuta compaiono con zero iscrizioni e “Nessuna ricevuta”, senza prezzi fittizi.
Il collegamento catalogo/ricevuta usa comitato, identificativo e data osservati: nessun ANNO inventato.
Il catalogo non sovrascrive ricevute già importate, prezzi manuali o rimborsi.

**Verifica reale del 31 ottobre 2026:** 80 tornei (58 territoriali), 19 ricevute elencate;
un login, sei richieste HTTP per leggere tutto, zero dettagli/frame aperti.
Consulta [modello e limiti](docs/DATI.md).
L'import/export Excel resta da implementare. La grafica delle ricevute è un modello iniziale modificabile.

## Prezzi e rimborsi

Apri **Prezzi tornei** e tocca o fai clic su **Quota (€)**. La modifica viene salvata alla conferma
della cella; una cella vuota o **Ripristina automatico** rimuove la correzione manuale.
Il controllo somma quota effettiva × numero iscrizioni per tutte le gare della ricevuta e confronta
il risultato con il totale originale. Se manca un prezzo, indica **Da completare**.
Il totale originale, ripetuto sulle gare, non viene sommato più volte.

In **Iscrizioni**, seleziona fino a 1000 quote con le checkbox e premi **Emetti rimborso**.
La selezione resta valida tra pagine e filtri; **Deseleziona tutto** la azzera.
Il recap mostra sia le quote sia i totali per atleta. Puoi modificare società emittente, data,
note e ripartizione dei doppi: metà per atleta, tutto al primo oppure tutto al secondo.
Con la divisione a metà, un centesimo dispari è assegnato al primo atleta e la somma resta esatta.
Il prezzo del doppio e lo stato del rimborso si riferiscono all'intera coppia.

**Genera PDF** ricontrolla prezzi e stato tramite API prima del download; se sono cambiati,
richiede una nuova verifica del recap. Le quote già rimborsate sono escluse dai nuovi PDF.
Scaricare un PDF non equivale a effettuare un pagamento e non modifica la marcatura.
**Segna rimborsata** registra la quota corrente, autore e data; una successiva modifica del prezzo
non cambia l'importo già registrato. La riapertura è esplicita. Questa tabella conserva lo stato
corrente, non costituisce un registro storico delle emissioni o dei pagamenti.
La selezione (solo ID) è conservata in sessionStorage per utente e rimossa al logout.

## Giocatori e preferenze del browser

**Giocatori** mostra gli atleti presenti nelle ricevute. La tessera identifica il giocatore;
se assente, si usa il nome normalizzato. Un'iscrizione di doppio compare nelle schede di entrambi.
La scheda mostra una riga per partecipazione e conta separatamente tornei distinti, partecipazioni
e quote rimborsate. Importi e stato dei doppi si riferiscono alla quota della coppia, senza inventare
pagamenti parziali o attribuzioni individuali non registrate.

Le annate sono derivate dalle date dei tornei, dal 1° luglio al 30 giugno. **Ultima disponibile**
sceglie l'annata più recente in tutto il dataset della società e avanza quando vengono importati nuovi tornei.
Un atleta senza partecipazioni in quell'annata mostra un elenco vuoto; non si passa silenziosamente a un anno precedente.
La scelta di un'annata specifica o di tutte le annate viene conservata e prevale sul valore iniziale.

Le preferenze sono salvate in **localStorage**, distinte per installazione WordPress, account del portale
e tabella. Restano dopo logout, chiusura del browser e nuovo accesso; cancellare i dati del browser le elimina.
Le preferenze della scheda giocatore sono condivise tra le schede degli atleti, mentre i due recap rimborsi
mantengono impostazioni indipendenti. Non vengono salvati token, password o dataset delle ricevute.
Le ricerche e i valori dei filtri inseriti dall'utente sono naturalmente parte delle preferenze salvate.
**Azzera filtri e ordine** ripristina ricerca e ordinamento; nella scheda giocatore torna all'ultima annata.
Se il browser blocca localStorage, le pagine rimangono utilizzabili senza conservare le preferenze.

Il tipo nazionale/territoriale deriva dal parametro originale `COMITATO`, non dal titolo del torneo.
I nomi dei 21 comitati territoriali sono estratti dal [menu pubblico FITeT](https://portale.fitet.org/menu.php),
verificato il 24 ottobre 2026. La mappa in `includes/committees.php` include il nazionale 30;
un codice sconosciuto resta visibile come “Comitato N”. Il nome originale del PDF viene conservato separatamente.

## Installazione

Requisiti: WordPress 6.5+, PHP 8.1+ con DOM, iconv, zlib e OpenSSL, MySQL/MariaDB supportato da WordPress. Usa HTTPS in produzione.
Questa prima versione è destinata a una singola installazione WordPress; non attivare a livello di network multisite.

1. In **Plugin → Aggiungi plugin → Carica plugin**, seleziona lo ZIP in `release/` e attivalo.
2. Apri **FITET Ricevute → Configurazione** e imposta nome e codice della società.
   Nella sezione **Extranet FITeT** salva le credenziali per la sincronizzazione.
3. In **Utenti del portale**, crea il primo account con una password di almeno 12 caratteri.
4. Apri `https://miosito.it/fitet-ricevute/` ed effettua il login.

Gli account sono **separati da WordPress e da ResPong**: non viene importato alcun utente
e non viene attribuito accesso a wp-admin. Nessuna credenziale predefinita è inclusa nel plugin.
Se la pagina dà 404, salva nuovamente **Impostazioni → Permalink**. Il portale resta su
`/fitet-ricevute/`; la navigazione Angular usa il frammento `#/` per non richiedere altre rewrite.
Con permalink semplici è disponibile anche `/?fitet_ricevute_app=1`.
Escludi il portale e `/wp-json/fitet-ricevute/v1/*` dalla cache di pagina/CDN.

## Autenticazione

Il modello di riferimento è il portale ResPong: utenti dell'app distinti dagli utenti WP,
cookie di sessione e richieste REST sullo stesso dominio. Questa implementazione è indipendente:
non usa tabelle, cookie o segreti di ResPong e non richiede che sia installato.

- Password salvate con `wp_hash_password`, verificate con `wp_check_password`.
- Token casuale di 256 bit in cookie `HttpOnly`, `SameSite=Lax`, `Secure` su HTTPS.
- Il database conserva solo l'hash SHA-256 del token; Angular non lo legge. localStorage contiene solo le preferenze delle tabelle.
- Sessioni revocabili e scadenza assoluta: 12 ore di default; 30 giorni con “Ricordami”.
  Senza “Ricordami” il cookie termina anche alla chiusura del browser.
- Logout protetto da controllo dell'origine e token CSRF; revoca effettiva nel database.
- Verifica dell'origine anche per login e letture autenticate cross-origin.
- Dopo 10 login falliti per IP o username: pausa di 15 minuti. Viene usato `REMOTE_ADDR`,
  senza fidarsi di header proxy forniti dal client. Dietro proxy, configura correttamente il server.
- Modificare un account revoca tutte le sue sessioni. Cambiare la durata configurata vale
  per le nuove sessioni. Disabilitare il portale sospende l'accesso senza cancellare i dati.
- Configurazioni salvate con Settings API WordPress, permesso `manage_options` e nonce;
  operazioni sugli utenti protette da permesso e nonce WordPress.
- Recupero password autonomo non incluso: il reset viene eseguito dall'amministratore.

Cookie autenticati sul medesimo dominio: niente JWT, nessun plugin di autenticazione esterno.
Il codice non modifica i cookie o le sessioni di WordPress/ResPong. Le API rispondono `no-store`.

## Credenziali extranet FITeT

In **wp-admin → FITET Ricevute → Configurazione → Extranet FITeT** inserisci username e password.
Il salvataggio è protetto dal permesso WordPress `manage_options` e da un nonce dedicato.
È indipendente dal salvataggio delle impostazioni generali e dagli account del portale.

- La password viene cifrata con AES-256-GCM, tramite PHP OpenSSL, prima del salvataggio.
- La chiave è derivata dai segreti di WordPress; non viene salvata insieme alla password nel database.
- La password salvata non viene reinserita nell'HTML, né esposta tramite API o configurazione Angular.
- Il campo vuoto conserva la password esistente. Per sostituirla, inseriscine una nuova.
- Per cancellarla, seleziona **Rimuovi la password extranet FITeT salvata** e lascia vuoto il campo password.
- Le credenziali vengono salvate in un'opzione privata, senza autoload.

PHP OpenSSL con AES-256-GCM è richiesto per salvare o leggere la password. Se non disponibile,
il plugin segnala l'errore e non salva password in chiaro. Dopo una rotazione dei segreti WordPress
occorre reinserire la password. La classe backend `FR_Extranet_Credentials::for_sync()` viene usata
esclusivamente dal sincronizzatore. L'operazione parte dal pulsante **Sincronizza ricevute**.

## API

Namespace: `/wp-json/fitet-ricevute/v1/` (supportato anche `?rest_route=`).

| Metodo | Percorso | Accesso |
| --- | --- | --- |
| GET | `config` | Pubblico; solo nome portale, nome società, messaggio login e stato |
| POST | `auth/login` | JSON `{username, password, remember}`; origine valida e `X-FR-Request: 1` |
| GET | `auth/me` | Cookie valido; ritorna utente, scadenza e token CSRF |
| POST | `auth/logout` | Cookie, origine valida, `X-FR-Request: 1`, `X-FR-CSRF` |
| GET | `gare`, `iscrizioni` | Sessione; JOIN ai prezzi, `page` e `search` |
| PATCH | `prezzi/{race_id}` | Sessione e CSRF; `{manual_cents: intero oppure null}` |
| POST | `rimborsi/anteprima` | Sessione e CSRF; `{ids: [id iscrizioni]}`; tutte attive e della società configurata |
| PATCH | `rimborsi/stato` | Sessione e CSRF; `{ids: [id iscrizioni], reimbursed: boolean}`; aggiornamento atomico |
| POST | `sincronizzazione` | Sessione e CSRF; login extranet e scoperta ricevute |
| POST | `sincronizzazione/{id}/passo` | Sessione e CSRF; elaborazione di una ricevuta del proprio job |

Il login e `auth/me` restituiscono `{user: {id, username, display_name}, expires_at, csrf_token}`.
Nessuna API espone password, hash o token di sessione. Gli errori seguono il formato WordPress
`{code, message, data: {status}}`, con 401 per credenziali/sessioni non valide, 403 per richieste
non consentite, 429 per troppi tentativi e 503 per portale disabilitato.

Sincronizzazione FITeT e persistenza dei dati sono sul backend; generazione PDF nel browser.
In futuro, parsing/generazione Excel in Angular con scambio di dati strutturati tramite API.
Le note sui rimborsi aggiunte ai PDF sono escluse dai dati originali da importare.
Un totale misto non consente di ricavare entrambe le tariffe di singolare e doppio.

## Struttura

```text
fitet-ricevute.php       Bootstrap, attivazione e pulizia sessioni
includes/               Configurazione, repository, auth, API, admin, route frontend
app/src/                Sorgenti Angular e stili
assets/app/             Build Angular pronta per WordPress
app/scripts/package.mjs ZIP installabile, con i soli file necessari a runtime
tests/                  Ambiente isolato e controlli di integrazione
```

Tabelle di autenticazione: `{prefisso}fitet_ricevute_users` e `{prefisso}fitet_ricevute_sessions`.
Tabelle dati: `{prefisso}fitet_ricevute_gare`, `{prefisso}fitet_ricevute_iscrizioni`, `{prefisso}fitet_ricevute_prezzi`.
Stato operativo dei rimborsi: `{prefisso}fitet_ricevute_rimborsi`, separato dai dati originali.
Relazioni, granularità e politica di aggiornamento sono descritte in [DATI.md](docs/DATI.md).
Opzioni: `fitet_ricevute_settings`, `fitet_ricevute_schema`, `fitet_ricevute_extranet_credentials` (privata).
La disinstallazione revoca le sessioni e rimuove le credenziali extranet, ma conserva account
e impostazioni generali per evitare perdite accidentali.

## Sviluppo e build

Angular 21 LTS. Node 24 LTS (oppure una versione compatibile con Angular 21).
Dipendenze PHP tramite Composer: `composer install --no-dev --prefer-dist` dalla root del progetto.
Lo ZIP installabile comprende già la libreria PDF e non richiede Composer sul server.

```sh
cd app
npm ci
npm run build
npm run package
```

`npm run build` scrive in `assets/app/`; `npm run package` crea `release/fitet-ricevute-0.6.2.zip`
e un archivio `-source.zip` con il progetto completo, esclusi node_modules e ambiente di test generato.
Lo ZIP non contiene sorgenti Angular, node_modules, test, database o credenziali di sviluppo.

Per sviluppo autenticazione conviene eseguire `ng build --watch --configuration development`
e servire il portale attraverso WordPress: cookie e origine restano quelli reali.
`npm start` serve solo la UI: per il login tramite proxy occorre allineare esplicitamente
l'origine del sito locale WP e il proxy. La verifica dell'origine non viene disabilitata in sviluppo.

## Test isolati

I test copiano esclusivamente il core di un WordPress locale (non la sua configurazione o i suoi dati)
in `tests/.runtime/wordpress`, usando il driver ufficiale SQLite solo per i test.
Estrai il plugin ufficiale `sqlite-database-integration` in `tests/.runtime/`.

```sh
python tests/prepare-runtime.py C:/percorso/core-wordpress
php tests/install.php
php -S 127.0.0.1:8095 -t tests/.runtime/wordpress tests/router.php
```

In un altro terminale: `node tests/e2e.cjs` dopo `npm ci` in `app/` (o `FR_PLAYWRIGHT_MODULE`
impostato al modulo Playwright locale). `FR_BROWSER_PATH` può indicare Chrome/Chromium installato.
In alternativa, installa il browser di test con `npx playwright install chromium` da `app/`.
Gli account fittizi dei test vengono creati esclusivamente nel database ignorato sotto `.runtime`.
Per le credenziali extranet: `php tests/extranet.php` verifica cifratura e ciclo di vita;
`node tests/extranet.cjs` verifica i form wp-admin, nonce e assenza di esposizione pubblica.
`php tests/data.php` verifica schema, parsing, identità, importazione atomica, prezzi manuali e
sincronizzazione simulata. Scarica prima il PDF pubblico dell'esempio in `tests/.runtime/ricevuta-esempio.pdf`.
`php tests/sync-flow.php` verifica il flusso ASP osservato, i cookie e gli errori della ricerca usando fixture.
Questi test abilitano il mock solo nell'installazione isolata, senza inviare credenziali al vero FITeT.
`php tests/refunds.php` verifica riconciliazione, marcature atomiche e conservazione degli importi.
`node tests/refund-model.cjs` verifica ripartizioni e PDF, inclusi accenti e ricevute su più pagine.
`node tests/grid-pagination.cjs` verifica filtri e selezione su un dataset simulato di 51 righe, oltre la prima pagina API.
`php tests/committees.php` verifica i metadati e la classificazione nazionale/territoriale nelle API.
`node tests/players-model.cjs` verifica annate e conteggi delle schede giocatore.
`node tests/preferences-players.cjs` verifica persistenza dei filtri, navigazione giocatore, isolamento degli account e uso da mobile di frontend/wp-admin.
`node tests/touch.cjs` verifica con emulazione touch modifica della quota, selezione con tabella scorsa e logout.
`node tests/e2e.cjs` comprende anche griglie, prezzi, sincronizzazione simulata e download dei PDF.
Il font Noto Sans è distribuito con licenza SIL Open Font License in `app/src/fonts/LICENSE.txt`
e copiato nella build; AG Grid è la versione Community, senza funzioni Enterprise.

Riferimenti: [ResPong](https://github.com/gun88/res-pong-codex),
[REST API WordPress](https://developer.wordpress.org/rest-api/),
[versioni Angular](https://angular.dev/reference/releases),
[driver SQLite ufficiale](https://wordpress.org/plugins/sqlite-database-integration/).

## Paginazione e avanzamento (0.6.2)

Tutte le sei griglie partono da 100 righe per pagina. Le preferenze salvate nel browser hanno precedenza,
compresa una diversa dimensione di pagina scelta in precedenza; filtri e ordinamenti restano conservati.
Durante la ricerca iniziale la barra è indeterminata. Dopo la creazione del job mostra il rapporto
ricevute elaborate/totale, con percentuale, e resta al 100% dopo il completamento accanto al risultato.
La percentuale misura le ricevute elaborate: gli eventuali errori restano indicati separatamente.
